<?php

namespace App\Console\Commands;

use App\Models\PreventiveMaintenance;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMaintenanceReminders extends Command
{
    protected $signature = 'maintenance:send-reminders';

    protected $description = 'Envoie des rappels pour les maintenances en retard ou à venir';

    public function handle(): int
    {
        $this->info('📬 Vérification des rappels de maintenance...');
        $this->newLine();

        // Maintenances préventives en retard
        $overduePlans = PreventiveMaintenance::query()
            ->where('is_active', true)
            ->where('next_execution_date', '<', Carbon::today())
            ->with(['equipment', 'assignedTo', 'site'])
            ->get();

        if ($overduePlans->count() > 0) {
            $this->warn("⚠️  {$overduePlans->count()} plan(s) de maintenance en retard:");
            foreach ($overduePlans as $plan) {
                $daysOverdue = Carbon::parse($plan->next_execution_date)->diffInDays(Carbon::today());
                $this->line("  - [{$plan->code}] {$plan->name} ({$daysOverdue} jour(s) de retard)");
                
                // Ici vous pourriez envoyer un email ou une notification
                // Mail::to($plan->assignedTo)->send(new MaintenanceOverdueNotification($plan));
            }
        } else {
            $this->info("✅ Aucune maintenance préventive en retard");
        }

        $this->newLine();

        // OT en cours depuis trop longtemps (> 7 jours)
        $longRunningWO = WorkOrder::query()
            ->where('status', 'in_progress')
            ->where('actual_start', '<', Carbon::now()->subDays(7))
            ->with(['equipment', 'assignedTo'])
            ->get();

        if ($longRunningWO->count() > 0) {
            $this->warn("⚠️  {$longRunningWO->count()} OT en cours depuis plus de 7 jours:");
            foreach ($longRunningWO as $wo) {
                $days = Carbon::parse($wo->actual_start)->diffInDays(Carbon::now());
                $this->line("  - [{$wo->code}] {$wo->title} ({$days} jours)");
            }
        } else {
            $this->info("✅ Aucun OT en cours prolongé");
        }

        $this->newLine();

        // OT urgents non assignés
        $urgentUnassigned = WorkOrder::query()
            ->whereIn('status', ['pending', 'approved'])
            ->where('priority', 'urgent')
            ->whereNull('assigned_to')
            ->get();

        if ($urgentUnassigned->count() > 0) {
            $this->error("🚨 {$urgentUnassigned->count()} OT urgent(s) non assigné(s):");
            foreach ($urgentUnassigned as $wo) {
                $this->line("  - [{$wo->code}] {$wo->title}");
            }
        } else {
            $this->info("✅ Aucun OT urgent non assigné");
        }

        Log::info('Vérification rappels maintenance', [
            'overdue_plans' => $overduePlans->count(),
            'long_running_wo' => $longRunningWO->count(),
            'urgent_unassigned' => $urgentUnassigned->count(),
        ]);

        return self::SUCCESS;
    }
}
