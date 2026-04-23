<?php

namespace App\Services\ZK;

use Illuminate\Support\Facades\Log;

class ZKSetMembershipService
{
    private array $whitelist = [];
    private array $merkleTree = [];
    private string $rootHash = '';
    
    /**
     * Initialise la whitelist des IBAN autorisés
     */
    public function __construct(array $whitelist = [])
    {
        if (!empty($whitelist)) {
            $this->whitelist = $whitelist;
            $this->buildMerkleTree();
        }
    }
    
    /**
     * Ajoute un IBAN à la whitelist
     */
    public function addToWhitelist(string $iban): void
    {
        if (!in_array($iban, $this->whitelist)) {
            $this->whitelist[] = $iban;
            $this->buildMerkleTree();
            
            Log::info('ZK_WHITELIST_UPDATED', ['count' => count($this->whitelist)]);
        }
    }
    
    /**
     * Construit l'arbre de Merkle pour la whitelist
     */
    private function buildMerkleTree(): void
    {
        // Créer les feuilles (hash des IBAN)
        $leaves = array_map(fn($iban) => hash('sha256', $iban), $this->whitelist);
        
        // Construction récursive
        $this->merkleTree = $this->buildTree($leaves);
        $this->rootHash = !empty($this->merkleTree) ? end($this->merkleTree) : '';
    }
    
    private function buildTree(array $nodes): array
    {
        if (count($nodes) === 1) {
            return $nodes;
        }
        
        $parentNodes = [];
        for ($i = 0; $i < count($nodes); $i += 2) {
            $left = $nodes[$i];
            $right = isset($nodes[$i + 1]) ? $nodes[$i + 1] : $left;
            $parentNodes[] = hash('sha256', $left . $right);
        }
        
        return array_merge($nodes, $this->buildTree($parentNodes));
    }
    
    /**
     * Trouve l'index d'un IBAN dans la whitelist
     */
    private function findIndex(string $iban): ?int
    {
        $index = array_search($iban, $this->whitelist);
        return $index !== false ? $index : null;
    }
    
    /**
     * Récupère le chemin de preuve (siblings) pour un index
     */
    private function getProofPath(int $index): array
    {
        $siblings = [];
        $level = 0;
        $levelSize = count($this->whitelist);
        
        while ($levelSize > 1) {
            $siblingIndex = ($index % 2 == 0) ? $index + 1 : $index - 1;
            
            if ($siblingIndex < $levelSize) {
                // Calculer la position du sibling dans l'arbre
                $nodeIndex = $this->getNodeIndex($level, $siblingIndex);
                if (isset($this->merkleTree[$nodeIndex])) {
                    $siblings[] = $this->merkleTree[$nodeIndex];
                }
            }
            
            $level++;
            $index = floor($index / 2);
            $levelSize = ceil($levelSize / 2);
        }
        
        return $siblings;
    }
    
    private function getNodeIndex(int $level, int $position): int
    {
        $offset = 0;
        for ($i = 0; $i < $level; $i++) {
            $offset += ceil(count($this->whitelist) / pow(2, $i));
        }
        return $offset + $position;
    }
    
    /**
     * Génère une preuve qu'un IBAN est dans la whitelist
     * SANS révéler l'IBAN
     */
    public function generateMembershipProof(string $iban): ?array
    {
        $index = $this->findIndex($iban);
        
        if ($index === null) {
            Log::warning('ZK_MEMBERSHIP_PROOF_FAILED', ['iban_hash' => hash('sha256', $iban)]);
            return null;
        }
        
        $leaf = hash('sha256', $iban);
        $siblings = $this->getProofPath($index);
        
        Log::info('ZK_MEMBERSHIP_PROOF_GENERATED', [
            'leaf_hash' => substr($leaf, 0, 16),
            'siblings_count' => count($siblings)
        ]);
        
        return [
            'type' => 'membership_proof',
            'leaf' => $leaf,
            'root' => $this->rootHash,
            'siblings' => $siblings,
            'index' => $index,
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    /**
     * Vérifie la preuve d'appartenance
     * SANS connaître l'IBAN
     */
    public function verifyMembershipProof(array $proof): bool
    {
        if (!isset($proof['leaf'], $proof['root'], $proof['siblings'], $proof['index'])) {
            return false;
        }
        
        $hash = $proof['leaf'];
        $index = $proof['index'];
        
        // Reconstruire la racine à partir des siblings
        foreach ($proof['siblings'] as $sibling) {
            if ($index % 2 == 0) {
                $hash = hash('sha256', $hash . $sibling);
            } else {
                $hash = hash('sha256', $sibling . $hash);
            }
            $index = floor($index / 2);
        }
        
        $isValid = hash_equals($hash, $proof['root']);
        
        Log::info('ZK_MEMBERSHIP_PROOF_VERIFIED', [
            'result' => $isValid ? 'valid' : 'invalid',
            'root_hash' => substr($proof['root'], 0, 16)
        ]);
        
        return $isValid;
    }
    
    /**
     * Retourne la racine Merkle (publique)
     */
    public function getRootHash(): string
    {
        return $this->rootHash;
    }
    
    /**
     * Retourne le nombre d'IBANs dans la whitelist
     */
    public function getWhitelistCount(): int
    {
        return count($this->whitelist);
    }
}
