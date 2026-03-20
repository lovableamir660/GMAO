<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Equipment;
use App\Models\InterventionRequest;
use App\Models\Notification;
use App\Models\Part;
use App\Models\PreventiveMaintenance;
use App\Models\Truck;
use App\Models\TruckDriverHistory;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Données complètes du dashboard (filtrées par permissions)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $siteId = $user->current_site_id;
        $userId = $user->id;

        $cacheKey = "dashboard_{$siteId}_{$userId}";

        $data = Cache::remember($cacheKey, 60, function () use ($siteId, $user) {
            $result = [];

            // KPIs filtrés par permission
            $result['kpis'] = $this->getFilteredKPIs($siteId, $user);

            // Widgets existants
            $result['work_orders'] = $user->can('workorder:view_any')
                ? $this->getWorkOrdersStats($siteId) : [];

            $result['equipment_status'] = $user->can('equipment:view_any')
                ? $this->getEquipmentStatus($siteId) : [];

            $result['upcoming_maintenance'] = ($user->can('workorder:view_any') || $user->can('preventive:view_any'))
                ? $this->getUpcomingMaintenance($siteId) : [];

            $result['team_performance'] = $user->can('workorder:view_any')
                ? $this->getTeamPerformance($siteId) : [];

            $result['monthly_trend'] = $user->can('workorder:view_any')
                ? $this->getMonthlyTrend($siteId) : [];

            // ✅ NOUVEAUX WIDGETS
            $result['trucks_stats'] = $user->can('truck:view_any')
                ? $this->getTrucksStats($siteId) : [];

            $result['drivers_stats'] = $user->can('driver:view_any')
                ? $this->getDriversStats($siteId) : [];

            $result['critical_stock_items'] = $user->can('part:view_any')
                ? $this->getCriticalStockItems($siteId) : [];

            $result['preventive_stats'] = $user->can('preventive:view_any')
                ? $this->getPreventiveStats($siteId) : [];

            $result['pending_interventions'] = ($user->can('intervention_request:view_any') || $user->can('intervention_request:view_own'))
                ? $this->getPendingInterventions($siteId, $user) : [];

            $result['intervention_stats'] = ($user->can('intervention_request:view_any') || $user->can('intervention_request:view_own'))
                ? $this->getInterventionStats($siteId) : [];

            $result['expiring_habilitations'] = $user->can('driver:view_any')
                ? $this->getExpiringHabilitations($siteId) : [];

            $result['costs_summary'] = $user->can('workorder:view_any')
                ? $this->getCostsSummary($siteId) : [];

            return $result;
        });

        // Données temps réel (non cachées)
        $data['recent_activities'] = $this->getFilteredActivities($siteId, $user);
        $data['urgent_items'] = $this->getFilteredUrgentItems($siteId, $user);
        $data['notifications'] = $this->getRecentNotifications($siteId, $userId);

        return response()->json($data);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        Cache::forget("dashboard_{$user->current_site_id}_{$user->id}");
        return $this->index($request);
    }

    // =========================================================================
    //  KPIs
    // =========================================================================

    protected function getFilteredKPIs(int $siteId, $user): array
    {
        $allKpis = $this->getKPIs($siteId);

        $permissionMap = [
            'pending_wo' => 'workorder:view_any',
            'completed_wo' => 'workorder:view_any',
            'availability' => 'equipment:view_any',
            'pending_di' => 'intervention_request:view_any',
            'critical_stock' => 'part:view_any',
            'equipments' => 'equipment:view_any',
            'trucks' => 'truck:view_any',
            'drivers' => 'driver:view_any',
            'preventive_plans' => 'preventive:view_any',
        ];

        return array_values(array_filter($allKpis, function ($kpi) use ($user, $permissionMap) {
            $requiredPerm = $permissionMap[$kpi['key']] ?? null;
            return !$requiredPerm || $user->can($requiredPerm);
        }));
    }

    protected function getKPIs(int $siteId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Work Orders
        $woCompletedThisMonth = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfMonth, $now])
            ->count();

        $woCompletedLastMonth = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        $pendingWO = WorkOrder::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'approved', 'assigned', 'in_progress', 'on_hold'])
            ->count();

        // Equipment
        $totalEquipments = Equipment::where('site_id', $siteId)
            ->whereNotIn('status', ['retired', 'disposed'])
            ->count();

        $operationalEquipments = Equipment::where('site_id', $siteId)
            ->where('status', 'operational')
            ->count();

        // DI
        $pendingDI = InterventionRequest::where('site_id', $siteId)
            ->where('status', 'pending')
            ->count();

        // Stock critique
        $criticalStock = Part::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock <= minimum_stock')
            ->count();

        // ✅ Trucks — PAS de is_active, on utilise status + SoftDeletes
        $totalTrucks = Truck::where('site_id', $siteId)
            ->whereNotIn('status', ['out_of_service'])
            ->count();

        $operationalTrucks = Truck::where('site_id', $siteId)
            ->where('status', 'available')
            ->count();

        // ✅ Drivers — PAS de is_active, on utilise status (active/inactive/suspended)
        $totalDrivers = Driver::where('site_id', $siteId)
            ->where('status', 'active')
            ->count();

        // Preventive — is_active existe bien sur preventive_maintenances
        $overduePreventive = PreventiveMaintenance::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', '!=', 'mileage')
            ->where('next_execution_date', '<', Carbon::today())
            ->count();

        $completedTrend = $woCompletedLastMonth > 0
            ? round((($woCompletedThisMonth - $woCompletedLastMonth) / $woCompletedLastMonth) * 100, 1)
            : 0;

        return [
            [
                'key' => 'pending_wo',
                'label' => 'OT en cours',
                'value' => $pendingWO,
                'icon' => '🔧',
                'color' => 'blue',
                'trend' => null,
                'suffix' => null,
                'link' => '/work-orders',
            ],
            [
                'key' => 'completed_wo',
                'label' => 'OT terminés (mois)',
                'value' => $woCompletedThisMonth,
                'icon' => '✅',
                'color' => 'green',
                'trend' => $completedTrend,
                'suffix' => null,
                'link' => '/work-orders',
            ],
            [
                'key' => 'availability',
                'label' => 'Dispo. équipements',
                'value' => $totalEquipments > 0 ? round(($operationalEquipments / $totalEquipments) * 100, 1) : 100,
                'suffix' => '%',
                'icon' => '⚡',
                'color' => 'purple',
                'trend' => null,
                'link' => '/equipments',
            ],
            [
                'key' => 'trucks',
                'label' => 'Camions dispo.',
                'value' => $operationalTrucks,
                'suffix' => '/' . $totalTrucks,
                'icon' => '🚚',
                'color' => 'teal',
                'trend' => null,
                'link' => '/trucks',
            ],
            [
                'key' => 'drivers',
                'label' => 'Chauffeurs actifs',
                'value' => $totalDrivers,
                'icon' => '👷',
                'color' => 'indigo',
                'trend' => null,
                'suffix' => null,
                'link' => '/drivers',
            ],
            [
                'key' => 'pending_di',
                'label' => 'DI en attente',
                'value' => $pendingDI,
                'icon' => '📋',
                'color' => 'orange',
                'trend' => null,
                'suffix' => null,
                'link' => '/intervention-requests',
            ],
            [
                'key' => 'critical_stock',
                'label' => 'Stock critique',
                'value' => $criticalStock,
                'icon' => '⚠️',
                'color' => $criticalStock > 0 ? 'red' : 'green',
                'trend' => null,
                'suffix' => null,
                'link' => '/parts',
            ],
            [
                'key' => 'preventive_plans',
                'label' => 'MP en retard',
                'value' => $overduePreventive,
                'icon' => '📅',
                'color' => $overduePreventive > 0 ? 'red' : 'green',
                'trend' => null,
                'suffix' => null,
                'link' => '/preventive-maintenance',
            ],
            [
                'key' => 'equipments',
                'label' => 'Équipements actifs',
                'value' => $operationalEquipments,
                'suffix' => '/' . $totalEquipments,
                'icon' => '⚙️',
                'color' => 'cyan',
                'trend' => null,
                'link' => '/equipments',
            ],
        ];
    }

    // =========================================================================
    //  WORK ORDERS
    // =========================================================================

    protected function getWorkOrdersStats(int $siteId): array
    {
        $stats = WorkOrder::where('site_id', $siteId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count])
            ->toArray();

        $labels = [
            'pending' => ['label' => 'En attente', 'color' => '#f39c12'],
            'approved' => ['label' => 'Approuvé', 'color' => '#3498db'],
            'assigned' => ['label' => 'Assigné', 'color' => '#8e44ad'],
            'in_progress' => ['label' => 'En cours', 'color' => '#9b59b6'],
            'on_hold' => ['label' => 'En pause', 'color' => '#95a5a6'],
            'completed' => ['label' => 'Terminé', 'color' => '#27ae60'],
            'cancelled' => ['label' => 'Annulé', 'color' => '#e74c3c'],
        ];

        $result = [];
        foreach ($labels as $key => $meta) {
            $result[] = [
                'status' => $key,
                'label' => $meta['label'],
                'count' => $stats[$key] ?? 0,
                'color' => $meta['color'],
            ];
        }

        return $result;
    }

    // =========================================================================
    //  EQUIPMENT
    // =========================================================================

    protected function getEquipmentStatus(int $siteId): array
    {
        $stats = Equipment::where('site_id', $siteId)
            ->whereNotIn('status', ['retired', 'disposed'])
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count])
            ->toArray();

        $labels = [
            'operational' => ['label' => 'Opérationnel', 'color' => '#27ae60', 'icon' => '✅'],
            'under_maintenance' => ['label' => 'En maintenance', 'color' => '#3498db', 'icon' => '🔧'],
            'stopped' => ['label' => 'Arrêté', 'color' => '#f39c12', 'icon' => '⏸️'],
            'broken' => ['label' => 'En panne', 'color' => '#e74c3c', 'icon' => '❌'],
        ];

        $result = [];
        foreach ($labels as $key => $meta) {
            if (isset($stats[$key]) && $stats[$key] > 0) {
                $result[] = [
                    'status' => $key,
                    'label' => $meta['label'],
                    'count' => $stats[$key],
                    'color' => $meta['color'],
                    'icon' => $meta['icon'],
                ];
            }
        }

        return $result;
    }

    // =========================================================================
    //  🚚 TRUCKS (NOUVEAU)
    // =========================================================================

    protected function getTrucksStats(int $siteId): array
    {
        // ✅ Pas de is_active — SoftDeletes + status
        $stats = Truck::where('site_id', $siteId)
            ->whereNotIn('status', ['out_of_service'])
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count])
            ->toArray();

        $labels = [
            'available' => ['label' => 'Disponible', 'color' => '#27ae60', 'icon' => '✅'],
            'in_use' => ['label' => 'En service', 'color' => '#3498db', 'icon' => '🚛'],
            'maintenance' => ['label' => 'En maintenance', 'color' => '#f39c12', 'icon' => '🔧'],
            'broken' => ['label' => 'En panne', 'color' => '#e74c3c', 'icon' => '❌'],
        ];

        $byStatus = [];
        foreach ($labels as $key => $meta) {
            $count = $stats[$key] ?? 0;
            if ($count > 0) {
                $byStatus[] = [
                    'status' => $key,
                    'label' => $meta['label'],
                    'count' => $count,
                    'color' => $meta['color'],
                    'icon' => $meta['icon'],
                ];
            }
        }

        // Camions avec kilométrage proche d'une maintenance
        $trucksNeedingMaintenance = 0;
        try {
            $trucksNeedingMaintenance = Truck::where('site_id', $siteId)
                ->whereNotIn('status', ['out_of_service'])
                ->whereHas('preventiveMaintenances', function ($q) {
                    $q->where('is_active', true)
                        ->where('frequency_type', 'mileage')
                        ->whereNotNull('next_mileage');
                })
                ->with([
                    'preventiveMaintenances' => function ($q) {
                        $q->where('is_active', true)
                            ->where('frequency_type', 'mileage')
                            ->whereNotNull('next_mileage');
                    }
                ])
                ->get()
                ->filter(function ($truck) {
                    return $truck->preventiveMaintenances->contains(function ($pm) use ($truck) {
                        $remaining = $pm->next_mileage - $truck->mileage;
                        return $remaining <= ($pm->advance_mileage ?? 500) && $remaining > 0;
                    });
                })
                ->count();
        } catch (\Exception $e) {
            // Silencieux si la relation n'existe pas encore
        }

        return [
            'total' => array_sum($stats),
            'by_status' => $byStatus,
            'needing_maintenance' => $trucksNeedingMaintenance,
        ];
    }

    // =========================================================================
    //  👷 DRIVERS (NOUVEAU)
    // =========================================================================

    protected function getDriversStats(int $siteId): array
    {
        // ✅ drivers utilise status = 'active' et non is_active
        $total = Driver::where('site_id', $siteId)
            ->where('status', 'active')
            ->count();

        $assigned = 0;
        try {
            $assigned = TruckDriverHistory::whereHas('truck', fn($q) => $q->where('site_id', $siteId))
                ->whereNull('ended_at')
                ->count();
        } catch (\Exception $e) {
            // Silencieux
        }

        $unassigned = max(0, $total - $assigned);

        return [
            'total' => $total,
            'assigned' => $assigned,
            'unassigned' => $unassigned,
        ];
    }

    // =========================================================================
    //  📦 STOCK CRITIQUE (NOUVEAU)
    // =========================================================================

    protected function getCriticalStockItems(int $siteId): array
    {
        return Part::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock <= minimum_stock')
            ->orderByRaw('quantity_in_stock - minimum_stock ASC')
            ->limit(8)
            ->get()
            ->map(fn($part) => [
                'id' => $part->id,
                'code' => $part->code,
                'name' => $part->name,
                'quantity' => $part->quantity_in_stock,
                'minimum' => $part->minimum_stock,
                'unit' => $part->unit,
                'deficit' => $part->minimum_stock - $part->quantity_in_stock,
                'is_empty' => $part->quantity_in_stock <= 0,
            ])
            ->toArray();
    }

    // =========================================================================
    //  🔄 PREVENTIVE MAINTENANCE (NOUVEAU)
    // =========================================================================

    protected function getPreventiveStats(int $siteId): array
    {
        $total = PreventiveMaintenance::where('site_id', $siteId)
            ->where('is_active', true)
            ->count();

        $overdue = PreventiveMaintenance::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', '!=', 'mileage')
            ->where('next_execution_date', '<', Carbon::today())
            ->count();

        $dueSoon = PreventiveMaintenance::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', '!=', 'mileage')
            ->whereBetween('next_execution_date', [Carbon::today(), Carbon::today()->addDays(7)])
            ->count();

        $overduePlans = PreventiveMaintenance::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', '!=', 'mileage')
            ->where('next_execution_date', '<', Carbon::today())
            ->with(['equipment:id,name,code', 'truck:id,registration_number,code', 'assignedTo:id,name'])
            ->orderBy('next_execution_date')
            ->limit(5)
            ->get()
            ->map(fn($pm) => [
                'id' => $pm->id,
                'code' => $pm->code,
                'name' => $pm->name,
                'asset_type' => $pm->asset_type ?? 'equipment',
                'asset_name' => $pm->asset_type === 'truck'
                    ? ($pm->truck?->registration_number ?? '-')
                    : ($pm->equipment?->name ?? '-'),
                'due_date' => $pm->next_execution_date?->format('d/m/Y'),
                'days_overdue' => $pm->next_execution_date
                    ? Carbon::today()->diffInDays($pm->next_execution_date)
                    : 0,
                'priority' => $pm->priority ?? 'medium',
                'assigned_to' => $pm->assignedTo?->name,
            ])
            ->toArray();

        $byAssetType = [
            'equipment' => PreventiveMaintenance::where('site_id', $siteId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('asset_type', 'equipment')->orWhereNull('asset_type');
                })->count(),
            'truck' => PreventiveMaintenance::where('site_id', $siteId)
                ->where('is_active', true)
                ->where('asset_type', 'truck')
                ->count(),
        ];

        return [
            'total' => $total,
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
            'on_track' => max(0, $total - $overdue - $dueSoon),
            'overdue_plans' => $overduePlans,
            'by_asset_type' => $byAssetType,
        ];
    }

    // =========================================================================
    //  📋 INTERVENTION REQUESTS (NOUVEAU)
    // =========================================================================

    protected function getPendingInterventions(int $siteId, $user): array
    {
        $query = InterventionRequest::where('site_id', $siteId)
            ->where('status', 'pending')  // ✅ 'pending' au lieu de 'submitted','under_review'
            ->with(['requestedBy:id,name', 'equipment:id,name,code', 'truck:id,registration_number,code'])
            ->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')")  // ✅ 'urgency' au lieu de 'priority'
            ->orderByDesc('created_at');

        if (!$user->can('intervention_request:view_any')) {
            $query->where('requested_by', $user->id);
        }

        return $query->limit(6)->get()->map(fn($di) => [
            'id' => $di->id,
            'code' => $di->code,
            'title' => $di->title,
            'status' => $di->status,
            'urgency' => $di->urgency,         // ✅ 'urgency' au lieu de 'priority'
            'machine_stopped' => $di->machine_stopped,
            'asset_type' => $di->asset_type ?? 'equipment',
            'asset_name' => ($di->asset_type ?? '') === 'truck'
                ? $di->truck?->registration_number
                : $di->equipment?->name,
            'requested_by' => $di->requestedBy?->name,
            'created_at' => $di->created_at->diffForHumans(),
        ])->toArray();
    }


    protected function getInterventionStats(int $siteId): array
    {
        $stats = InterventionRequest::where('site_id', $siteId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count])
            ->toArray();

        // ✅ Statuts réels de la migration : pending, approved, rejected, converted
        return [
            'pending' => $stats['pending'] ?? 0,
            'approved' => $stats['approved'] ?? 0,
            'rejected' => $stats['rejected'] ?? 0,
            'converted' => $stats['converted'] ?? 0,
            'total' => array_sum($stats),
        ];
    }


    // =========================================================================
    //  📜 HABILITATIONS EXPIRANTES (NOUVEAU)
    // =========================================================================

    protected function getExpiringHabilitations(int $siteId): array
    {
        try {
            return DB::table('driver_habilitation')
                ->join('drivers', 'driver_habilitation.driver_id', '=', 'drivers.id')
                ->join('habilitations', 'driver_habilitation.habilitation_id', '=', 'habilitations.id')
                ->where('drivers.site_id', $siteId)
                ->where('drivers.status', 'active') // ✅ status au lieu de is_active
                ->whereNotNull('driver_habilitation.expires_at')
                ->where('driver_habilitation.expires_at', '<=', Carbon::today()->addDays(30))
                ->where('driver_habilitation.expires_at', '>', Carbon::today())
                ->select([
                    'drivers.id as driver_id',
                    'drivers.first_name',
                    'drivers.last_name',
                    'habilitations.name as habilitation_name',
                    'habilitations.category',
                    'driver_habilitation.expires_at',
                ])
                ->orderBy('driver_habilitation.expires_at')
                ->limit(6)
                ->get()
                ->map(fn($item) => [
                    'driver_id' => $item->driver_id,
                    'driver_name' => "{$item->first_name} {$item->last_name}",
                    'habilitation' => $item->habilitation_name,
                    'category' => $item->category,
                    'expires_at' => Carbon::parse($item->expires_at)->format('d/m/Y'),
                    'days_remaining' => Carbon::today()->diffInDays(Carbon::parse($item->expires_at)),
                ])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    //  💰 COÛTS (NOUVEAU)
    // =========================================================================

    protected function getCostsSummary(int $siteId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $costThisMonth = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfMonth, $now])
            ->sum('total_cost');

        $costLastMonth = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_cost');

        $laborCost = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfMonth, $now])
            ->sum('labor_cost');

        $partsCost = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfMonth, $now])
            ->sum('parts_cost');

        $trend = $costLastMonth > 0
            ? round((($costThisMonth - $costLastMonth) / $costLastMonth) * 100, 1)
            : 0;

        return [
            'total_this_month' => round($costThisMonth, 2),
            'total_last_month' => round($costLastMonth, 2),
            'labor_cost' => round($laborCost, 2),
            'parts_cost' => round($partsCost, 2),
            'trend' => $trend,
        ];
    }

    // =========================================================================
    //  EXISTANTS (mis à jour avec support trucks)
    // =========================================================================

    protected function getUpcomingMaintenance(int $siteId): array
    {
        $preventive = PreventiveMaintenance::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereBetween('next_execution_date', [now(), now()->addDays(7)])
            ->with(['equipment:id,name,code', 'truck:id,registration_number,code'])
            ->orderBy('next_execution_date')
            ->limit(5)
            ->get()
            ->map(fn($pm) => [
                'id' => $pm->id,
                'title' => $pm->name,
                'asset_type' => $pm->asset_type ?? 'equipment',
                'equipment' => $pm->asset_type === 'truck'
                    ? $pm->truck?->registration_number
                    : $pm->equipment?->name,
                'equipment_code' => $pm->asset_type === 'truck'
                    ? $pm->truck?->code
                    : $pm->equipment?->code,
                'due_date' => $pm->next_execution_date?->format('Y-m-d'),
                'due_date_formatted' => $pm->next_execution_date?->format('d/m'),
                'days_until' => $pm->next_execution_date
                    ? now()->diffInDays($pm->next_execution_date, false)
                    : 0,
                'type' => 'preventive',
            ]);

        $scheduled = WorkOrder::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'approved', 'assigned'])
            ->whereNotNull('scheduled_start')
            ->whereBetween('scheduled_start', [now(), now()->addDays(7)])
            ->with(['equipment:id,name,code', 'truck:id,registration_number,code'])
            ->orderBy('scheduled_start')
            ->limit(5)
            ->get()
            ->map(fn($wo) => [
                'id' => $wo->id,
                'title' => $wo->title,
                'code' => $wo->code,
                'asset_type' => $wo->asset_type ?? 'equipment',
                'equipment' => ($wo->asset_type ?? '') === 'truck'
                    ? $wo->truck?->registration_number
                    : $wo->equipment?->name,
                'equipment_code' => ($wo->asset_type ?? '') === 'truck'
                    ? $wo->truck?->code
                    : $wo->equipment?->code,
                'due_date' => $wo->scheduled_start->format('Y-m-d'),
                'due_date_formatted' => $wo->scheduled_start->format('d/m'),
                'days_until' => now()->diffInDays($wo->scheduled_start, false),
                'type' => 'work_order',
                'priority' => $wo->priority,
            ]);

        return $preventive->concat($scheduled)
            ->sortBy('due_date')
            ->values()
            ->take(8)
            ->toArray();
    }

    protected function getTeamPerformance(int $siteId): array
    {
        return WorkOrder::where('work_orders.site_id', $siteId)
            ->where('work_orders.status', 'completed')
            ->whereBetween('work_orders.completed_at', [now()->startOfMonth(), now()])
            ->whereNotNull('work_orders.assigned_to')
            ->join('users', 'work_orders.assigned_to', '=', 'users.id')
            ->selectRaw('users.id, users.name, COUNT(*) as completed_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'initials' => $this->getInitials($item->name),
                'completed' => $item->completed_count,
            ])
            ->toArray();
    }

    protected function getMonthlyTrend(int $siteId): array
    {
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            $data[] = [
                'month' => $date->format('M'),
                'month_full' => $date->translatedFormat('F Y'),
                'created' => WorkOrder::where('site_id', $siteId)
                    ->whereBetween('created_at', [$start, $end])->count(),
                'completed' => WorkOrder::where('site_id', $siteId)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end])->count(),
            ];
        }
        return $data;
    }

    // =========================================================================
    //  ACTIVITÉS RÉCENTES (mis à jour)
    // =========================================================================

    protected function getFilteredActivities(int $siteId, $user): array
    {
        $activities = [];

        if ($user->can('workorder:view_any')) {
            $recentWO = WorkOrder::where('site_id', $siteId)
                ->with(['assignedTo:id,name', 'equipment:id,name', 'truck:id,registration_number'])
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get();

            foreach ($recentWO as $wo) {
                $activities[] = [
                    'type' => 'work_order',
                    'icon' => $this->getWOStatusIcon($wo->status),
                    'title' => $wo->code,
                    'description' => $this->getWOActivityDescription($wo),
                    'time' => $wo->updated_at->diffForHumans(),
                    'timestamp' => $wo->updated_at,
                    'link' => "/work-orders/{$wo->id}",
                    'color' => $this->getWOStatusColor($wo->status),
                ];
            }
        }

        if ($user->can('intervention_request:view_any')) {
            $recentDI = InterventionRequest::where('site_id', $siteId)
                ->with(['requestedBy:id,name', 'equipment:id,name'])
                ->orderByDesc('updated_at')
                ->limit(3)
                ->get();

            foreach ($recentDI as $di) {
                $activities[] = [
                    'type' => 'intervention_request',
                    'icon' => '📋',
                    'title' => $di->code,
                    'description' => "Demande: {$di->title}",
                    'time' => $di->updated_at->diffForHumans(),
                    'timestamp' => $di->updated_at,
                    'link' => "/intervention-requests/{$di->id}",
                    'color' => 'orange',
                ];
            }
        }

        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        return array_slice($activities, 0, 8);
    }

    // =========================================================================
    //  ÉLÉMENTS URGENTS (mis à jour avec trucks)
    // =========================================================================

    protected function getFilteredUrgentItems(int $siteId, $user): array
    {
        $items = [];

        if ($user->can('workorder:view_any')) {
            $overdueWO = WorkOrder::where('site_id', $siteId)
                ->whereIn('status', ['pending', 'approved', 'assigned', 'in_progress'])
                ->whereNotNull('scheduled_end')
                ->where('scheduled_end', '<', now())
                ->with(['equipment:id,name', 'truck:id,registration_number', 'assignedTo:id,name'])
                ->orderBy('scheduled_end')
                ->limit(5)
                ->get();

            foreach ($overdueWO as $wo) {
                $daysLate = Carbon::parse($wo->scheduled_end)->diffInDays(now());
                $items[] = [
                    'type' => 'overdue_wo',
                    'icon' => '🚨',
                    'title' => $wo->code,
                    'subtitle' => ($wo->asset_type ?? '') === 'truck'
                        ? $wo->truck?->registration_number
                        : $wo->equipment?->name,
                    'description' => "En retard de {$daysLate} jour(s)",
                    'priority' => $wo->priority,
                    'link' => "/work-orders/{$wo->id}",
                    'urgency' => 'high',
                ];
            }

            $urgentUnassigned = WorkOrder::where('site_id', $siteId)
                ->whereIn('status', ['pending', 'approved'])
                ->whereIn('priority', ['urgent', 'high'])
                ->whereNull('assigned_to')
                ->with(['equipment:id,name', 'truck:id,registration_number'])
                ->limit(3)
                ->get();

            foreach ($urgentUnassigned as $wo) {
                $items[] = [
                    'type' => 'unassigned_wo',
                    'icon' => '👤',
                    'title' => $wo->code,
                    'subtitle' => ($wo->asset_type ?? '') === 'truck'
                        ? $wo->truck?->registration_number
                        : $wo->equipment?->name,
                    'description' => 'Non assigné - Priorité ' . $wo->priority,
                    'priority' => $wo->priority,
                    'link' => "/work-orders/{$wo->id}",
                    'urgency' => 'medium',
                ];
            }
        }

        if ($user->can('equipment:view_any')) {
            $brokenEquipments = Equipment::where('site_id', $siteId)
                ->whereIn('status', ['broken', 'stopped'])
                ->limit(3)
                ->get();

            foreach ($brokenEquipments as $eq) {
                $items[] = [
                    'type' => 'equipment_down',
                    'icon' => '⚙️',
                    'title' => $eq->name,
                    'subtitle' => $eq->code,
                    'description' => $eq->status === 'broken' ? 'En panne' : 'Arrêté',
                    'link' => "/equipments/{$eq->id}",
                    'urgency' => $eq->status === 'broken' ? 'high' : 'medium',
                ];
            }
        }

        // ✅ Camions en panne — PAS de is_active
        if ($user->can('truck:view_any')) {
            $brokenTrucks = Truck::where('site_id', $siteId)
                ->where('status', 'broken')
                ->limit(3)
                ->get();

            foreach ($brokenTrucks as $truck) {
                $items[] = [
                    'type' => 'truck_down',
                    'icon' => '🚚',
                    'title' => $truck->registration_number,
                    'subtitle' => $truck->code,
                    'description' => 'Camion en panne',
                    'link' => "/trucks/{$truck->id}",
                    'urgency' => 'high',
                ];
            }
        }

        return array_slice($items, 0, 8);
    }

    // =========================================================================
    //  NOTIFICATIONS
    // =========================================================================

    protected function getRecentNotifications(int $siteId, int $userId): array
    {
        return Notification::where('site_id', $siteId)
            ->where(fn($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($n) => [
                'id' => $n->id,
                'icon' => $n->icon,
                'title' => $n->title,
                'message' => $n->message,
                'color' => $n->color,
                'time' => $n->created_at->diffForHumans(),
                'is_read' => $n->read_at !== null,
                'link' => $n->link,
            ])
            ->toArray();
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    protected function getWOStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => '⏳',
            'approved' => '✓',
            'assigned' => '👤',
            'in_progress' => '🔧',
            'on_hold' => '⏸️',
            'completed' => '✅',
            'cancelled' => '❌',
            default => '📋',
        };
    }

    protected function getWOStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'orange',
            'approved' => 'blue',
            'assigned' => 'purple',
            'in_progress' => 'purple',
            'on_hold' => 'gray',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    protected function getWOActivityDescription(WorkOrder $wo): string
    {
        $asset = ($wo->asset_type ?? '') === 'truck'
            ? $wo->truck?->registration_number
            : $wo->equipment?->name;

        return match ($wo->status) {
            'completed' => "Terminé par {$wo->assignedTo?->name}",
            'in_progress' => "En cours - {$asset}",
            'approved' => "Approuvé - {$wo->title}",
            'assigned' => "Assigné à {$wo->assignedTo?->name}",
            'pending' => "Nouvelle demande - {$wo->title}",
            default => $wo->title,
        };
    }

    protected function getInitials(string $name): string
    {
        $parts = explode(' ', $name);
        $initials = '';
        foreach ($parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return substr($initials, 0, 2);
    }
}
