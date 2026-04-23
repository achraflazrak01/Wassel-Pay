<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SplitKeyService
{
    private const TOTAL_SHARES = 3;      // Nombre total de parts
    private const THRESHOLD = 2;          // Parts nécessaires pour reconstruire
    private const PRIME = 1000003;        // Nombre premier (pour modulo)
    
    private string $privateKey;
    private string $bankName;
    
    public function __construct(string $bankName = 'credit_sahara')
    {
        $this->bankName = $bankName;
        $keyPath = base_path("keys/private_{$bankName}.pem");
        
        if (!file_exists($keyPath)) {
            throw new \Exception("Clé privée non trouvée pour {$bankName}");
        }
        
        $this->privateKey = file_get_contents($keyPath);
    }
    
    /**
     * Convertit une clé PEM en entier pour le calcul Shamir
     */
    private function keyToInteger(string $key): int
    {
        // Prendre les 8 premiers caractères du hash comme entier
        $hash = hash('sha256', $key);
        return hexdec(substr($hash, 0, 8));
    }
    
    /**
     * Convertit un entier en hash de clé
     */
    private function integerToKey(int $value): string
    {
        // Reconstruire un hash à partir de l'entier
        return hash('sha256', (string)$value);
    }
    
    /**
     * Évalue un polynôme à un point x
     * f(x) = a₀ + a₁·x + a₂·x² + ... + a_{t-1}·x^{t-1}
     */
    private function evaluatePolynomial(array $coefficients, int $x): int
    {
        $result = 0;
        $power = 1;
        
        for ($i = 0; $i < count($coefficients); $i++) {
            $result = ($result + $coefficients[$i] * $power) % self::PRIME;
            $power = ($power * $x) % self::PRIME;
        }
        
        return $result;
    }
    
    /**
     * Fractionne la clé privée en parts (Shamir Secret Sharing)
     * @return array Les 3 parts avec leur checksum
     */
    public function splitKey(): array
    {
        $secret = $this->keyToInteger($this->privateKey);
        
        // Générer des coefficients aléatoires pour le polynôme
        // f(0) = secret (a₀)
        $coefficients = [$secret];
        
        for ($i = 1; $i < self::THRESHOLD; $i++) {
            $coefficients[] = random_int(1, self::PRIME - 1);
        }
        
        // Générer les parts (x, y)
        $shares = [];
        for ($x = 1; $x <= self::TOTAL_SHARES; $x++) {
            $y = $this->evaluatePolynomial($coefficients, $x);
            
            // Checksum pour vérifier l'intégrité de la part
            $checksum = hash('sha256', $x . ':' . $y . ':' . $this->bankName);
            
            $shares[] = [
                'index' => $x,
                'x' => $x,
                'y' => $y,
                'checksum' => $checksum,
                'role' => $this->getRoleForIndex($x),
                'bank' => $this->bankName
            ];
        }
        
        // Sauvegarder les parts
        $this->saveShares($shares);
        
        // Journaliser le fractionnement
        Log::channel('security')->info('KEY_SPLIT_COMPLETED', [
            'bank' => $this->bankName,
            'total_shares' => self::TOTAL_SHARES,
            'threshold' => self::THRESHOLD
        ]);
        
        return $shares;
    }
    
    /**
     * Détermine le rôle pour chaque part
     */
    private function getRoleForIndex(int $index): string
    {
        return match($index) {
            1 => 'admin_ceo',
            2 => 'dba_ops',
            3 => 'hsm_security',
            default => 'unknown'
        };
    }
    
    /**
     * Sauvegarde les parts dans des fichiers séparés
     */
    private function saveShares(array $shares): void
    {
        $sharesDir = base_path("keys/shares");
        if (!is_dir($sharesDir)) {
            mkdir($sharesDir, 0755, true);
        }
        
        foreach ($shares as $share) {
            $data = json_encode($share, JSON_PRETTY_PRINT);
            $filename = "{$sharesDir}/share_{$share['role']}_{$this->bankName}.json";
            file_put_contents($filename, $data);
            
            // Changer les permissions pour sécurité
            chmod($filename, 0600);
        }
    }
    
    /**
     * Charge une part depuis un fichier
     */
    private function loadShare(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        $share = json_decode($content, true);
        
        // Vérifier le checksum
        $expectedChecksum = hash('sha256', $share['x'] . ':' . $share['y'] . ':' . $share['bank']);
        
        if (!hash_equals($expectedChecksum, $share['checksum'])) {
            throw new \Exception("Intégrité de la part {$share['role']} compromise !");
        }
        
        return $share;
    }
    
    /**
     * Interpolation de Lagrange pour reconstruire le secret
     * Avec 2 points (x₁,y₁) et (x₂,y₂), on calcule f(0)
     */
    private function lagrangeInterpolation(array $points): int
    {
        $secret = 0;
        
        for ($i = 0; $i < self::THRESHOLD; $i++) {
            $xi = $points[$i]['x'];
            $yi = $points[$i]['y'];
            
            $numerator = 1;
            $denominator = 1;
            
            for ($j = 0; $j < self::THRESHOLD; $j++) {
                if ($i !== $j) {
                    $xj = $points[$j]['x'];
                    $numerator = ($numerator * -$xj) % self::PRIME;
                    $denominator = ($denominator * ($xi - $xj)) % self::PRIME;
                }
            }
            
            // Inverse modulaire du dénominateur
            $denomInv = $this->modInverse($denominator, self::PRIME);
            $lagrange = ($yi * $numerator * $denomInv) % self::PRIME;
            $secret = ($secret + $lagrange) % self::PRIME;
        }
        
        // Ajuster pour les valeurs négatives
        if ($secret < 0) {
            $secret += self::PRIME;
        }
        
        return $secret;
    }
    
    /**
     * Calcule l'inverse modulaire d'un nombre
     * a * x ≡ 1 (mod m)
     */
    private function modInverse(int $a, int $m): int
    {
        $a = $a % $m;
        
        for ($x = 1; $x < $m; $x++) {
            if (($a * $x) % $m == 1) {
                return $x;
            }
        }
        
        return 1;
    }
    
    /**
     * Reconstruit la clé privée à partir de parts
     * @param array $sharePaths Chemins des fichiers des parts (minimum 2)
     * @return string La clé privée reconstruite
     */
    public function reconstructKey(array $sharePaths): string
    {
        if (count($sharePaths) < self::THRESHOLD) {
            throw new \Exception("Il faut au moins " . self::THRESHOLD . " parts pour reconstruire la clé");
        }
        
        // Charger les parts
        $points = [];
        $roles = [];
        
        foreach ($sharePaths as $path) {
            $share = $this->loadShare($path);
            if ($share) {
                $points[] = ['x' => $share['x'], 'y' => $share['y']];
                $roles[] = $share['role'];
            }
        }
        
        if (count($points) < self::THRESHOLD) {
            throw new \Exception("Parts invalides ou corrompues");
        }
        
        // Reconstruire le secret avec Lagrange
        $secretInt = $this->lagrangeInterpolation($points);
        
        // Convertir en clé (simulation)
        $reconstructedHash = $this->integerToKey($secretInt);
        
        // Journaliser la reconstruction
        Log::channel('security')->alert('KEY_RECONSTRUCTED', [
            'bank' => $this->bankName,
            'roles' => $roles,
            'timestamp' => now()->toIso8601String()
        ]);
        
        // Note: Dans un vrai système, il faudrait reconstruire la clé PEM réelle
        // Pour la démonstration, on retourne un hash
        return $reconstructedHash;
    }
    
    /**
     * Vérifier l'intégrité de toutes les parts
     */
    public function verifySharesIntegrity(): array
    {
        $sharesDir = base_path("keys/shares");
        $results = [];
        
        if (!is_dir($sharesDir)) {
            return ['error' => 'Dossier des parts non trouvé'];
        }
        
        $files = glob($sharesDir . "/share_*_{$this->bankName}.json");
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $share = json_decode($content, true);
            
            $expectedChecksum = hash('sha256', $share['x'] . ':' . $share['y'] . ':' . $share['bank']);
            $isValid = hash_equals($expectedChecksum, $share['checksum']);
            
            $results[] = [
                'file' => basename($file),
                'role' => $share['role'],
                'valid' => $isValid,
                'message' => $isValid ? '✅ Intègre' : '❌ Corrompue'
            ];
        }
        
        return $results;
    }
    
    /**
     * Récupère la liste des parts disponibles
     */
    public function listShares(): array
    {
        $sharesDir = base_path("keys/shares");
        
        if (!is_dir($sharesDir)) {
            return [];
        }
        
        $files = glob($sharesDir . "/share_*_{$this->bankName}.json");
        $shares = [];
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $share = json_decode($content, true);
            $shares[] = [
                'file' => basename($file),
                'role' => $share['role'],
                'index' => $share['index'],
                'created_at' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        return $shares;
    }
    
    /**
     * Supprime toutes les parts (après rotation réussie)
     */
    public function cleanupShares(): int
    {
        $sharesDir = base_path("keys/shares");
        $count = 0;
        
        if (is_dir($sharesDir)) {
            $files = glob($sharesDir . "/share_*_{$this->bankName}.json");
            foreach ($files as $file) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        Log::info('SHARES_CLEANUP', ['bank' => $this->bankName, 'deleted' => $count]);
        
        return $count;
    }
}
