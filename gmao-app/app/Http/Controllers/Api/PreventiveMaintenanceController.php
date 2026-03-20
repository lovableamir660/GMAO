<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\PreventiveMaintenance;
use App\Models\PreventiveMaintenanceLog;
use App\Models\Truck;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreventiveMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('preventive:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $siteId = $request->user()->current_site_id;

        $plans = PreventiveMaintenance::query()
            ->where('site_id', $siteId)
            ->with(['equipment', 'truck', 'site', 'assignedTo'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('equipment', function ($eq) use ($search) {
                            $eq->where('name', 'like', "%{$search}%")
                               ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('truck', function ($tr) use ($search) {
                            $tr->where('registration_number', 'like', "%{$search}%")
                               ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->asset_type, fn($q, $type) => $q->where('asset_type', $type))
            ->when($request->equipment_id, fn($q, $id) => $q->where('equipment_id', $id))
            ->when($request->truck_id, fn($q, $id) => $q->where('truck_id', $id))
            ->when($request->frequency_type, fn($q, $type) => $q->where('frequency_type', $type))
            ->when($request->priority, fn($q, $priority) => $q->where('priority', $priority))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('due'), fn($q) => $q->due())
            ->when($request->boolean('due_soon'), fn($q) => $q->dueSoon($request->days ?? 7))
            ->when($request->boolean('overdue'), function ($q) {
                $q->where('is_active', true)
                  ->where('next_execution_date', '<', Carbon::today());
            })
            ->orderByRaw('CASE WHEN next_execution_date < CURDATE() THEN 0 ELSE 1 END')
            ->orderBy('next_execution_date')
            ->paginate($request->per_page ?? 15);

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('preventive:create')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'asset_type' => 'required|in:equipment,truck',
            'equipment_id' => 'required_if:asset_type,equipment|nullable|exists:equipments,id',
            'truck_id' => 'required_if:asset_type,truck|nullable|exists:trucks,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency_type' => 'required|in:daily,weekly,monthly,yearly,counter,mileage',
            'frequency_value' => 'required_if:frequency_type,daily,weekly,monthly,yearly|nullable|integer|min:1',
            'counter_threshold' => 'required_if:frequency_type,counter|nullable|integer|min:1',
            'counter_unit' => 'required_if:frequency_type,counter|nullable|string|max:50',
            'mileage_interval' => 'required_if:frequency_type,mileage|nullable|integer|min:100',
            'start_date' => 'required_unless:frequency_type,mileage|nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'priority' => 'required|in:low,medium,high,urgent',
            'estimated_duration' => 'nullable|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'advance_days' => 'nullable|integer|min:0|max:30',
            'advance_mileage' => 'nullable|integer|min:0|max:5000',
            'tasks' => 'nullable|array',
            'tasks.*.description' => 'required|string|max:255',
            'tasks.*.estimated_duration' => 'nullable|integer|min:1',
            'tasks.*.instructions' => 'nullable|string',
        ]);

        $siteId = $request->user()->current_site_id;

        if ($validated['asset_type'] === 'equipment' && $validated['equipment_id']) {
            $equipment = Equipment::find($validated['equipment_id']);
            if ($equipment) {
                $siteId = $equipment->site_id;
            }
        } elseif ($validated['asset_type'] === 'truck' && $validated['truck_id']) {
            $truck = Truck::find($validated['truck_id']);
            if ($truck) {
                $siteId = $truck->site_id;
            }
        }

        $validated['site_id'] = $siteId;
        $validated['created_by'] = $request->user()->id;
        $validated['code'] = PreventiveMaintenance::generateCode();
        $validated['is_active'] = true;
        $validated['advance_days'] = $validated['advance_days'] ?? 7;
        $validated['advance_mileage'] = $validated['advance_mileage'] ?? 500;

        if ($validated['asset_type'] === 'equipment') {
            $validated['truck_id'] = null;
            $validated['mileage_interval'] = null;
            $validated['advance_mileage'] = null;
        } else {
            $validated['equipment_id'] = null;
        }

        if ($validated['frequency_type'] === 'mileage') {
            $validated['next_execution_date'] = null;
            $truck = Truck::find($validated['truck_id']);
            $validated['last_mileage'] = $truck?->mileage ?? 0;
            $validated['next_mileage'] = ($truck?->mileage ?? 0) + $validated['mileage_interval'];
        } else {
            $validated['next_execution_date'] = Carbon::parse($validated['start_date']);
        }

        $plan = DB::transaction(function () use ($validated) {
            $tasks = $validated['tasks'] ?? [];
            unset($validated['tasks']);

            $plan = PreventiveMaintenance::create($validated);

            foreach ($tasks as $index => $task) {
                $plan->tasks()->create([
                    'order' => $index + 1,
                    'description' => $task['description'],
                    'estimated_duration' => $task['estimated_duration'] ?? null,
                    'instructions' => $task['instructions'] ?? null,
                ]);
            }

            return $plan;
        });

        return response()->json([
            'message' => 'Plan de maintenance créé avec succès',
            'preventive_maintenance' => $plan->load(['equipment', 'truck', 'site', 'assignedTo', 'tasks']),
        ], 201);
    }

    public function show(Request $request, PreventiveMaintenance $preventiveMaintenance): JsonResponse
    {
        if (!$request->user()->can('preventive:view')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $preventiveMaintenance->site_id !== $request->user()->current_site_id) {
            abort(403, 'Ce plan n\'appartient pas à votre site');
        }

        $preventiveMaintenance->load([
            'equipment.location',
            'truck.currentDriver',
            'site',
            'assignedTo',
            'createdBy',
            'tasks',
            'parts.part',
            'logs.workOrder',
        ]);

        $preventiveMaintenance->append(['days_until_due', 'mileage_until_due', 'status']);

        return response()->json($preventiveMaintenance);
    }

    public function update(Request $request, PreventiveMaintenance $preventiveMaintenance): JsonResponse
    {
        if (!$request->user()->can('preventive:update')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $preventiveMaintenance->site_id !== $request->user()->current_site_id) {
            abort(403, 'Ce plan n\'appartient pas à votre site');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'frequency_type' => 'sometimes|required|in:daily,weekly,monthly,yearly,counter,mileage',
            'frequency_value' => 'required_if:frequency_type,daily,weekly,monthly,yearly|nullable|integer|min:1',
            'counter_threshold' => 'required_if:frequency_type,counter|nullable|integer|min:1',
            'counter_unit' => 'required_if:frequency_type,counter|nullable|string|max:50',
            'mileage_interval' => 'required_if:frequency_type,mileage|nullable|integer|min:100',
            'end_date' => 'nullable|date',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
            'estimated_duration' => 'nullable|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'advance_days' => 'nullable|integer|min:0|max:30',
            'advance_mileage' => 'nullable|integer|min:0|max:5000',
            'is_active' => 'boolean',
        ]);

        $preventiveMaintenance->update($validated);
        $preventiveMaintenance->updateNextExecution();

        return response()->json([
            'message' => 'Plan de maintenance mis à jour',
            'preventive_maintenance' => $preventiveMaintenance->load(['equipment', 'truck', 'assignedTo', 'tasks']),
        ]);
    }

    public function destroy(Request $request, PreventiveMaintenance $preventiveMaintenance): JsonResponse
    {
        if (!$request->user()->can('preventive:delete')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $preventiveMaintenance->site_id !== $request->user()->current_site_id) {
            abort(403, 'Ce plan n\'appartient pas à votre site');
        }

        $preventiveMaintenance->delete();

        return response()->json([
            'message' => 'Plan de maintenance supprimé',
        ]);
    }

    /**
     * Activer/Désactiver un plan
     */
    public function toggleActive(Request $request, PreventiveMaintenance $preventiveMaintenance): JsonResponse
    {
        if (!$request->user()->can('preventive:update')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $preventiveMaintenance->site_id !== $request->user()->current_site_id) {
            abort(403, 'Ce plan n\'appartient pas à votre site');
        }

        if ($preventiveMaintenance->is_active) {
            $preventiveMaintenance->deactivate();
            $status = 'désactivé';
        } else {
            $preventiveMaintenance->activate();
            $status = 'activé';
        }

        return response()->json([
            'message' => "Plan {$status}",
            'preventive_maintenance' => $preventiveMaintenance,
        ]);
    }

    /**
     * Générer manuellement un OT depuis un plan
     * ✅ CORRIGÉ — exige assigned_to, crée l'OT en statut 'assigned'
     */
    public function generateWorkOrder(Request $request, PreventiveMaintenance $preventiveMaintenance): JsonResponse
    {
        if (!$request->user()->can('preventive:generate_wo')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $preventiveMaintenance->site_id !== $request->user()->current_site_id) {
            abort(403, 'Ce plan n\'appartient pas à votre site');
        }

        $validated = $request->validate([
            'mileage' => 'nullable|integer|min:0',
            'assigned_to' => 'required|exists:users,id',
        ]);

        $assignedTo = $validated['assigned_to'];

        $workOrder = DB::transaction(function () use ($preventiveMaintenance, $request, $validated, $assignedTo) {
            // Construire la description avec les tâches
            $description = $preventiveMaintenance->description ?? '';
            if ($preventiveMaintenance->tasks->count() > 0) {
                $description .= "\n\n--- Tâches à effectuer ---\n";
                foreach ($preventiveMaintenance->tasks as $task) {
                    $description .= "☐ {$task->description}\n";
                    if ($task->instructions) {
                        $description .= "   → {$task->instructions}\n";
                    }
                }
            }

            // Info sur le déclencheur
            $triggerInfo = '';
            if ($preventiveMaintenance->frequency_type === 'mileage') {
                $currentMileage = $validated['mileage'] ?? $preventiveMaintenance->truck?->mileage ?? 0;
                $triggerInfo = "\n\n--- Info déclenchement ---\n";
                $triggerInfo .= "Type: Kilométrage\n";
                $triggerInfo .= "Seuil: " . number_format($preventiveMaintenance->next_mileage, 0, ',', ' ') . " km\n";
                $triggerInfo .= "Kilométrage actuel: " . number_format($currentMileage, 0, ',', ' ') . " km\n";
            }

            // Créer l'OT avec le technicien assigné → statut 'assigned'
            $workOrder = WorkOrder::create([
                'site_id' => $preventiveMaintenance->site_id,
                'asset_type' => $preventiveMaintenance->asset_type,
                'equipment_id' => $preventiveMaintenance->equipment_id,
                'truck_id' => $preventiveMaintenance->truck_id,
                'requested_by' => $request->user()->id,
                'assigned_to' => $assignedTo,
                'code' => WorkOrder::generateCode($preventiveMaintenance->site_id),
                'title' => "[MP] {$preventiveMaintenance->name}",
                'description' => $description . $triggerInfo,
                'type' => 'preventive',
                'priority' => $preventiveMaintenance->priority,
                'status' => 'assigned',
                'scheduled_start' => $preventiveMaintenance->next_execution_date ?? now(),
                'estimated_duration' => $preventiveMaintenance->estimated_duration,
            ]);

            // Historique
            $workOrder->addHistory(
                $request->user()->id,
                'created',
                "Généré depuis le plan {$preventiveMaintenance->code}"
            );

            $assignee = \App\Models\User::find($assignedTo);
            $workOrder->addHistory(
                $request->user()->id,
                'assigned',
                "Assigné à {$assignee->name}"
            );

            // Log
            PreventiveMaintenanceLog::create([
                'preventive_maintenance_id' => $preventiveMaintenance->id,
                'work_order_id' => $workOrder->id,
                'scheduled_date' => $preventiveMaintenance->next_execution_date ?? now(),
                'mileage_at_generation' => $preventiveMaintenance->asset_type === 'truck'
                    ? ($validated['mileage'] ?? $preventiveMaintenance->truck?->mileage)
                    : null,
                'status' => 'generated',
            ]);

            // Mettre à jour les dates/kilométrages du plan
            if ($preventiveMaintenance->frequency_type === 'mileage') {
                $currentMileage = $validated['mileage'] ?? $preventiveMaintenance->truck?->mileage ?? 0;
                $preventiveMaintenance->markAsExecuted($currentMileage);
            } else {
                $preventiveMaintenance->last_execution_date = $preventiveMaintenance->next_execution_date ?? now();
                $preventiveMaintenance->updateNextExecution();
            }

            return $workOrder;
        });

        return response()->json([
            'message' => 'Ordre de travail généré avec succès',
            'work_order' => $workOrder->load(['equipment', 'truck', 'assignedTo']),
            'preventive_maintenance' => $preventiveMaintenance->fresh(['equipment', 'truck']),
        ]);
    }

    /**
     * Calendrier des maintenances préventives
     */
    public function calendar(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->endOfMonth()->addMonths(2);

        $datePlans = PreventiveMaintenance::query()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', '!=', 'mileage')
            ->whereNotNull('next_execution_date')
            ->whereBetween('next_execution_date', [$startDate, $endDate])
            ->with(['equipment', 'truck', 'assignedTo'])
            ->get();

        $mileagePlans = PreventiveMaintenance::query()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', 'mileage')
            ->where('asset_type', 'truck')
            ->whereNotNull('next_mileage')
            ->with(['truck', 'assignedTo'])
            ->get()
            ->filter(function ($plan) {
                return $plan->is_due || $plan->is_due_soon;
            });

        $events = collect();

        foreach ($datePlans as $plan) {
            $events->push([
                'id' => $plan->id,
                'title' => $plan->name,
                'code' => $plan->code,
                'date' => $plan->next_execution_date->format('Y-m-d'),
                'asset_type' => $plan->asset_type,
                'asset_name' => $plan->asset_name,
                'asset_code' => $plan->asset_code,
                'priority' => $plan->priority,
                'assigned_to' => $plan->assignedTo?->name,
                'frequency_label' => $plan->frequency_label,
                'trigger_type' => 'date',
                'is_overdue' => $plan->next_execution_date->lt(Carbon::today()),
            ]);
        }

        foreach ($mileagePlans as $plan) {
            $events->push([
                'id' => $plan->id,
                'title' => $plan->name,
                'code' => $plan->code,
                'date' => Carbon::today()->format('Y-m-d'),
                'asset_type' => $plan->asset_type,
                'asset_name' => $plan->asset_name,
                'asset_code' => $plan->asset_code,
                'priority' => $plan->priority,
                'assigned_to' => $plan->assignedTo?->name,
                'frequency_label' => $plan->frequency_label,
                'trigger_type' => 'mileage',
                'current_mileage' => $plan->truck?->mileage,
                'next_mileage' => $plan->next_mileage,
                'mileage_remaining' => $plan->mileage_until_due,
                'is_overdue' => $plan->is_due,
            ]);
        }

        return response()->json($events->sortBy('date')->values());
    }

    /**
     * Plans à exécuter prochainement
     */
    public function upcoming(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $days = $request->days ?? 7;

        $datePlans = PreventiveMaintenance::query()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', '!=', 'mileage')
            ->whereNotNull('next_execution_date')
            ->where('next_execution_date', '<=', Carbon::now()->addDays($days))
            ->with(['equipment', 'truck', 'assignedTo'])
            ->orderBy('next_execution_date')
            ->get();

        $mileagePlans = PreventiveMaintenance::query()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->where('frequency_type', 'mileage')
            ->where('asset_type', 'truck')
            ->with(['truck', 'assignedTo'])
            ->get()
            ->filter(function ($plan) {
                return $plan->is_due || $plan->is_due_soon;
            })
            ->values();

        return response()->json([
            'by_date' => $datePlans,
            'by_mileage' => $mileagePlans,
            'total' => $datePlans->count() + $mileagePlans->count(),
        ]);
    }

    /**
     * Statistiques
     */
    public function stats(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $baseQuery = PreventiveMaintenance::where('site_id', $siteId);

        $total = (clone $baseQuery)->count();
        $active = (clone $baseQuery)->active()->count();
        $inactive = (clone $baseQuery)->inactive()->count();

        $byAssetType = [
            'equipment' => (clone $baseQuery)->forEquipments()->count(),
            'truck' => (clone $baseQuery)->forTrucks()->count(),
        ];

        $byFrequency = [
            'daily' => (clone $baseQuery)->where('frequency_type', 'daily')->count(),
            'weekly' => (clone $baseQuery)->where('frequency_type', 'weekly')->count(),
            'monthly' => (clone $baseQuery)->where('frequency_type', 'monthly')->count(),
            'yearly' => (clone $baseQuery)->where('frequency_type', 'yearly')->count(),
            'mileage' => (clone $baseQuery)->where('frequency_type', 'mileage')->count(),
            'counter' => (clone $baseQuery)->where('frequency_type', 'counter')->count(),
        ];

        $overdueByDate = (clone $baseQuery)
            ->active()
            ->where('frequency_type', '!=', 'mileage')
            ->where('next_execution_date', '<', Carbon::today())
            ->count();

        $overdueByMileage = PreventiveMaintenance::where('site_id', $siteId)
            ->active()
            ->where('frequency_type', 'mileage')
            ->where('asset_type', 'truck')
            ->with('truck')
            ->get()
            ->filter(fn($p) => $p->is_due)
            ->count();

        $dueThisWeek = (clone $baseQuery)
            ->active()
            ->where('frequency_type', '!=', 'mileage')
            ->whereBetween('next_execution_date', [Carbon::now(), Carbon::now()->addWeek()])
            ->count();

        $generatedThisMonth = PreventiveMaintenanceLog::whereHas('preventiveMaintenance', function ($q) use ($siteId) {
                $q->where('site_id', $siteId);
            })
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'by_asset_type' => $byAssetType,
            'by_frequency' => $byFrequency,
            'overdue' => $overdueByDate + $overdueByMileage,
            'overdue_by_date' => $overdueByDate,
            'overdue_by_mileage' => $overdueByMileage,
            'due_this_week' => $dueThisWeek,
            'generated_this_month' => $generatedThisMonth,
        ]);
    }

    public function forEquipment(Request $request, Equipment $equipment): JsonResponse
    {
        if (!$request->user()->can('preventive:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $plans = PreventiveMaintenance::where('equipment_id', $equipment->id)
            ->with(['assignedTo', 'tasks'])
            ->orderBy('next_execution_date')
            ->paginate($request->per_page ?? 10);

        return response()->json($plans);
    }

    public function forTruck(Request $request, Truck $truck): JsonResponse
    {
        if (!$request->user()->can('preventive:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $plans = PreventiveMaintenance::where('truck_id', $truck->id)
            ->with(['assignedTo', 'tasks'])
            ->orderByRaw('CASE WHEN frequency_type = "mileage" THEN next_mileage ELSE NULL END')
            ->orderBy('next_execution_date')
            ->paginate($request->per_page ?? 10);

        return response()->json($plans);
    }

    /**
     * Vérifier et générer automatiquement les OT dus
     */
    public function checkAndGenerate(Request $request): JsonResponse
    {
        if (!$request->user()->can('preventive:generate_wo')) {
            abort(403, 'Accès non autorisé');
        }

        $siteId = $request->user()->current_site_id;
        $generated = [];

        $duePlans = PreventiveMaintenance::where('site_id', $siteId)
            ->active()
            ->with(['equipment', 'truck'])
            ->get()
            ->filter(fn($plan) => $plan->needsWorkOrderGeneration());

        foreach ($duePlans as $plan) {
            $hasOpenWorkOrder = WorkOrder::where('site_id', $siteId)
                ->whereIn('status', ['pending', 'approved', 'assigned', 'in_progress', 'on_hold'])
                ->where('type', 'preventive')
                ->where('title', 'like', "%{$plan->code}%")
                ->exists();

            if (!$hasOpenWorkOrder) {
                $workOrder = $plan->generateWorkOrder($request->user()->id);
                if ($workOrder) {
                    $generated[] = [
                        'plan_code' => $plan->code,
                        'plan_name' => $plan->name,
                        'work_order_code' => $workOrder->code,
                        'asset_name' => $plan->asset_name,
                    ];
                }
            }
        }

        return response()->json([
            'message' => count($generated) . ' ordre(s) de travail généré(s)',
            'generated' => $generated,
        ]);
    }
}
