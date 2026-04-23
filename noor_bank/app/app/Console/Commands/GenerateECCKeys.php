<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CryptoService;

class GenerateECCKeys extends Command
{
    protected $signature = 'crypto:generate-keys {--bank=noor_bank}';
    protected $description = 'Générer les clés ECC P-256 pour Wassel Pay';

    public function handle(CryptoService $crypto)
    {
        $bank = $this->option('bank');
        
        $this->info("🔐 Génération des clés ECC pour $bank...");
        
        $keys = $crypto->generateKeyPair();
        
        $keyDir = base_path('keys');
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0755, true);
        }
        
        file_put_contents("$keyDir/private_$bank.pem", $keys['private']);
        file_put_contents("$keyDir/public_$bank.pem", $keys['public']);
        
        $this->info("✅ Clés générées !");
        $this->info("📁 Privée: keys/private_$bank.pem");
        $this->info("📁 Publique: keys/public_$bank.pem");
        
        $this->newLine();
        $this->info("📋 Clé publique à partager :");
        $this->line($keys['public']);
    }
}
