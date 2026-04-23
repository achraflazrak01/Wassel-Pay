<?php

namespace App\Console\Commands;

use App\Services\CryptoService;
use Illuminate\Console\Command;

class CryptoBenchmark extends Command
{
    protected $signature = 'crypto:benchmark {--iterations=10}';
    protected $description = 'Benchmark RSA vs ECC P-256';
    
    public function handle()
    {
        $iterations = (int)$this->option('iterations');
        $crypto = new CryptoService();
        
        $this->info('🔬 BENCHMARK RSA-2048 vs ECC P-256');
        $this->info("Itérations: $iterations\n");
        
        // ============================================================
        // 1. GÉNÉRATION DES CLÉS
        // ============================================================
        $this->info('1. GÉNÉRATION DES CLÉS');
        
        // RSA-2048
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $rsaConfig = ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];
            $rsaKey = openssl_pkey_new($rsaConfig);
        }
        $rsaGenTime = (microtime(true) - $start) / $iterations * 1000;
        
        // ECC P-256
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $eccConfig = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
            $eccKey = openssl_pkey_new($eccConfig);
        }
        $eccGenTime = (microtime(true) - $start) / $iterations * 1000;
        
        $this->table(['Algorithme', 'Temps génération (ms)'], [
            ['RSA-2048', round($rsaGenTime, 2)],
            ['ECC P-256', round($eccGenTime, 2)],
            ['Gain ECC', round($rsaGenTime / $eccGenTime, 1) . 'x plus rapide']
        ]);
        
        // Générer les clés pour la suite
        $rsaKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($rsaKey, $rsaPrivate);
        $rsaPublic = openssl_pkey_get_details($rsaKey)['key'];
        
        $eccKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($eccKey, $eccPrivate);
        $eccPublic = openssl_pkey_get_details($eccKey)['key'];
        
        // ============================================================
        // 2. SIGNATURE
        // ============================================================
        $this->newLine();
        $this->info('2. SIGNATURE ECDSA/RSA');
        
        $data = str_repeat('A', 1024); // 1KB de données
        
        // RSA signature
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            openssl_sign($data, $rsaSig, $rsaPrivate, OPENSSL_ALGO_SHA256);
        }
        $rsaSignTime = (microtime(true) - $start) / $iterations * 1000;
        $rsaSigSize = strlen(base64_encode($rsaSig));
        
        // ECC signature
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            openssl_sign($data, $eccSig, $eccPrivate, OPENSSL_ALGO_SHA256);
        }
        $eccSignTime = (microtime(true) - $start) / $iterations * 1000;
        $eccSigSize = strlen(base64_encode($eccSig));
        
        $this->table(['Algorithme', 'Temps signature (ms)', 'Taille signature (bytes)'], [
            ['RSA-2048', round($rsaSignTime, 2), $rsaSigSize],
            ['ECC P-256', round($eccSignTime, 2), $eccSigSize],
            ['Gain ECC', round($rsaSignTime / $eccSignTime, 1) . 'x plus rapide', round($eccSigSize / $rsaSigSize * 100, 1) . '%']
        ]);
        
        // ============================================================
        // 3. VÉRIFICATION
        // ============================================================
        $this->newLine();
        $this->info('3. VÉRIFICATION DE SIGNATURE');
        
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            openssl_verify($data, $rsaSig, $rsaPublic, OPENSSL_ALGO_SHA256);
        }
        $rsaVerifyTime = (microtime(true) - $start) / $iterations * 1000;
        
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            openssl_verify($data, $eccSig, $eccPublic, OPENSSL_ALGO_SHA256);
        }
        $eccVerifyTime = (microtime(true) - $start) / $iterations * 1000;
        
        $this->table(['Algorithme', 'Temps vérification (ms)'], [
            ['RSA-2048', round($rsaVerifyTime, 2)],
            ['ECC P-256', round($eccVerifyTime, 2)],
            ['Gain ECC', round($rsaVerifyTime / $eccVerifyTime, 1) . 'x plus rapide']
        ]);
        
        // ============================================================
        // 4. TAILLE DES CLÉS
        // ============================================================
        $this->newLine();
        $this->info('4. TAILLE DES CLÉS');
        
        $this->table(['Algorithme', 'Clé privée', 'Clé publique'], [
            ['RSA-2048', '~2048 bits (256 bytes)', '~2048 bits (256 bytes)'],
            ['ECC P-256', '~256 bits (32 bytes)', '~256 bits (32 bytes)'],
            ['Gain ECC', '8x plus petit', '8x plus petit']
        ]);
        
        // ============================================================
        // 5. NIVEAU DE SÉCURITÉ
        // ============================================================
        $this->newLine();
        $this->info('5. NIVEAU DE SÉCURITÉ COMPARÉ');
        
        $this->table(['Algorithme', 'Taille clé', 'Sécurité (bits)', 'Années de sécurité estimées'], [
            ['RSA-2048', '2048 bits', '112 bits', '~2030'],
            ['ECC P-256', '256 bits', '128 bits', '~2050+'],
            ['Conclusion', '', 'ECC offre un meilleur niveau pour une taille plus petite', '']
        ]);
        
        // ============================================================
        // CONCLUSION
        // ============================================================
        $this->newLine();
        $this->info('📊 CONCLUSION');
        $this->line('┌─────────────────────────────────────────────────────────────────────┐');
        $this->line('│  ECC P-256 est supérieur à RSA-2048 pour les applications bancaires  │');
        $this->line('├─────────────────────────────────────────────────────────────────────┤');
        $this->line('│  ✅ Génération : ' . round($rsaGenTime / $eccGenTime, 1) . 'x plus rapide      │');
        $this->line('│  ✅ Signature   : ' . round($rsaSignTime / $eccSignTime, 1) . 'x plus rapide      │');
        $this->line('│  ✅ Vérification: ' . round($rsaVerifyTime / $eccVerifyTime, 1) . 'x plus rapide      │');
        $this->line('│  ✅ Taille clé  : 8x plus petite                              │');
        $this->line('│  ✅ Sécurité    : 128 bits vs 112 bits                         │');
        $this->line('└─────────────────────────────────────────────────────────────────────┘');
        
        return 0;
    }
}
