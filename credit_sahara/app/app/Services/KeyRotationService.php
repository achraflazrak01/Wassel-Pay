<?php

namespace App\Services;

use App\Services\CryptoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class KeyRotationService
{
    private CryptoService $crypto;
    private int $MAX_KEY_AGE_DAYS = 90;  // Rotation tous les 90 jours
    private string $keyPath;
    private string $publicKeyPath;
    private string $archivePath;
    private string $bankName;
    
    public function __construct(string $bankName = 'credit_sahara')
    {
        $this->crypto = new CryptoService();
        $this->bankName = $bankName;
        $this->keyPath = base_path("keys/private_{$bankName}.pem");
        $this->publicKeyPath = base_path("keys/public_{$bankName}.pem");
        $this->archivePath = base_path("keys/archive");
        
        // Créer le dossier d'archive s'il n'existe pas
        if (!is_dir($this->archivePath)) {
            mkdir($this->archivePath, 0755, true);
        }
    }
    
    /**
     * Vérifier l'âge de la clé actuelle
     * @return array ['age_days' => int, 'needs_rotation' => bool, 'expires_in' => int]
     */
    public function checkKeyAge(): array
    {
        if (!file_exists($this->keyPath)) {
            return [
                'age_days' => 0,
                'needs_rotation' => true,
                'expires_in' => 0,
                'error' => 'Clé non trouvée'
            ];
        }
        
        $keyAge = time() - filemtime($this->keyPath);
        $daysOld = floor($keyAge / 86400);
        
        return [
            'age_days' => $daysOld,
            'needs_rotation' => $daysOld >= $this->MAX_KEY_AGE_DAYS,
            'expires_in' => max(0, $this->MAX_KEY_AGE_DAYS - $daysOld),
            'key_path' => $this->keyPath,
            'last_modified' => date('Y-m-d H:i:s', filemtime($this->keyPath))
        ];
    }
    
    /**
     * Calculer l'empreinte (fingerprint) d'une clé
     */
    private function getKeyFingerprint(string $keyContent): string
    {
        return hash('sha256', $keyContent);
    }
    
    /**
     * Archiver l'ancienne clé avant rotation
     */
    private function archiveOldKey(string $keyContent, string $type): string
    {
        $date = date('Ymd_His');
        $archiveFile = "{$this->archivePath}/{$type}_{$this->bankName}_{$date}.pem";
        file_put_contents($archiveFile, $keyContent);
        return $archiveFile;
    }
    
    /**
     * Ajouter une clé à la liste de révocation
     */
    private function addToRevocationList(string $fingerprint, string $reason, ?string $replacedBy = null): void
    {
        DB::table('key_revocation_list')->insert([
            'key_fingerprint' => $fingerprint,
            'key_type' => 'ECC_P256',
            'bank' => $this->bankName,
            'revoked_at' => now(),
            'reason' => $reason,
            'replaced_by' => $replacedBy,
            'expires_at' => now()->addDays($this->MAX_KEY_AGE_DAYS),
            'created_at' => now()
        ]);
    }
    
    /**
     * Journaliser l'historique de rotation
     */
    private function logRotationHistory(string $oldFingerprint, string $newFingerprint, int $ageDays, bool $success, ?string $error = null): void
    {
        DB::table('key_rotation_history')->insert([
            'old_key_fingerprint' => $oldFingerprint,
            'new_key_fingerprint' => $newFingerprint,
            'bank' => $this->bankName,
            'initiated_by' => 'system',
            'key_age_days' => $ageDays,
            'success' => $success,
            'log_details' => $error,
            'rotated_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    /**
     * Notifier l'autre banque du changement de clé
     */
    private function notifyOtherBank(string $newPublicKey, string $oldFingerprint, string $newFingerprint): array
    {
        $otherBank = ($this->bankName === 'credit_sahara') ? 'noor_bank' : 'credit_sahara';
        $url = "http://app_{$otherBank}:8000/api/keys/update";
        
        try {
            $response = Http::timeout(30)->post($url, [
                'bank' => $this->bankName,
                'new_public_key' => $newPublicKey,
                'old_fingerprint' => $oldFingerprint,
                'new_fingerprint' => $newFingerprint,
                'timestamp' => now()->toIso8601String(),
                'signature' => $this->generateNotificationSignature($newPublicKey)
            ]);
            
            return [
                'success' => $response->successful(),
                'message' => $response->json('message', 'Notification envoyée'),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }
    
    /**
     * Générer une signature pour la notification (sécurité)
     */
    private function generateNotificationSignature(string $data): string
    {
        $secret = env('KEY_ROTATION_SECRET', 'wassel_pay_secret_2025');
        return hash_hmac('sha256', $data, $secret);
    }
    
    /**
     * Re-signer les transactions en attente avec la nouvelle clé
     */
    private function reSignPendingTransactions(string $newPrivateKey): int
    {
        $count = 0;
        $transactions = DB::table('transactions')
            ->where('status', 'pending')
            ->where('sender_bank', $this->bankName)
            ->get();
        
        foreach ($transactions as $transaction) {
            try {
                // Re-signer les données avec la nouvelle clé
                $newSignature = $this->crypto->sign($transaction->encrypted_data, $newPrivateKey);
                
                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'signature' => $newSignature,
                        'key_version' => date('Ymd'),
                        'updated_at' => now()
                    ]);
                $count++;
            } catch (\Exception $e) {
                Log::warning("Failed to re-sign transaction {$transaction->id}", ['error' => $e->getMessage()]);
            }
        }
        
        return $count;
    }
    
    /**
     * ROTATION PRINCIPALE : Effectuer la rotation complète des clés
     */
    public function rotateKeys(bool $force = false): array
    {
        $startTime = microtime(true);
        
        try {
            // 1. Vérifier l'âge de la clé
            $keyStatus = $this->checkKeyAge();
            
            if (!$force && !$keyStatus['needs_rotation']) {
                return [
                    'success' => true,
                    'rotated' => false,
                    'message' => "Clé encore valide (âge: {$keyStatus['age_days']} jours)",
                    'age_days' => $keyStatus['age_days'],
                    'expires_in' => $keyStatus['expires_in']
                ];
            }
            
            // 2. Lire l'ancienne clé
            $oldPrivateKey = file_get_contents($this->keyPath);
            $oldPublicKey = file_get_contents($this->publicKeyPath);
            $oldFingerprint = $this->getKeyFingerprint($oldPrivateKey);
            
            // 3. Archiver l'ancienne clé
            $archivePrivate = $this->archiveOldKey($oldPrivateKey, 'private');
            $archivePublic = $this->archiveOldKey($oldPublicKey, 'public');
            
            // 4. Générer la nouvelle paire de clés
            $newKeys = $this->crypto->generateKeyPair();
            $newFingerprint = $this->getKeyFingerprint($newKeys['private']);
            
            // 5. Sauvegarder les nouvelles clés
            file_put_contents($this->keyPath, $newKeys['private']);
            file_put_contents($this->publicKeyPath, $newKeys['public']);
            
            // 6. Ajouter l'ancienne clé à la liste de révocation
            $this->addToRevocationList($oldFingerprint, 'Scheduled rotation', $newFingerprint);
            
            // 7. Re-signer les transactions en attente
            $reSignedCount = $this->reSignPendingTransactions($newKeys['private']);
            
            // 8. Notifier l'autre banque
            $notification = $this->notifyOtherBank($newKeys['public'], $oldFingerprint, $newFingerprint);
            
            // 9. Journaliser l'historique
            $this->logRotationHistory($oldFingerprint, $newFingerprint, $keyStatus['age_days'], true);
            
            // 10. Loguer l'événement de sécurité
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::channel('security')->info('KEY_ROTATION_COMPLETED', [
                'bank' => $this->bankName,
                'old_fingerprint' => substr($oldFingerprint, 0, 16),
                'new_fingerprint' => substr($newFingerprint, 0, 16),
                'age_days' => $keyStatus['age_days'],
                'resigned_count' => $reSignedCount,
                'notification_sent' => $notification['success'],
                'duration_ms' => $duration
            ]);
            
            return [
                'success' => true,
                'rotated' => true,
                'message' => 'Rotation des clés effectuée avec succès',
                'old_fingerprint' => substr($oldFingerprint, 0, 16),
                'new_fingerprint' => substr($newFingerprint, 0, 16),
                'age_days' => $keyStatus['age_days'],
                'resigned_count' => $reSignedCount,
                'notification' => $notification,
                'archive_files' => [
                    'private' => $archivePrivate,
                    'public' => $archivePublic
                ],
                'duration_ms' => $duration
            ];
            
        } catch (\Exception $e) {
            // Journaliser l'erreur
            $errorMessage = $e->getMessage();
            
            Log::channel('security')->error('KEY_ROTATION_FAILED', [
                'bank' => $this->bankName,
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Journaliser l'échec dans l'historique
            $this->logRotationHistory('unknown', 'unknown', 0, false, $errorMessage);
            
            return [
                'success' => false,
                'rotated' => false,
                'message' => 'Erreur lors de la rotation: ' . $errorMessage,
                'error' => $errorMessage
            ];
        }
    }
    
    /**
     * Récupérer l'historique des rotations
     */
    public function getRotationHistory(int $limit = 10): array
    {
        return DB::table('key_rotation_history')
            ->where('bank', $this->bankName)
            ->orderBy('rotated_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    /**
     * Vérifier si une clé est révoquée
     */
    public function isKeyRevoked(string $fingerprint): bool
    {
        return DB::table('key_revocation_list')
            ->where('key_fingerprint', $fingerprint)
            ->exists();
    }
}
