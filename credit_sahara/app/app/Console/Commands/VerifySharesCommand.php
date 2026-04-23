<?php

namespace App\Console\Commands;

use App\Services\SplitKeyService;
use Illuminate\Console\Command;

class VerifySharesCommand extends Command
{
    protected $signature = 'split-key:verify {--bank=credit_sahara}';
    protected $description = 'Vérifier l\'intégrité des parts de clé';
    
    public function handle()
    {
        $bank = $this->option('bank');
        
        $this->info('🔐 Vérification des parts pour ' . $bank);
        $this->newLine();
        
        try {
            $splitter = new SplitKeyService($bank);
            $results = $splitter->verifySharesIntegrity();
            
            if (isset($results['error'])) {
                $this->error($results['error']);
                return 1;
            }
            
            $rows = [];
            foreach ($results as $result) {
                $rows[] = [
                    $result['file'],
                    $result['role'],
                    $result['valid'] ? '✅' : '❌',
                    $result['message']
                ];
            }
            
            $this->table(['Fichier', 'Rôle', 'Statut', 'Message'], $rows);
            
            $validCount = count(array_filter($results, fn($r) => $r['valid']));
            $totalCount = count($results);
            
            $this->newLine();
            $this->info("📊 Résultat : {$validCount}/{$totalCount} parts valides");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Erreur : ' . $e->getMessage());
            return 1;
        }
    }
}
