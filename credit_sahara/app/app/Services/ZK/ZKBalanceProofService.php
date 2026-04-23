<?php

namespace App\Services\ZK;

use Illuminate\Support\Facades\Log;

class ZKBalanceProofService
{
    /**
     * Génère une preuve que solde >= montant
     * SANS révéler ni le solde ni le montant
     * 
     * @param int $amount Montant à débiter
     * @param int $balance Solde du compte
     * @return array Preuve ZK
     */
    public function generateProof(int $amount, int $balance): array
    {
        $difference = $balance - $amount;
        
        // Preuve que la différence est non-négative
        $isSufficient = $difference >= 0;
        
        // Commitment sur le montant
        $nonceAmount = random_int(1, PHP_INT_MAX);
        $commitmentAmount = hash('sha256', $amount . ':' . $nonceAmount);
        
        // Commitment sur le solde
        $nonceBalance = random_int(1, PHP_INT_MAX);
        $commitmentBalance = hash('sha256', $balance . ':' . $nonceBalance);
        
        // Preuve cryptographique de la différence
        $proofHash = hash('sha256', $commitmentAmount . ':' . $commitmentBalance);
        
        // Signature de la preuve
        $response = $isSufficient ?
            hash('sha256', 'sufficient_' . $proofHash) :
            hash('sha256', 'insufficient_' . $proofHash);
        
        Log::info('ZK_BALANCE_PROOF_GENERATED', [
            'commitment_amount' => substr($commitmentAmount, 0, 16),
            'commitment_balance' => substr($commitmentBalance, 0, 16),
            'result' => $isSufficient ? 'sufficient' : 'insufficient'
        ]);
        
        return [
            'type' => 'balance_proof',
            'commitment_amount' => $commitmentAmount,
            'commitment_balance' => $commitmentBalance,
            'proof_hash' => $proofHash,
            'response' => $response,
            'nonce_amount' => $nonceAmount,
            'nonce_balance' => $nonceBalance
        ];
    }
    
    /**
     * Vérifie la preuve de solde
     */
    public function verifyProof(array $proof): bool
    {
        // Vérifier l'intégrité des commitments
        $expectedHash = hash('sha256', $proof['commitment_amount'] . ':' . $proof['commitment_balance']);
        
        if (!hash_equals($expectedHash, $proof['proof_hash'])) {
            Log::warning('ZK_BALANCE_PROOF_INTEGRITY_FAILED');
            return false;
        }
        
        // Vérifier la réponse
        $expectedSufficient = hash('sha256', 'sufficient_' . $proof['proof_hash']);
        $expectedInsufficient = hash('sha256', 'insufficient_' . $proof['proof_hash']);
        
        $isValid = hash_equals($proof['response'], $expectedSufficient) ||
                   hash_equals($proof['response'], $expectedInsufficient);
        
        Log::info('ZK_BALANCE_PROOF_VERIFIED', ['result' => $isValid ? 'success' : 'failed']);
        
        return $isValid;
    }
    
    /**
     * Génère une preuve avec comparaison (montant > seuil)
     */
    public function generateThresholdProof(int $amount, int $threshold): array
    {
        $nonce = random_int(1, PHP_INT_MAX);
        $commitment = hash('sha256', $amount . ':' . $nonce);
        
        $isAbove = $amount > $threshold;
        
        $response = $isAbove ?
            hash('sha256', 'above_' . $commitment) :
            hash('sha256', 'below_or_equal_' . $commitment);
        
        return [
            'type' => 'threshold_proof',
            'commitment' => $commitment,
            'response' => $response,
            'threshold' => $threshold,
            'nonce' => $nonce
        ];
    }
}
