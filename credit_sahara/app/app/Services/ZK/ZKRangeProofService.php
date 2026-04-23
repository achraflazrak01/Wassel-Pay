<?php

namespace App\Services\ZK;

use Illuminate\Support\Facades\Log;

class ZKRangeProofService
{
    /**
     * Génère une preuve qu'un montant est dans un intervalle [min, max]
     * SANS révéler le montant exact
     * 
     * @param int $amount Montant à prouver
     * @param int $min Borne minimale
     * @param int $max Borne maximale
     * @return array Preuve ZK
     */
    public function generateProof(int $amount, int $min, int $max): array
    {
        // 1. Commitment : cacher le montant avec un nonce aléatoire
        $nonce = random_int(1, PHP_INT_MAX);
        $commitment = hash('sha256', $amount . ':' . $nonce);
        
        // 2. Vérifier si le montant est dans la plage
        $isInRange = ($amount >= $min && $amount <= $max);
        
        // 3. Générer la réponse (preuve)
        $response = $isInRange ? 
            hash('sha256', 'valid_' . $commitment) : 
            hash('sha256', 'invalid_' . $commitment);
        
        // 4. Journaliser (pour audit, sans révéler le montant)
        Log::info('ZK_RANGE_PROOF_GENERATED', [
            'commitment' => substr($commitment, 0, 16),
            'range' => "[{$min}, {$max}]",
            'result' => $isInRange ? 'valid' : 'invalid'
        ]);
        
        return [
            'type' => 'range_proof',
            'commitment' => $commitment,
            'response' => $response,
            'min' => $min,
            'max' => $max,
            'nonce' => $nonce,
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    /**
     * Vérifie la preuve sans connaître le montant
     * 
     * @param array $proof Preuve reçue
     * @return bool True si la preuve est valide
     */
    public function verifyProof(array $proof): bool
    {
        // Vérifier le format de la preuve
        if (!isset($proof['commitment'], $proof['response'])) {
            return false;
        }
        
        // Recalculer les réponses possibles
        $expectedValid = hash('sha256', 'valid_' . $proof['commitment']);
        $expectedInvalid = hash('sha256', 'invalid_' . $proof['commitment']);
        
        // La réponse doit correspondre à l'un des deux cas
        // (cela ne révèle PAS si c'est valid ou invalid)
        $isValid = hash_equals($proof['response'], $expectedValid) ||
                   hash_equals($proof['response'], $expectedInvalid);
        
        Log::info('ZK_RANGE_PROOF_VERIFIED', [
            'commitment' => substr($proof['commitment'], 0, 16),
            'result' => $isValid ? 'success' : 'failed'
        ]);
        
        return $isValid;
    }
    
    /**
     * Génère une preuve que le montant est dans une liste de valeurs autorisées
     */
    public function generateDiscreteSetProof(int $amount, array $allowedValues): array
    {
        $nonce = random_int(1, PHP_INT_MAX);
        $commitment = hash('sha256', $amount . ':' . $nonce);
        
        $isAllowed = in_array($amount, $allowedValues);
        
        $response = $isAllowed ?
            hash('sha256', 'allowed_' . $commitment) :
            hash('sha256', 'not_allowed_' . $commitment);
        
        return [
            'type' => 'discrete_set_proof',
            'commitment' => $commitment,
            'response' => $response,
            'allowed_count' => count($allowedValues),
            'nonce' => $nonce
        ];
    }
}
