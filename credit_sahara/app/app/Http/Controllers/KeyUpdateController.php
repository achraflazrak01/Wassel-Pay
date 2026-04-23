<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KeyUpdateController extends Controller
{
    /**
     * Recevoir la nouvelle clé publique lors d'une rotation
     * POST /api/keys/update
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'bank' => 'required|string|in:credit_sahara,noor_bank',
                'new_public_key' => 'required|string',
                'old_fingerprint' => 'required|string',
                'new_fingerprint' => 'required|string',
                'timestamp' => 'required|string',
                'signature' => 'required|string'
            ]);
            
            // 1. Vérifier la signature (anti-contrefaçon)
            $expectedSignature = hash_hmac('sha256', $validated['new_public_key'], env('KEY_ROTATION_SECRET', 'wassel_pay_secret_2025'));
            
            if (!hash_equals($expectedSignature, $validated['signature'])) {
                Log::channel('security')->warning('INVALID_KEY_UPDATE_SIGNATURE', [
                    'bank' => $validated['bank'],
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Signature invalide'
                ], 401);
            }
            
            // 2. Vérifier le timestamp (anti-rejeu, max 5 minutes de décalage)
            $timestamp = strtotime($validated['timestamp']);
            $now = time();
            
            if (abs($now - $timestamp) > 300) {
                Log::channel('security')->warning('KEY_UPDATE_TIMESTAMP_INVALID', [
                    'bank' => $validated['bank'],
                    'timestamp' => $validated['timestamp'],
                    'now' => date('Y-m-d H:i:s', $now)
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Timestamp invalide'
                ], 401);
            }
            
            // 3. Sauvegarder la nouvelle clé publique
            $keyPath = base_path("keys/public_{$validated['bank']}.pem");
            $oldKeyContent = file_exists($keyPath) ? file_get_contents($keyPath) : null;
            
            // Archiver l'ancienne clé
            if ($oldKeyContent) {
                $archiveDir = base_path("keys/archive");
                if (!is_dir($archiveDir)) {
                    mkdir($archiveDir, 0755, true);
                }
                $date = date('Ymd_His');
                $archiveFile = "{$archiveDir}/public_{$validated['bank']}_{$date}.pem";
                file_put_contents($archiveFile, $oldKeyContent);
            }
            
            // Sauvegarder la nouvelle clé
            file_put_contents($keyPath, $validated['new_public_key']);
            
            // 4. Enregistrer dans l'historique
            DB::table('key_rotation_history')->insert([
                'old_key_fingerprint' => $validated['old_fingerprint'],
                'new_key_fingerprint' => $validated['new_fingerprint'],
                'bank' => $validated['bank'],
                'initiated_by' => 'remote_system',
                'key_age_days' => 0,
                'success' => true,
                'log_details' => 'Clé reçue via rotation API',
                'rotated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // 5. Journaliser l'événement
            Log::channel('security')->info('KEY_RECEIVED_FROM_REMOTE', [
                'bank' => $validated['bank'],
                'fingerprint' => substr($validated['new_fingerprint'], 0, 16),
                'old_fingerprint' => substr($validated['old_fingerprint'], 0, 16)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Clé publique mise à jour avec succès',
                'fingerprint' => substr($validated['new_fingerprint'], 0, 16)
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('KEY_UPDATE_ERROR', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer l'état des clés
     * GET /api/keys/status
     */
    public function status(Request $request)
    {
        $bank = $request->query('bank', 'credit_sahara');
        $keyPath = base_path("keys/public_{$bank}.pem");
        
        if (!file_exists($keyPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Clé non trouvée'
            ], 404);
        }
        
        $keyContent = file_get_contents($keyPath);
        $fingerprint = hash('sha256', $keyContent);
        
        return response()->json([
            'success' => true,
            'bank' => $bank,
            'fingerprint' => substr($fingerprint, 0, 16),
            'last_modified' => date('Y-m-d H:i:s', filemtime($keyPath)),
            'key_exists' => true
        ]);
    }
}
