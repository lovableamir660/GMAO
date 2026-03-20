<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Truck;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // ✅ Vérifier au moins une permission de visualisation
        if (!$user->can('workorder:view_any') &&
            !$user->can('workorder:view_own') &&
            !$user->can('workorder:view')) {
            abort(403, 'Accès non autorisé');
        }

        $siteId = $user->current_site_id;

        $workOrders = WorkOrder::query()
            ->where('site_id', $siteId)
            ->with(['equipment', 'truck', 'site', 'assignedTo', 'requestedBy'])

            // ✅ Si PAS view_any → filtrer uniquement les OT de l'utilisateur
            ->when(!$user->can('workorder:view_any'), function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('requested_by', $user->id);
                });
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
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->priority, function ($query, $priority) {
                $query->where('priority', $priority);
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
            ->when($request->assigned_to, function ($query, $assignedTo) {
                $query->where('assigned_to', $assignedTo);
            })
            ->when($request->boolean('overdue'), function ($query) {
                $query->overdue();
            })
            ->when($request->boolean('my_orders'), function ($query) use ($request) {
                $query->where('assigned_to', $request->user()->id);
            })
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($workOrders);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('workorder:create')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'asset_type' => 'required|in:equipment,truck',
            'equipment_id' => 'required_if:asset_type,equipment|nullable|exists:equipments,id',
            'truck_id' => 'required_if:asset_type,truck|nullable|exists:trucks,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:corrective,preventive,improvement,inspection',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'estimated_duration' => 'nullable|integer|min:1',
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
        $validated['code'] = WorkOrder::generateCode($siteId);
        $validated['status'] = $validated['assigned_to'] ? 'assigned' : 'pending';

        if ($validated['asset_type'] === 'equipment') {
            $validated['truck_id'] = null;
        } else {
            $validated['equipment_id'] = null;
        }

        $workOrder = WorkOrder::create($validated);

        $workOrder->addHistory(
            $request->user()->id,
            'created',
            'Ordre de travail créé'
        );

        if ($validated['assigned_to']) {
            $assignee = \App\Models\User::find($validated['assigned_to']);
            $workOrder->addHistory(
                $request->user()->id,
                'assigned',
                "Assigné à {$assignee->name}",
                null,
                $assignee->name
            );
        }

        return response()->json([
            'message' => 'Intervention créée avec succès',
            'work_order' => $workOrder->load(['equipment', 'truck', 'site', 'assignedTo', 'requestedBy']),
        ], 201);
    }

    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $user = $request->user();

        // ✅ Vérifier permission view OU view_own
        if (!$user->can('workorder:view') &&
            !$user->can('workorder:view_own') &&
            !$user->can('workorder:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        // ✅ Si view_own seulement, vérifier que c'est bien son OT
        if (!$user->can('workorder:view_any') && !$user->can('workorder:view')) {
            if ($workOrder->assigned_to !== $user->id && $workOrder->requested_by !== $user->id) {
                abort(403, 'Vous n\'avez pas accès à cette intervention');
            }
        }

        if (!$user->hasRole('super-admin') &&
            $workOrder->site_id !== $user->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        $workOrder->load([
            'equipment.location',
            'truck.currentDriver',
            'site',
            'interventionRequest',
            'assignedTo',
            'requestedBy',
            'approvedBy',
            'completedBy',
            'cancelledBy',
            'parts.part',
            'comments.user',
            'histories.user',
        ]);

        return response()->json($workOrder);
    }

    public function update(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:update')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $workOrder->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:corrective,preventive,improvement,inspection',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'estimated_duration' => 'nullable|integer|min:1',
            'work_performed' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'technician_notes' => 'nullable|string',
            'mileage_at_intervention' => 'nullable|integer|min:0',
        ]);

        $changes = [];

        if (isset($validated['assigned_to']) && $validated['assigned_to'] != $workOrder->assigned_to) {
            $oldAssignee = $workOrder->assignedTo?->name ?? 'Non assigné';
            $newAssignee = $validated['assigned_to'] ? \App\Models\User::find($validated['assigned_to'])->name : 'Non assigné';
            $changes[] = ['action' => 'assigned', 'old' => $oldAssignee, 'new' => $newAssignee, 'desc' => "Réassigné de {$oldAssignee} à {$newAssignee}"];

            if ($workOrder->status === 'pending' && $validated['assigned_to']) {
                $workOrder->status = 'assigned';
            }
        }

        if (isset($validated['priority']) && $validated['priority'] != $workOrder->priority) {
            $changes[] = ['action' => 'priority_changed', 'old' => $workOrder->priority, 'new' => $validated['priority'], 'desc' => "Priorité changée de {$workOrder->priority} à {$validated['priority']}"];
        }

        $workOrder->update($validated);

        foreach ($changes as $change) {
            $workOrder->addHistory($request->user()->id, $change['action'], $change['desc'], $change['old'], $change['new']);
        }

        return response()->json([
            'message' => 'Intervention mise à jour avec succès',
            'work_order' => $workOrder->load(['equipment', 'truck', 'assignedTo']),
        ]);
    }

    public function destroy(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:delete')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $workOrder->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        if ($workOrder->status === 'completed') {
            return response()->json([
                'message' => 'Impossible de supprimer une intervention terminée',
            ], 422);
        }

        $workOrder->delete();

        return response()->json([
            'message' => 'Intervention supprimée avec succès',
        ]);
    }

    public function updateStatus(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->hasRole('super-admin') &&
            $workOrder->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,assigned,in_progress,on_hold,completed,cancelled',
            'work_performed' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'technician_notes' => 'nullable|string',
            'mileage_at_intervention' => 'nullable|integer|min:0',
            'cancellation_reason' => 'required_if:status,cancelled|nullable|string|max:500',
        ]);

        $oldStatus = $workOrder->status;
        $newStatus = $validated['status'];

        switch ($newStatus) {
            case 'approved':
                if (!$request->user()->can('workorder:approve_close')) {
                    abort(403, 'Vous ne pouvez pas approuver cette intervention');
                }
                $workOrder->approved_by = $request->user()->id;
                $workOrder->approved_at = now();
                break;

            case 'assigned':
                break;

            case 'in_progress':
                if (!$request->user()->can('workorder:start')) {
                    abort(403, 'Vous ne pouvez pas démarrer cette intervention');
                }
                $workOrder->actual_start = $workOrder->actual_start ?? now();

                if ($workOrder->asset) {
                    $workOrder->asset->setInMaintenance();
                }
                break;

            case 'on_hold':
                break;

            case 'completed':
                if (!$request->user()->can('workorder:close')) {
                    abort(403, 'Vous ne pouvez pas clôturer cette intervention');
                }
                $workOrder->actual_end = now();
                $workOrder->completed_by = $request->user()->id;
                $workOrder->completed_at = now();

                if ($workOrder->actual_start) {
                    $workOrder->actual_duration = $workOrder->actual_start->diffInMinutes($workOrder->actual_end);
                }

                if (!empty($validated['work_performed'])) {
                    $workOrder->work_performed = $validated['work_performed'];
                }
                if (!empty($validated['root_cause'])) {
                    $workOrder->root_cause = $validated['root_cause'];
                }
                if (!empty($validated['diagnosis'])) {
                    $workOrder->diagnosis = $validated['diagnosis'];
                }
                if (!empty($validated['technician_notes'])) {
                    $workOrder->technician_notes = $validated['technician_notes'];
                }

                if ($workOrder->asset_type === 'truck' && !empty($validated['mileage_at_intervention'])) {
                    $workOrder->mileage_at_intervention = $validated['mileage_at_intervention'];
                    if ($workOrder->truck) {
                        $workOrder->truck->updateMileage($validated['mileage_at_intervention']);
                    }
                }

                $hourlyRate = 500;
                $workOrder->labor_cost = round(($workOrder->actual_duration / 60) * $hourlyRate, 2);
                $workOrder->total_cost = $workOrder->labor_cost + $workOrder->parts_cost;

                if ($workOrder->asset) {
                    $workOrder->asset->setActive();
                }
                break;

            case 'cancelled':
                if (!$request->user()->can('workorder:update')) {
                    abort(403, 'Vous ne pouvez pas annuler cette intervention');
                }
                $workOrder->cancelled_by = $request->user()->id;
                $workOrder->cancelled_at = now();
                $workOrder->cancellation_reason = $validated['cancellation_reason'] ?? null;

                if ($oldStatus === 'in_progress' && $workOrder->asset) {
                    $workOrder->asset->setActive();
                }
                break;
        }

        $workOrder->status = $newStatus;
        $workOrder->save();

        $statusLabels = [
            'pending' => 'En attente',
            'approved' => 'Approuvé',
            'assigned' => 'Assigné',
            'in_progress' => 'En cours',
            'on_hold' => 'En pause',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
        ];

        $description = "Statut changé de \"{$statusLabels[$oldStatus]}\" à \"{$statusLabels[$newStatus]}\"";
        if ($newStatus === 'cancelled' && !empty($validated['cancellation_reason'])) {
            $description .= " - Raison: {$validated['cancellation_reason']}";
        }

        $workOrder->addHistory(
            $request->user()->id,
            'status_changed',
            $description,
            $oldStatus,
            $newStatus
        );

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'work_order' => $workOrder->load(['equipment', 'truck', 'assignedTo', 'histories.user']),
        ]);
    }

    public function start(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:start')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$workOrder->canStart()) {
            return response()->json([
                'message' => 'Cette intervention ne peut pas être démarrée',
            ], 422);
        }

        $workOrder->start($request->user()->id);

        return response()->json([
            'message' => 'Intervention démarrée',
            'work_order' => $workOrder->load(['equipment', 'truck', 'assignedTo']),
        ]);
    }

    public function pause(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if ($workOrder->status !== 'in_progress') {
            return response()->json([
                'message' => 'Seule une intervention en cours peut être mise en pause',
            ], 422);
        }

        $workOrder->pause($request->user()->id, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Intervention mise en pause',
            'work_order' => $workOrder,
        ]);
    }

    public function resume(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if ($workOrder->status !== 'on_hold') {
            return response()->json([
                'message' => 'Seule une intervention en pause peut être reprise',
            ], 422);
        }

        $workOrder->resume($request->user()->id);

        return response()->json([
            'message' => 'Intervention reprise',
            'work_order' => $workOrder,
        ]);
    }

    public function complete(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:close')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$workOrder->canComplete()) {
            return response()->json([
                'message' => 'Cette intervention ne peut pas être terminée',
            ], 422);
        }

        $validated = $request->validate([
            'work_performed' => 'required|string',
            'root_cause' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'technician_notes' => 'nullable|string',
            'mileage_at_intervention' => 'required_if:asset_type,truck|nullable|integer|min:0',
        ]);

        $workOrder->complete($request->user()->id, $validated);

        return response()->json([
            'message' => 'Intervention terminée avec succès',
            'work_order' => $workOrder->load(['equipment', 'truck', 'assignedTo', 'completedBy']),
        ]);
    }

    public function cancel(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:update')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$workOrder->canCancel()) {
            return response()->json([
                'message' => 'Cette intervention ne peut pas être annulée',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $workOrder->cancel($request->user()->id, $validated['reason']);

        return response()->json([
            'message' => 'Intervention annulée',
            'work_order' => $workOrder,
        ]);
    }

    public function assign(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:update')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $workOrder->assign($validated['assigned_to'], $request->user()->id);

        return response()->json([
            'message' => 'Technicien assigné avec succès',
            'work_order' => $workOrder->load(['assignedTo']),
        ]);
    }

    public function addPart(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:use_parts')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $workOrder->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        if ($workOrder->status === 'completed' || $workOrder->status === 'cancelled') {
            return response()->json(['message' => 'Impossible d\'ajouter des pièces à une intervention terminée'], 422);
        }

        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $part = Part::findOrFail($validated['part_id']);

        if ($part->site_id !== $workOrder->site_id) {
            abort(403, 'Cette pièce n\'appartient pas au même site');
        }

        if ($part->quantity_in_stock < $validated['quantity']) {
            return response()->json([
                'message' => "Stock insuffisant. Disponible: {$part->quantity_in_stock} {$part->unit}",
            ], 422);
        }

        DB::transaction(function () use ($workOrder, $part, $validated, $request) {
            $workOrderPart = WorkOrderPart::create([
                'work_order_id' => $workOrder->id,
                'part_id' => $part->id,
                'quantity_used' => $validated['quantity'],
                'unit_price' => $part->unit_price,
                'total_price' => $part->unit_price * $validated['quantity'],
            ]);

            $quantityBefore = $part->quantity_in_stock;
            $part->quantity_in_stock -= $validated['quantity'];
            $part->save();

            StockMovement::create([
                'site_id' => $part->site_id,
                'part_id' => $part->id,
                'user_id' => $request->user()->id,
                'work_order_id' => $workOrder->id,
                'type' => 'out',
                'quantity' => -$validated['quantity'],
                'quantity_before' => $quantityBefore,
                'quantity_after' => $part->quantity_in_stock,
                'unit_price' => $part->unit_price,
                'reason' => 'Utilisation sur ' . $workOrder->code,
            ]);

            $workOrder->parts_cost = $workOrder->parts()->sum('total_price');
            $workOrder->total_cost = $workOrder->labor_cost + $workOrder->parts_cost;
            $workOrder->save();

            $workOrder->addHistory(
                $request->user()->id,
                'part_added',
                "Pièce ajoutée: {$validated['quantity']} x {$part->name} ({$workOrderPart->total_price} DA)"
            );
        });

        return response()->json([
            'message' => 'Pièce ajoutée avec succès',
            'work_order' => $workOrder->fresh(['parts.part', 'histories.user']),
        ]);
    }

    public function removePart(Request $request, WorkOrder $workOrder, WorkOrderPart $part): JsonResponse
    {
        if (!$request->user()->can('workorder:use_parts')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $workOrder->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        if ($workOrder->status === 'completed' || $workOrder->status === 'cancelled') {
            return response()->json(['message' => 'Impossible de retirer des pièces d\'une intervention terminée'], 422);
        }

        DB::transaction(function () use ($workOrder, $part, $request) {
            $partModel = $part->part;

            $quantityBefore = $partModel->quantity_in_stock;
            $partModel->quantity_in_stock += $part->quantity_used;
            $partModel->save();

            StockMovement::create([
                'site_id' => $partModel->site_id,
                'part_id' => $partModel->id,
                'user_id' => $request->user()->id,
                'work_order_id' => $workOrder->id,
                'type' => 'in',
                'quantity' => $part->quantity_used,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $partModel->quantity_in_stock,
                'unit_price' => $part->unit_price,
                'reason' => 'Retour de ' . $workOrder->code,
            ]);

            $workOrder->addHistory(
                $request->user()->id,
                'part_removed',
                "Pièce retirée: {$part->quantity_used} x {$partModel->name}"
            );

            $part->delete();

            $workOrder->parts_cost = $workOrder->parts()->sum('total_price');
            $workOrder->total_cost = $workOrder->labor_cost + $workOrder->parts_cost;
            $workOrder->save();
        });

        return response()->json([
            'message' => 'Pièce retirée avec succès',
            'work_order' => $workOrder->fresh(['parts.part', 'histories.user']),
        ]);
    }

    public function addComment(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->can('workorder:comment')) {
            abort(403, 'Accès non autorisé');
        }

        if (!$request->user()->hasRole('super-admin') &&
            $workOrder->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $comment = $workOrder->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $validated['comment'],
        ]);

        $workOrder->addHistory(
            $request->user()->id,
            'comment_added',
            'Commentaire ajouté'
        );

        return response()->json([
            'message' => 'Commentaire ajouté avec succès',
            'comment' => $comment->load('user'),
        ]);
    }

    public function availableParts(Request $request, WorkOrder $workOrder): JsonResponse
    {
        if (!$request->user()->hasRole('super-admin') &&
            $workOrder->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette intervention n\'appartient pas à votre site');
        }

        $parts = Part::where('site_id', $workOrder->site_id)
            ->where('is_active', true)
            ->where('quantity_in_stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'quantity_in_stock', 'unit', 'unit_price']);

        return response()->json($parts);
    }

    public function stats(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $baseQuery = WorkOrder::where('site_id', $siteId);

        $byStatus = [
            'pending' => (clone $baseQuery)->pending()->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'assigned' => (clone $baseQuery)->where('status', 'assigned')->count(),
            'in_progress' => (clone $baseQuery)->inProgress()->count(),
            'on_hold' => (clone $baseQuery)->where('status', 'on_hold')->count(),
            'completed' => (clone $baseQuery)->completed()->count(),
            'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
        ];

        $byType = [
            'corrective' => (clone $baseQuery)->corrective()->count(),
            'preventive' => (clone $baseQuery)->preventive()->count(),
            'improvement' => (clone $baseQuery)->where('type', 'improvement')->count(),
            'inspection' => (clone $baseQuery)->where('type', 'inspection')->count(),
        ];

        $byPriority = [
            'urgent' => (clone $baseQuery)->open()->where('priority', 'urgent')->count(),
            'high' => (clone $baseQuery)->open()->where('priority', 'high')->count(),
            'medium' => (clone $baseQuery)->open()->where('priority', 'medium')->count(),
            'low' => (clone $baseQuery)->open()->where('priority', 'low')->count(),
        ];

        $byAssetType = [
            'equipment' => (clone $baseQuery)->forEquipments()->count(),
            'truck' => (clone $baseQuery)->forTrucks()->count(),
        ];

        $overdue = (clone $baseQuery)->overdue()->count();

        $thisMonth = [
            'created' => (clone $baseQuery)->thisMonth()->count(),
            'completed' => (clone $baseQuery)->completed()
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)->count(),
            'total_cost' => (clone $baseQuery)->completed()
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->sum('total_cost'),
        ];

        $avgResolutionTime = (clone $baseQuery)->completed()
            ->whereNotNull('actual_duration')
            ->avg('actual_duration');

        return response()->json([
            'by_status' => $byStatus,
            'by_type' => $byType,
            'by_priority' => $byPriority,
            'by_asset_type' => $byAssetType,
            'overdue' => $overdue,
            'this_month' => $thisMonth,
            'avg_resolution_time' => round($avgResolutionTime ?? 0),
            'total' => array_sum($byStatus),
            'open' => $byStatus['pending'] + $byStatus['approved'] + $byStatus['assigned'] + $byStatus['in_progress'] + $byStatus['on_hold'],
        ]);
    }

    // ✅ Corrigé : accepter view_own en plus de view_any
    public function forEquipment(Request $request, Equipment $equipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('workorder:view_any') &&
            !$user->can('workorder:view_own') &&
            !$user->can('workorder:view')) {
            abort(403, 'Accès non autorisé');
        }

        $query = WorkOrder::where('equipment_id', $equipment->id)
            ->with(['assignedTo', 'requestedBy']);

        if (!$user->can('workorder:view_any')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('requested_by', $user->id);
            });
        }

        $workOrders = $query->orderByDesc('created_at')
            ->paginate($request->per_page ?? 10);

        return response()->json($workOrders);
    }

    // ✅ Corrigé : accepter view_own en plus de view_any
    public function forTruck(Request $request, Truck $truck): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('workorder:view_any') &&
            !$user->can('workorder:view_own') &&
            !$user->can('workorder:view')) {
            abort(403, 'Accès non autorisé');
        }

        $query = WorkOrder::where('truck_id', $truck->id)
            ->with(['assignedTo', 'requestedBy']);

        if (!$user->can('workorder:view_any')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('requested_by', $user->id);
            });
        }

        $workOrders = $query->orderByDesc('created_at')
            ->paginate($request->per_page ?? 10);

        return response()->json($workOrders);
    }
}
