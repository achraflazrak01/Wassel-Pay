<?php

namespace App\Console\Commands;

use App\Services\KeyRotationService;
use Illuminate\Console\Command;

class RotateKeysCommand extends Command
{
    /**
     * Nom de la commande : php artisan keys:rotate
     * Options : --bank=credit_sahara --force
     */
    protected $signature = 'keys:rotate 
                            {--bank=credit_sahara : Nom de la banque (credit_sahara ou noor_bank)}
                            {--force : Forcer la rotation même si l\'âge est inférieur à 90 jours}';
    
    protected $description = 'Effectuer la rotation automatique des clés ECC (90 jours)';
    
    public function handle()
    {
        $bank = $this->option('bank');
        $force = $this->option('force');
        
        $this->info('🔐 ==========================================');
        $this->info("🔐 Rotation des clés pour {$bank}");
        $this->info('🔐 ==========================================');
        $this->newLine();
        
        // Créer le service de rotation
        $rotationService = new KeyRotationService($bank);
        
        // Vérifier l'âge de la clé
        $keyStatus = $rotationService->checkKeyAge();
        
        $this->info("📊 État actuel :");
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Âge de la clé', $keyStatus['age_days'] . ' jours'],
                ['Expire dans', $keyStatus['expires_in'] . ' jours'],
                ['Rotation nécessaire', $keyStatus['needs_rotation'] ? '✅ OUI' : '❌ NON'],
                ['Emplacement', $keyStatus['key_path'] ?? 'N/A'],
                ['Dernière modification', $keyStatus['last_modified'] ?? 'N/A']
            ]
        );
        $this->newLine();
        
        // Demander confirmation
        if (!$force && !$keyStatus['needs_rotation']) {
            $this->warn("⚠️ La clé est encore valide. Utilisez --force pour forcer la rotation.");
            return;
        }
        
        if (!$force) {
            $confirmed = $this->confirm('⚠️ Voulez-vous vraiment effectuer la rotation des clés ?');
            if (!$confirmed) {
                $this->info('Opération annulée.');
                return;
            }
        }
        
        $this->newLine();
        $this->info('🔄 Démarrage de la rotation...');
        $this->newLine();
        
        // Exécuter la rotation
        $result = $rotationService->rotateKeys($force);
        
        $this->newLine();
        
        if ($result['success']) {
            if ($result['rotated']) {
                $this->info('✅ ' . $result['message']);
                $this->newLine();
                
                $this->info('📊 Résultat de la rotation :');
                $this->table(
                    ['Métrique', 'Valeur'],
                    [
                        ['Ancienne clé', $result['old_fingerprint'] . '...'],
                        ['Nouvelle clé', $result['new_fingerprint'] . '...'],
                        ['Âge', $result['age_days'] . ' jours'],
                        ['Transactions re-signées', $result['resigned_count']],
                        ['Notification', $result['notification']['success'] ? '✅ Envoyée' : '❌ Échouée'],
                        ['Durée', $result['duration_ms'] . ' ms']
                    ]
                );
                $this->newLine();
                
                $this->info('📁 Fichiers archivés :');
                $this->line("  • Privée: {$result['archive_files']['private']}");
                $this->line("  • Publique: {$result['archive_files']['public']}");
            } else {
                $this->info('ℹ️ ' . $result['message']);
            }
        } else {
            $this->error('❌ ' . $result['message']);
        }
        
        $this->newLine();
        
        return $result['success'] ? 0 : 1;
    }
}
