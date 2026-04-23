<?php

namespace App\Console\Commands;

use App\Services\SplitKeyService;
use Illuminate\Console\Command;

class SplitKeyCommand extends Command
{
    protected $signature = 'split-key:split {--bank=credit_sahara}';
    protected $description = 'Fractionner la clé privée en 3 parts (Shamir 2/3)';
    
    public function handle()
    {
        $bank = $this->option('bank');
        
        $this->info('🔐 ==========================================');
        $this->info("🔐 Fractionnement de la clé pour {$bank}");
        $this->info('🔐 ==========================================');
        $this->newLine();
        
        $this->warn('⚠️  Cette opération va fractionner la clé privée en 3 parts.');
        $this->warn('⚠️  Vous aurez besoin d\'au moins 2 parts pour reconstruire la clé.');
        $this->newLine();
        
        if (!$this->confirm('Voulez-vous continuer ?')) {
            $this->info('Opération annulée.');
            return;
        }
        
        try {
            $splitter = new SplitKeyService($bank);
            $shares = $splitter->splitKey();
            
            $this->newLine();
            $this->info('✅ Fractionnement réussi !');
            $this->newLine();
            
            $this->info('📊 Parts générées :');
            
            $rows = [];
            foreach ($shares as $share) {
                $rows[] = [
                    $share['index'],
                    $share['role'],
                    substr($share['checksum'], 0, 16) . '...',
                    "keys/shares/share_{$share['role']}_{$bank}.json"
                ];
            }
            
            $this->table(['#', 'Rôle', 'Checksum', 'Emplacement'], $rows);
            
            $this->newLine();
            $this->info('📁 Les parts sont stockées dans : keys/shares/');
            $this->warn('⚠️  Distribuez ces parts à 3 personnes différentes !');
            $this->warn('⚠️  Aucune personne seule ne peut reconstruire la clé !');
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
