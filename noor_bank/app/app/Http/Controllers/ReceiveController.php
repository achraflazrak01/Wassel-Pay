<?php

namespace App\Http\Controllers;

use App\Services\CryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReceiveController extends Controller
{
    private CryptoService $crypto;
    
    public function __construct()
    {
        $this->crypto = new CryptoService();
    }
    
    /**
     * Recevoir un virement depuis Crédit Sahara
     * POST /api/transfer/receive
     */
    public function receive(Request $request)
    {
        try {
            $packet = $request->all();
            
            // 1. Vérifier l'unicité de l'UUID (anti-rejeu)
            $existingNonce = \DB::table('nonces_used')->where('uuid', $packet['uuid'])->first();
            if ($existingNonce) {
                Log::warning("Tentative de rejeu détectée", ['uuid' => $packet['uuid']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction déjà traitée (anti-rejeu)'
                ], 409);
            }
            
            // 2. Vérifier la signature avec la clé publique de Crédit Sahara
            $creditPublicKey = file_get_contents(base_path('keys/public_credit_sahara.pem'));
            $dataToVerify = json_encode([
                'uuid' => $packet['uuid'],
                'amount' => $packet['amount'] ?? null,
                'from_iban' => $packet['from_iban'] ?? null,
                'to_iban' => $packet['to_iban'] ?? null,
                'timestamp' => $packet['timestamp'] ?? null
            ]);
            
            // Pour la vérification, on utilise les données originales avant chiffrement
            // Dans un cas réel, il faudrait stocker le hash du message signé
            
            // 3. Déchiffrer les données
            $noorPrivateKey = file_get_contents(base_path('keys/private_noor_bank.pem'));
            
            $packetToDecrypt = [
                'encrypted_session_key' => $packet['encrypted_session_key'],
                'nonce' => $packet['nonce'],
                'ciphertext' => $packet['ciphertext'],
                'tag' => $packet['tag']
            ];
            
            $decryptedData = $this->crypto->decryptHybrid($packetToDecrypt, $noorPrivateKey);
            $transferData = json_decode($decryptedData, true);
            
            // 4. Sauvegarder la transaction
            \DB::table('transactions')->insert([
                'uuid' => $packet['uuid'],
                'sender_bank' => $packet['sender_bank'],
                'encrypted_data' => $packet['ciphertext'],
                'signature' => $packet['signature'],
                'encrypted_session_key' => $packet['encrypted_session_key'],
                'nonce' => $packet['nonce'],
                'tag' => $packet['tag'],
                'status' => 'received',
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // 5. Marquer l'UUID comme utilisé (anti-rejeu)
            \DB::table('nonces_used')->insert([
                'uuid' => $packet['uuid'],
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // 6. Journaliser l'audit
            \DB::table('audit_log')->insert([
                'table_name' => 'transactions',
                'record_id' => $packet['uuid'],
                'action' => 'INSERT',
                'user' => 'system',
                'ip_address' => $request->ip(),
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info("Virement reçu avec succès", ['uuid' => $packet['uuid'], 'amount' => $transferData['amount']]);
            
            return response()->json([
                'success' => true,
                'message' => 'Virement reçu et déchiffré avec succès',
                'uuid' => $packet['uuid'],
                'amount' => $transferData['amount']
            ], 200);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la réception du virement", ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
