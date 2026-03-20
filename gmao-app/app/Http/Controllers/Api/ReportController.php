<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\PreventiveMaintenance;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * KPIs principaux
     */
    public function kpis(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

        // MTTR - Mean Time To Repair (en heures)
        $mttrHours = $this->calculateMTTR($siteId, $startDate, $endDate);

        // MTBF - Mean Time Between Failures (en jours)
        $mtbf = $this->calculateMTBF($siteId, $startDate, $endDate);

        // Taux de disponibilité
        $availability = $this->calculateAvailability($siteId, $startDate, $endDate);

        // === STATISTIQUES OT ===
        $createdInPeriod = WorkOrder::where('site_id', $siteId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $completedInPeriod = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        $currentPending = WorkOrder::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'approved', 'in_progress', 'on_hold'])
            ->count();

        $totalActive = WorkOrder::where('site_id', $siteId)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Coûts
        $costs = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->selectRaw('SUM(total_cost) as total, SUM(labor_cost) as labor, SUM(parts_cost) as parts')
            ->first();

        // Ratio préventif/correctif
        $preventiveCount = WorkOrder::where('site_id', $siteId)
            ->where('type', 'preventive')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $correctiveCount = WorkOrder::where('site_id', $siteId)
            ->where('type', 'corrective')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $preventiveRatio = $createdInPeriod > 0 ? round(($preventiveCount / $createdInPeriod) * 100, 1) : 0;

        // Taux de résolution dans les délais
        $onTimeCount = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->whereNotNull('scheduled_end')
            ->whereRaw('completed_at <= scheduled_end')
            ->count();

        $scheduledCount = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->whereNotNull('scheduled_end')
            ->count();

        $onTimeRate = $scheduledCount > 0 ? round(($onTimeCount / $scheduledCount) * 100, 1) : 100;

        return response()->json([
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'kpis' => [
                'mttr' => $mttrHours,
                'mttr_unit' => 'heures',
                'mtbf' => $mtbf,
                'mtbf_unit' => 'jours',
                'availability' => $availability,
                'availability_unit' => '%',
                'preventive_ratio' => $preventiveRatio,
                'on_time_rate' => $onTimeRate,
            ],
            'work_orders' => [
                'created' => $createdInPeriod,
                'completed' => $completedInPeriod,
                'pending' => $currentPending,
                'total' => $totalActive,
                'preventive' => $preventiveCount,
                'corrective' => $correctiveCount,
            ],
            'costs' => [
                'total' => round($costs->total ?? 0, 2),
                'labor' => round($costs->labor ?? 0, 2),
                'parts' => round($costs->parts ?? 0, 2),
            ],
        ]);
    }

    /**
     * Calcul du MTTR - Mean Time To Repair (en heures)
     */
    protected function calculateMTTR(int $siteId, Carbon $startDate, Carbon $endDate): float
    {
        $workOrders = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->where('type', 'corrective')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();

        if ($workOrders->isEmpty()) {
            return 0;
        }

        $totalMinutes = 0;
        $count = 0;

        foreach ($workOrders as $wo) {
            if ($wo->actual_duration && $wo->actual_duration > 0) {
                $totalMinutes += $wo->actual_duration;
                $count++;
            } elseif ($wo->started_at && $wo->completed_at) {
                $duration = Carbon::parse($wo->started_at)->diffInMinutes(Carbon::parse($wo->completed_at));
                $totalMinutes += $duration;
                $count++;
            } elseif ($wo->created_at && $wo->completed_at) {
                $duration = Carbon::parse($wo->created_at)->diffInMinutes(Carbon::parse($wo->completed_at));
                $totalMinutes += $duration;
                $count++;
            }
        }

        if ($count == 0) {
            return 0;
        }

        $mttrHours = ($totalMinutes / $count) / 60;

        return round($mttrHours, 2);
    }

    /**
     * Calcul du MTBF - Mean Time Between Failures (en jours)
     */
    protected function calculateMTBF(int $siteId, Carbon $startDate, Carbon $endDate): float
    {
        $failureCount = WorkOrder::where('site_id', $siteId)
            ->where('type', 'corrective')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        if ($failureCount <= 1) {
            return 0;
        }

        $totalDays = $startDate->diffInDays($endDate);

        $equipmentCount = Equipment::where('site_id', $siteId)
            ->whereIn('status', ['operational', 'under_maintenance'])
            ->count();

        if ($equipmentCount == 0) {
            return 0;
        }

        $mtbf = ($totalDays * $equipmentCount) / $failureCount;

        return round($mtbf, 1);
    }

    /**
     * Calcul du taux de disponibilité
     */
    protected function calculateAvailability(int $siteId, Carbon $startDate, Carbon $endDate): float
    {
        $totalEquipments = Equipment::where('site_id', $siteId)
            ->whereNotIn('status', ['retired', 'disposed'])
            ->count();

        if ($totalEquipments == 0) {
            return 100;
        }

        $operationalCount = Equipment::where('site_id', $siteId)
            ->where('status', 'operational')
            ->count();

        $instantAvailability = ($operationalCount / $totalEquipments) * 100;

        $totalHours = $startDate->diffInHours($endDate);

        if ($totalHours == 0) {
            return round($instantAvailability, 2);
        }

        $downtimeFromWO = WorkOrder::where('site_id', $siteId)
            ->where('type', 'corrective')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->whereNotNull('actual_duration')
            ->sum('actual_duration');

        $ongoingDowntime = WorkOrder::where('site_id', $siteId)
            ->where('type', 'corrective')
            ->whereIn('status', ['in_progress', 'on_hold'])
            ->where('created_at', '<=', $endDate)
            ->get()
            ->sum(function ($wo) use ($endDate) {
                $start = Carbon::parse($wo->started_at ?? $wo->created_at);
                return $start->diffInMinutes($endDate);
            });

        $totalDowntimeMinutes = $downtimeFromWO + $ongoingDowntime;
        $totalDowntimeHours = $totalDowntimeMinutes / 60;

        $totalAvailableHours = $totalHours * $totalEquipments;
        $timeBasedAvailability = (($totalAvailableHours - $totalDowntimeHours) / $totalAvailableHours) * 100;

        $availability = min($instantAvailability, $timeBasedAvailability);

        return round(max(0, min(100, $availability)), 2);
    }

    /**
     * Évolution des OT par mois
     */
    public function workOrderTrend(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $months = $request->months ?? 12;

        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $created = WorkOrder::where('site_id', $siteId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();

            $completed = WorkOrder::where('site_id', $siteId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->count();

            $corrective = WorkOrder::where('site_id', $siteId)
                ->where('type', 'corrective')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();

            $preventive = WorkOrder::where('site_id', $siteId)
                ->where('type', 'preventive')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();

            $data[] = [
                'month' => $date->format('M Y'),
                'month_short' => $date->format('M'),
                'created' => $created,
                'completed' => $completed,
                'corrective' => $corrective,
                'preventive' => $preventive,
            ];
        }

        return response()->json($data);
    }

    /**
     * Répartition par type
     */
    public function workOrdersByType(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id ?? 1;

        $query = WorkOrder::where('site_id', $siteId);

        if ($request->start_date && $request->end_date) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();  // <-- FIX
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $data = $query->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn($item) => [$item->type => $item->count])
            ->toArray();

        $labels = [
            'corrective' => 'Corrective',
            'preventive' => 'Préventive',
            'improvement' => 'Amélioration',
            'inspection' => 'Inspection',
        ];

        $result = [];
        foreach ($labels as $key => $label) {
            $result[] = [
                'type' => $key,
                'label' => $label,
                'count' => $data[$key] ?? 0,
            ];
        }

        return response()->json($result);
    }




    /**
     * Répartition par statut
     */
    public function workOrdersByStatus(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;

        $data = WorkOrder::where('site_id', $siteId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count])
            ->toArray();

        $labels = [
            'pending' => 'En attente',
            'approved' => 'Approuvé',
            'in_progress' => 'En cours',
            'on_hold' => 'En pause',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
        ];

        $result = [];
        foreach ($labels as $key => $label) {
            if (isset($data[$key]) && $data[$key] > 0) {
                $result[] = [
                    'status' => $key,
                    'label' => $label,
                    'count' => $data[$key],
                ];
            }
        }

        return response()->json($result);
    }

    /**
     * Top équipements par nombre d'interventions
     */
    public function topEquipmentsByFailures(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id ?? 1;
        $limit = $request->limit ?? 10;
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfYear();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

        $data = WorkOrder::where('work_orders.site_id', $siteId)
            ->where('work_orders.type', 'corrective')
            ->whereBetween('work_orders.created_at', [$startDate, $endDate])
            ->join('equipments', 'work_orders.equipment_id', '=', 'equipments.id')
            ->selectRaw('equipments.id, equipments.code, equipments.name, count(*) as failure_count')
            ->groupBy('equipments.id', 'equipments.code', 'equipments.name')
            ->orderByDesc('failure_count')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }


    /**
     * Coûts par équipement
     */
    public function costsByEquipment(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $limit = $request->limit ?? 10;
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfYear();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

        $data = WorkOrder::where('work_orders.site_id', $siteId)
            ->where('work_orders.status', 'completed')
            ->whereBetween('work_orders.completed_at', [$startDate, $endDate])
            ->join('equipments', 'work_orders.equipment_id', '=', 'equipments.id')
            ->selectRaw('equipments.id, equipments.code, equipments.name, 
                         SUM(work_orders.total_cost) as total_cost,
                         SUM(work_orders.labor_cost) as labor_cost,
                         SUM(work_orders.parts_cost) as parts_cost,
                         COUNT(*) as wo_count')
            ->groupBy('equipments.id', 'equipments.code', 'equipments.name')
            ->orderByDesc('total_cost')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    /**
     * Évolution des coûts par mois
     */
    public function costsTrend(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $months = $request->months ?? 12;

        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $costs = WorkOrder::where('site_id', $siteId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->selectRaw('SUM(total_cost) as total, SUM(labor_cost) as labor, SUM(parts_cost) as parts')
                ->first();

            $data[] = [
                'month' => $date->format('M Y'),
                'month_short' => $date->format('M'),
                'total' => round($costs->total ?? 0, 2),
                'labor' => round($costs->labor ?? 0, 2),
                'parts' => round($costs->parts ?? 0, 2),
            ];
        }

        return response()->json($data);
    }

    /**
     * Performance des techniciens
     */
    public function technicianPerformance(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id ?? 1;
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

        $workOrders = WorkOrder::where('work_orders.site_id', $siteId)
            ->where('work_orders.status', 'completed')
            ->whereBetween('work_orders.completed_at', [$startDate, $endDate])
            ->whereNotNull('work_orders.assigned_to')
            ->join('users', 'work_orders.assigned_to', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'work_orders.actual_start',
                'work_orders.actual_end',
                'work_orders.actual_duration',
                'work_orders.total_cost'
            )
            ->get();

        $data = $workOrders->groupBy('id')->map(function ($items, $userId) {
            $first = $items->first();
            $completedCount = $items->count();

            $totalMinutes = 0;
            $countWithDuration = 0;

            foreach ($items as $wo) {
                if ($wo->actual_duration && $wo->actual_duration > 0) {
                    $totalMinutes += $wo->actual_duration;
                    $countWithDuration++;
                } elseif ($wo->actual_start && $wo->actual_end) {
                    $duration = Carbon::parse($wo->actual_start)->diffInMinutes(Carbon::parse($wo->actual_end));
                    $totalMinutes += $duration;
                    $countWithDuration++;
                }
            }

            $avgDurationHours = $countWithDuration > 0
                ? round(($totalMinutes / $countWithDuration) / 60, 2)
                : 0;

            $totalCost = $items->sum(fn($wo) => $wo->total_cost ?? 0);

            return [
                'id' => $userId,
                'name' => $first->name,
                'completed_count' => $completedCount,
                'avg_duration_hours' => $avgDurationHours,
                'total_cost' => round($totalCost, 2),
            ];
        })->values();

        return response()->json($data);
    }



    /**
     * Stock critique
     */
    public function criticalStock(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;

        $data = Part::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock <= minimum_stock')
            ->orderByRaw('quantity_in_stock - minimum_stock')
            ->get(['id', 'code', 'name', 'quantity_in_stock', 'minimum_stock', 'unit']);

        return response()->json($data);
    }

    /**
     * Consommation de pièces
     */
    public function partsConsumption(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id ?? 1;
        $limit = $request->limit ?? 10;
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

        $data = StockMovement::where('stock_movements.site_id', $siteId)
            ->where('stock_movements.type', 'out')
            ->whereBetween('stock_movements.created_at', [$startDate, $endDate])
            ->join('parts', 'stock_movements.part_id', '=', 'parts.id')
            ->selectRaw('parts.id, parts.code, parts.name, parts.unit,
                     SUM(ABS(stock_movements.quantity)) as total_used,
                     SUM(ABS(stock_movements.quantity) * stock_movements.unit_price) as total_cost')
            ->groupBy('parts.id', 'parts.code', 'parts.name', 'parts.unit')
            ->orderByDesc('total_used')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }


    /**
     * Export des données en CSV
     */
    public function exportWorkOrders(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $siteId = $request->user()->current_site_id;
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfYear();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

        $workOrders = WorkOrder::where('site_id', $siteId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['equipment', 'assignedTo'])
            ->orderBy('created_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="interventions_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($workOrders) {
            $handle = fopen('php://output', 'w');

            // BOM pour Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // En-têtes
            fputcsv($handle, [
                'Code',
                'Titre',
                'Type',
                'Priorité',
                'Statut',
                'Équipement',
                'Assigné à',
                'Date création',
                'Date fin',
                'Durée (min)',
                'Coût MO',
                'Coût pièces',
                'Coût total',
            ], ';');

            // Données
            foreach ($workOrders as $wo) {
                fputcsv($handle, [
                    $wo->code,
                    $wo->title,
                    $wo->type,
                    $wo->priority,
                    $wo->status,
                    $wo->equipment?->name,
                    $wo->assignedTo?->name,
                    $wo->created_at?->format('d/m/Y H:i'),
                    $wo->completed_at?->format('d/m/Y H:i'),
                    $wo->actual_duration,
                    $wo->labor_cost,
                    $wo->parts_cost,
                    $wo->total_cost,
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
