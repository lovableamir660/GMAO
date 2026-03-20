<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class GenerateNotifications extends Command
{
    protected $signature = 'notifications:generate {--site= : ID du site spécifique}';
    protected $description = 'Générer les notifications automatiques (stock critique, OT en retard, etc.)';

    public function handle(NotificationService $service): int
    {
        $siteId = $this->option('site');

        if ($siteId) {
            $sites = Site::where('id', $siteId)->get();
        } else {
            $sites = Site::where('is_active', true)->get();
        }

        foreach ($sites as $site) {
            $this->info("Génération pour le site: {$site->name}");
            
            $results = $service->generateForSite($site);
            
            foreach ($results as $type => $count) {
                if ($count > 0) {
                    $this->line("  - {$type}: {$count} notification(s)");
                }
            }
        }

        $this->info('Terminé !');
        return Command::SUCCESS;
    }
}
