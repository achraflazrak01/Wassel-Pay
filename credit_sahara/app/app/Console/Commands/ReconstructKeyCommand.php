<?php

namespace App\Console\Commands;

use App\Services\SplitKeyService;
use Illuminate\Console\Command;

class ReconstructKeyCommand extends Command
{
    protected $signature = 'split-key:reconstruct 
                            {--parts=* : Chemins des fichiers parts}
                            {--bank=credit_sahara}';
    protected $description = 'Reconstruire la clé privée à partir de parts (minimum 2)';
    
    public function handle()
    {
        $bank = $this->option('bank');
        $parts = $this->option('parts');
        
        $this->info('🔐 ==========================================');
        $this->info("🔐 Reconstruction de la clé pour {$bank}");
        $this->info('🔐 ==========================================');
        $this->newLine();
        
        // Si aucun part spécifié, lister les disponibles
        if (empty($parts)) {
            $splitter = new SplitKeyService($bank);
            $availableShares = $splitter->listShares();
            
            if (empty($availableShares)) {
                $this->error('Aucune part trouvée.');
                return 1;
            }
            
            $this->info('Parts disponibles :');
            foreach ($availableShares as $share) {
                $this->line("  • {$share['file']} ({$share['role']})");
            }
            $this->newLine();
            $this->info('Utilisez --parts="chemin1" --parts="chemin2" pour spécifier les parts');
            return;
        }
        
        if (count($parts) < 2) {
            $this->error('Il faut au moins 2 parts pour reconstruire la clé !');
            return 1;
        }
        
        try {
            $splitter = new SplitKeyService($bank);
            $reconstructedKey = $splitter->reconstructKey($parts);
            
            $this->newLine();
            $this->info('✅ Clé reconstruite avec succès !');
            $this->newLine();
            $this->info("🔑 Empreinte : " . substr($reconstructedKey, 0, 32) . "...");
            $this->newLine();
            $this->warn('⚠️  Cette clé peut maintenant être utilisée pour déchiffrer.');
            
        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
