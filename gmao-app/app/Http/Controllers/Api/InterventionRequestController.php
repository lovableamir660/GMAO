<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InterventionRequest;
use App\Models\WorkOrder;
use App\Models\Equipment;
use App\Models\Truck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterventionRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (
            !$user->can('intervention_request:view_any') &&
            !$user->can('intervention_request:view_own') &&
            !$user->can('intervention_request:view')
        ) {
            abort(403, 'Accès non autorisé');
        }

        $siteId = $user->current_site_id;

        $requests = InterventionRequest::query()
            ->where('site_id', $siteId)
            ->with(['equipment', 'truck', 'site', 'requestedBy', 'validatedBy', 'rejectedBy'])

            // ✅ Corrigé : supprimé assigned_to
            ->when(!$user->can('intervention_request:view_any'), function ($query) use ($user) {
                $query->where('requested_by', $user->id);
            })

            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
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
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->urgency, function ($query, $urgency) {
                $query->where('urgency', $urgency);
            })
            ->when($request->asset_type, function ($query, $assetType) {
                $query->where('asset_type', $assetType);
            })
            ->when($request->equipment_id, function ($query, $equipmentId) {
                $query->where('equipment_id', $equipmentId);
            })
            ->when($request->truck_id, function ($query, $truckId) {
                $query->where('truck_id', $truckId);
            })
            ->when($request->boolean('machine_stopped'), function ($query) {
                $query->where('machine_stopped', true);
            })
            ->when($request->boolean('my_requests'), function ($query) use ($request) {
                $query->where('requested_by', $request->user()->id);
            })
            ->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($requests);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('intervention_request:create')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'asset_type' => 'required|in:equipment,truck',
            'equipment_id' => 'required_if:asset_type,equipment|nullable|exists:equipments,id',
            'truck_id' => 'required_if:asset_type,truck|nullable|exists:trucks,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'urgency' => 'required|in:low,medium,high,critical',
            'machine_stopped' => 'boolean',
            'location_details' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
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
        $validated['requested_by'] = $request->user()->id;
        $validated['code'] = InterventionRequest::generateCode();
        $validated['status'] = 'pending';

        if ($validated['asset_type'] === 'equipment') {
            $validated['truck_id'] = null;
        } else {
            $validated['equipment_id'] = null;
        }

        $interventionRequest = InterventionRequest::create($validated);

        return response()->json([
            'message' => 'Demande d\'intervention créée avec succès',
            'intervention_request' => $interventionRequest->load(['equipment', 'truck', 'site', 'requestedBy']),
        ], 201);
    }

    public function show(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        $user = $request->user();

        if (
            !$user->can('intervention_request:view') &&
            !$user->can('intervention_request:view_own') &&
            !$user->can('intervention_request:view_any')
        ) {
            abort(403, 'Accès non autorisé');
        }

        // ✅ Corrigé : supprimé assigned_to
        if (!$user->can('intervention_request:view_any') && !$user->can('intervention_request:view')) {
            if ($interventionRequest->requested_by !== $user->id) {
                abort(403, 'Vous n\'avez pas accès à cette demande');
            }
        }

        if (
            !$user->hasRole('super-admin') &&
            $interventionRequest->site_id !== $user->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        $interventionRequest->load([
            'equipment.location',
            'truck.currentDriver',
            'site',
            'requestedBy',
            'validatedBy',
            'rejectedBy',
            'workOrder.assignedTo',
        ]);

        return response()->json($interventionRequest);
    }

    public function update(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        if (!$request->user()->can('intervention_request:update')) {
            abort(403, 'Accès non autorisé');
        }

        if (
            !$request->user()->hasRole('super-admin') &&
            $interventionRequest->site_id !== $request->user()->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        if (!$interventionRequest->isPending()) {
            return response()->json([
                'message' => 'Cette demande ne peut plus être modifiée',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'urgency' => 'sometimes|required|in:low,medium,high,critical',
            'machine_stopped' => 'boolean',
            'location_details' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        $interventionRequest->update($validated);

        return response()->json([
            'message' => 'Demande mise à jour avec succès',
            'intervention_request' => $interventionRequest->load(['equipment', 'truck', 'site', 'requestedBy']),
        ]);
    }

    public function destroy(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        if (!$request->user()->can('intervention_request:delete')) {
            abort(403, 'Accès non autorisé');
        }

        if (
            !$request->user()->hasRole('super-admin') &&
            $interventionRequest->site_id !== $request->user()->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        if (!$interventionRequest->isPending()) {
            return response()->json([
                'message' => 'Cette demande ne peut plus être supprimée',
            ], 422);
        }

        $interventionRequest->delete();

        return response()->json([
            'message' => 'Demande supprimée avec succès',
        ]);
    }

    public function approve(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        if (!$request->user()->can('intervention_request:validate')) {
            abort(403, 'Accès non autorisé');
        }

        if (
            !$request->user()->hasRole('super-admin') &&
            $interventionRequest->site_id !== $request->user()->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        if (!$interventionRequest->canBeValidated()) {
            return response()->json([
                'message' => 'Cette demande ne peut plus être validée',
            ], 422);
        }

        $validated = $request->validate([
            'comment' => 'nullable|string|max:500',
        ]);

        $interventionRequest->approve($request->user()->id, $validated['comment'] ?? null);

        return response()->json([
            'message' => 'Demande approuvée avec succès',
            'intervention_request' => $interventionRequest->load(['equipment', 'truck', 'requestedBy', 'validatedBy']),
        ]);
    }

    public function reject(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        if (!$request->user()->can('intervention_request:validate')) {
            abort(403, 'Accès non autorisé');
        }

        if (
            !$request->user()->hasRole('super-admin') &&
            $interventionRequest->site_id !== $request->user()->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        if (!$interventionRequest->canBeRejected()) {
            return response()->json([
                'message' => 'Cette demande ne peut plus être rejetée',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $interventionRequest->reject($request->user()->id, $validated['reason']);

        return response()->json([
            'message' => 'Demande rejetée',
            'intervention_request' => $interventionRequest->load(['equipment', 'truck', 'requestedBy', 'rejectedBy']),
        ]);
    }

    public function validate(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        if (!$request->user()->can('intervention_request:validate')) {
            abort(403, 'Accès non autorisé');
        }

        if (
            !$request->user()->hasRole('super-admin') &&
            $interventionRequest->site_id !== $request->user()->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        if (!$interventionRequest->canBeValidated()) {
            return response()->json([
                'message' => 'Cette demande ne peut plus être validée',
            ], 422);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:500',
            'reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        if ($validated['action'] === 'approve') {
            $interventionRequest->approve($request->user()->id, $validated['comment'] ?? null);
            $message = 'Demande approuvée avec succès';
        } else {
            $interventionRequest->reject($request->user()->id, $validated['reason'] ?? $validated['comment'] ?? 'Rejetée');
            $message = 'Demande rejetée';
        }

        return response()->json([
            'message' => $message,
            'intervention_request' => $interventionRequest->load(['equipment', 'truck', 'requestedBy', 'validatedBy', 'rejectedBy']),
        ]);
    }

    public function convertToWorkOrder(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        if (!$request->user()->can('intervention_request:convert')) {
            abort(403, 'Accès non autorisé');
        }

        if (
            !$request->user()->hasRole('super-admin') &&
            $interventionRequest->site_id !== $request->user()->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        if (!$interventionRequest->canBeConverted()) {
            return response()->json([
                'message' => 'Cette demande ne peut pas être convertie en OT',
            ], 422);
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'type' => 'required|in:corrective,preventive,improvement,inspection',
            'scheduled_start' => 'nullable|date',
            'estimated_duration' => 'nullable|integer|min:1',
        ]);

        $priorityMap = [
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'critical' => 'urgent',
        ];

        $workOrder = DB::transaction(function () use ($interventionRequest, $validated, $request, $priorityMap) {
            $workOrder = WorkOrder::create([
                'site_id' => $interventionRequest->site_id,
                'asset_type' => $interventionRequest->asset_type,
                'equipment_id' => $interventionRequest->equipment_id,
                'truck_id' => $interventionRequest->truck_id,
                'intervention_request_id' => $interventionRequest->id,
                'requested_by' => $interventionRequest->requested_by,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'code' => WorkOrder::generateCode($interventionRequest->site_id),
                'title' => $interventionRequest->title,
                'description' => $interventionRequest->description . "\n\n---\nCréé depuis DI: " . $interventionRequest->code,
                'type' => $validated['type'],
                'priority' => $validated['priority'] ?? $priorityMap[$interventionRequest->urgency],
                'status' => $validated['assigned_to'] ? 'assigned' : 'approved',
                'scheduled_start' => $validated['scheduled_start'] ?? null,
                'estimated_duration' => $validated['estimated_duration'] ?? null,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $workOrder->addHistory(
                $request->user()->id,
                'created',
                "OT créé depuis DI {$interventionRequest->code}"
            );

            $interventionRequest->convertToWorkOrder($workOrder->id);

            return $workOrder;
        });

        return response()->json([
            'message' => 'Demande convertie en ordre de travail avec succès',
            'intervention_request' => $interventionRequest->fresh(['equipment', 'truck', 'requestedBy', 'workOrder']),
            'work_order' => $workOrder->load(['equipment', 'truck', 'assignedTo']),
        ]);
    }

    public function cancel(Request $request, InterventionRequest $interventionRequest): JsonResponse
    {
        if (!$request->user()->can('intervention_request:update')) {
            abort(403, 'Accès non autorisé');
        }

        if (
            !$request->user()->hasRole('super-admin') &&
            $interventionRequest->site_id !== $request->user()->current_site_id
        ) {
            abort(403, 'Cette demande n\'appartient pas à votre site');
        }

        if ($interventionRequest->isConverted()) {
            return response()->json([
                'message' => 'Cette demande a déjà été convertie en OT et ne peut pas être annulée',
            ], 422);
        }

        $interventionRequest->cancel();

        return response()->json([
            'message' => 'Demande annulée avec succès',
            'intervention_request' => $interventionRequest,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $siteId = $user->current_site_id;

        // ✅ Appliquer le même filtre que index()
        $baseQuery = InterventionRequest::where('site_id', $siteId);

        if (!$user->can('intervention_request:view_any')) {
            $baseQuery->where('requested_by', $user->id);
        }

        $byStatus = [
            'pending' => (clone $baseQuery)->pending()->count(),
            'approved' => (clone $baseQuery)->approved()->count(),
            'rejected' => (clone $baseQuery)->rejected()->count(),
            'converted' => (clone $baseQuery)->converted()->count(),
            'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
        ];

        $byUrgency = [
            'critical' => (clone $baseQuery)->pending()->where('urgency', 'critical')->count(),
            'high' => (clone $baseQuery)->pending()->where('urgency', 'high')->count(),
            'medium' => (clone $baseQuery)->pending()->where('urgency', 'medium')->count(),
            'low' => (clone $baseQuery)->pending()->where('urgency', 'low')->count(),
        ];

        $byAssetType = [
            'equipment' => (clone $baseQuery)->forEquipments()->count(),
            'truck' => (clone $baseQuery)->forTrucks()->count(),
        ];

        $machineStopped = (clone $baseQuery)->pending()->machineStopped()->count();

        $thisMonth = [
            'created' => (clone $baseQuery)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'converted' => (clone $baseQuery)->whereMonth('converted_at', now()->month)
                ->whereYear('converted_at', now()->year)->count(),
        ];

        $topEquipments = InterventionRequest::where('site_id', $siteId)
            ->where('asset_type', 'equipment')
            ->whereNotNull('equipment_id')
            ->when(!$user->can('intervention_request:view_any'), function ($query) use ($user) {
                $query->where('requested_by', $user->id);
            })
            ->select('equipment_id', DB::raw('count(*) as total'))
            ->groupBy('equipment_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('equipment:id,name,code')
            ->get();

        $topTrucks = InterventionRequest::where('site_id', $siteId)
            ->where('asset_type', 'truck')
            ->whereNotNull('truck_id')
            ->when(!$user->can('intervention_request:view_any'), function ($query) use ($user) {
                $query->where('requested_by', $user->id);
            })
            ->select('truck_id', DB::raw('count(*) as total'))
            ->groupBy('truck_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('truck:id,registration_number,code,brand,model')
            ->get();

        return response()->json([
            'by_status' => $byStatus,
            'by_urgency' => $byUrgency,
            'by_asset_type' => $byAssetType,
            'machine_stopped' => $machineStopped,
            'this_month' => $thisMonth,
            'top_equipments' => $topEquipments,
            'top_trucks' => $topTrucks,
            'total' => array_sum($byStatus),
        ]);
    }


    public function forEquipment(Request $request, Equipment $equipment): JsonResponse
    {
        $user = $request->user();

        if (
            !$user->can('intervention_request:view_any') &&
            !$user->can('intervention_request:view_own') &&
            !$user->can('intervention_request:view')
        ) {
            abort(403, 'Accès non autorisé');
        }

        $query = InterventionRequest::where('equipment_id', $equipment->id)
            ->with(['requestedBy', 'validatedBy', 'workOrder']);

        // ✅ Corrigé : supprimé assigned_to
        if (!$user->can('intervention_request:view_any')) {
            $query->where('requested_by', $user->id);
        }

        $requests = $query->orderByDesc('created_at')
            ->paginate($request->per_page ?? 10);

        return response()->json($requests);
    }

    public function forTruck(Request $request, Truck $truck): JsonResponse
    {
        $user = $request->user();

        if (
            !$user->can('intervention_request:view_any') &&
            !$user->can('intervention_request:view_own') &&
            !$user->can('intervention_request:view')
        ) {
            abort(403, 'Accès non autorisé');
        }

        $query = InterventionRequest::where('truck_id', $truck->id)
            ->with(['requestedBy', 'validatedBy', 'workOrder']);

        // ✅ Corrigé : supprimé assigned_to
        if (!$user->can('intervention_request:view_any')) {
            $query->where('requested_by', $user->id);
        }

        $requests = $query->orderByDesc('created_at')
            ->paginate($request->per_page ?? 10);

        return response()->json($requests);
    }
}
