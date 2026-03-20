<?php

namespace App\Console\Commands;

use App\Models\PreventiveMaintenance;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MaintenanceStats extends Command
{
    protected $signature = 'maintenance:stats {--period=month : Période (week, month, year)}';

    protected $description = 'Affiche les statistiques de maintenance';

    public function handle(): int
    {
        $period = $this->option('period');
        
        $startDate = match ($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        $this->info("📊 Statistiques de maintenance ({$period})");
        $this->info("   Depuis: {$startDate->format('d/m/Y')}");
        $this->newLine();

        // Stats OT
        $this->info('🔧 ORDRES DE TRAVAIL');
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total créés', WorkOrder::where('created_at', '>=', $startDate)->count()],
                ['En attente', WorkOrder::where('status', 'pending')->count()],
                ['En cours', WorkOrder::where('status', 'in_progress')->count()],
                ['Terminés', WorkOrder::where('status', 'completed')->where('completed_at', '>=', $startDate)->count()],
                ['Annulés', WorkOrder::where('status', 'cancelled')->where('updated_at', '>=', $startDate)->count()],
            ]
        );

        $this->newLine();

        // Stats par type
        $this->info('📈 PAR TYPE');
        $byType = WorkOrder::where('created_at', '>=', $startDate)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $typeLabels = ['corrective' => 'Corrective', 'preventive' => 'Préventive', 'improvement' => 'Amélioration', 'inspection' => 'Inspection'];
        $typeData = [];
        foreach ($typeLabels as $key => $label) {
            $typeData[] = [$label, $byType[$key] ?? 0];
        }
        $this->table(['Type', 'Nombre'], $typeData);

        $this->newLine();

        // Stats Préventif
        $this->info('📅 MAINTENANCE PRÉVENTIVE');
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Plans actifs', PreventiveMaintenance::where('is_active', true)->count()],
                ['Plans inactifs', PreventiveMaintenance::where('is_active', false)->count()],
                ['À exécuter cette semaine', PreventiveMaintenance::where('is_active', true)
                    ->whereBetween('next_execution_date', [Carbon::now(), Carbon::now()->endOfWeek()])
                    ->count()],
                ['En retard', PreventiveMaintenance::where('is_active', true)
                    ->where('next_execution_date', '<', Carbon::today())
                    ->count()],
            ]
        );

        $this->newLine();

        // Temps moyen de résolution
        $avgDuration = WorkOrder::where('status', 'completed')
            ->where('completed_at', '>=', $startDate)
            ->whereNotNull('actual_duration')
            ->avg('actual_duration');

        if ($avgDuration) {
            $hours = floor($avgDuration / 60);
            $minutes = $avgDuration % 60;
            $this->info("⏱️  Temps moyen de résolution: {$hours}h {$minutes}min");
        }

        // Coûts
        $totalCost = WorkOrder::where('status', 'completed')
            ->where('completed_at', '>=', $startDate)
            ->sum('total_cost');

        $this->info("💰 Coût total des interventions: " . number_format($totalCost, 2, ',', ' ') . " DA");

        return self::SUCCESS;
    }
}
