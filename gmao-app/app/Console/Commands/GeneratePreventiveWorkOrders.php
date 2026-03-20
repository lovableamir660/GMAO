<?php

namespace App\Console\Commands;

use App\Models\PreventiveMaintenance;
use App\Models\PreventiveMaintenanceLog;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneratePreventiveWorkOrders extends Command
{
    protected $signature = 'maintenance:generate-preventive 
                            {--site= : ID du site spécifique}
                            {--dry-run : Simuler sans créer les OT}';

    protected $description = 'Génère automatiquement les ordres de travail pour les maintenances préventives à venir';

    public function handle(): int
    {
        $this->info('🔄 Début de la génération des OT préventifs...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $siteId = $this->option('site');

        if ($dryRun) {
            $this->warn('⚠️  Mode simulation activé - Aucun OT ne sera créé');
            $this->newLine();
        }

        // Récupérer les plans actifs qui nécessitent une génération
        $query = PreventiveMaintenance::query()
            ->where('is_active', true)
            ->whereNotNull('next_execution_date')
            ->with(['equipment', 'tasks', 'assignedTo']);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $plans = $query->get();

        $this->info("📋 {$plans->count()} plan(s) de maintenance actif(s) trouvé(s)");
        $this->newLine();

        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($plans as $plan) {
            $this->line("Traitement: [{$plan->code}] {$plan->name}");

            // Vérifier si une génération est nécessaire
            if (!$this->shouldGenerate($plan)) {
                $this->line("  ⏭️  Pas encore à générer (prochaine: {$plan->next_execution_date->format('d/m/Y')})");
                $skipped++;
                continue;
            }

            // Vérifier s'il n'y a pas déjà un OT en attente pour ce plan
            if ($this->hasPendingWorkOrder($plan)) {
                $this->line("  ⏭️  Un OT est déjà en attente pour ce plan");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->info("  ✅ [SIMULATION] OT serait généré");
                $generated++;
                continue;
            }

            try {
                $workOrder = $this->generateWorkOrder($plan);
                $this->info("  ✅ OT généré: {$workOrder->code}");
                $generated++;
            } catch (\Exception $e) {
                $this->error("  ❌ Erreur: {$e->getMessage()}");
                Log::error("Erreur génération OT préventif", [
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->newLine();
        $this->info('📊 Résumé:');
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Plans analysés', $plans->count()],
                ['OT générés', $generated],
                ['Ignorés', $skipped],
                ['Erreurs', $errors],
            ]
        );

        if ($generated > 0 && !$dryRun) {
            Log::info("Génération OT préventifs terminée", [
                'generated' => $generated,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Vérifie si un OT doit être généré pour ce plan
     */
    protected function shouldGenerate(PreventiveMaintenance $plan): bool
    {
        if (!$plan->next_execution_date) {
            return false;
        }

        // Date à partir de laquelle on doit générer l'OT
        $triggerDate = $plan->next_execution_date->copy()->subDays($plan->advance_days);

        return Carbon::today()->gte($triggerDate);
    }

    /**
     * Vérifie s'il y a déjà un OT en attente pour ce plan
     */
    protected function hasPendingWorkOrder(PreventiveMaintenance $plan): bool
    {
        return PreventiveMaintenanceLog::query()
            ->where('preventive_maintenance_id', $plan->id)
            ->where('scheduled_date', $plan->next_execution_date)
            ->whereIn('status', ['scheduled', 'generated'])
            ->exists();
    }

    /**
     * Génère l'OT pour un plan
     */
    protected function generateWorkOrder(PreventiveMaintenance $plan): WorkOrder
    {
        return DB::transaction(function () use ($plan) {
            // Construire la description avec les tâches
            $description = $plan->description ?? '';
            
            if ($plan->tasks->count() > 0) {
                $description .= "\n\n══════════════════════════════\n";
                $description .= "📋 TÂCHES À EFFECTUER\n";
                $description .= "══════════════════════════════\n\n";
                
                foreach ($plan->tasks as $index => $task) {
                    $num = $index + 1;
                    $description .= "☐ {$num}. {$task->description}\n";
                    
                    if ($task->instructions) {
                        $description .= "   📝 {$task->instructions}\n";
                    }
                    
                    if ($task->estimated_duration) {
                        $description .= "   ⏱️ Durée estimée: {$task->estimated_duration} min\n";
                    }
                    
                    $description .= "\n";
                }
            }

            $description .= "\n══════════════════════════════\n";
            $description .= "🔄 Généré automatiquement depuis: {$plan->code}\n";
            $description .= "📅 Date planifiée: {$plan->next_execution_date->format('d/m/Y')}\n";

            // Créer l'OT
            $workOrder = WorkOrder::create([
                'site_id' => $plan->site_id,
                'equipment_id' => $plan->equipment_id,
                'requested_by' => $plan->created_by, // Le créateur du plan
                'assigned_to' => $plan->assigned_to,
                'code' => WorkOrder::generateCode(),
                'title' => "[PM] {$plan->name}",
                'description' => $description,
                'type' => 'preventive',
                'priority' => $plan->priority,
                'status' => 'approved', // Directement approuvé
                'scheduled_start' => $plan->next_execution_date,
                'estimated_duration' => $plan->estimated_duration,
                'approved_by' => $plan->created_by,
                'approved_at' => now(),
            ]);

            // Ajouter l'historique sur l'OT
            $workOrder->addHistory(
                $plan->created_by,
                'created',
                "Généré automatiquement depuis le plan {$plan->code}"
            );

            // Créer le log de maintenance préventive
            PreventiveMaintenanceLog::create([
                'preventive_maintenance_id' => $plan->id,
                'work_order_id' => $workOrder->id,
                'scheduled_date' => $plan->next_execution_date,
                'status' => 'generated',
            ]);

            // Mettre à jour les dates du plan
            $plan->last_execution_date = $plan->next_execution_date;
            $plan->updateNextExecution();

            return $workOrder;
        });
    }
}
