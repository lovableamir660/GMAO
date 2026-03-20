<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EquipmentController extends Controller
{
    /**
     * Liste des équipements
     */
    public function index(Request $request): JsonResponse
    {
        $query = Equipment::query()
            ->where('site_id', $request->user()->current_site_id)
            ->with(['site', 'location']);

        // Filtres
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('criticality')) {
            $query->where('criticality', $request->criticality);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Tri
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $equipments = $query->paginate($perPage);

        return response()->json($equipments);
    }

    /**
     * Créer un équipement
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'status' => 'nullable|in:operational,degraded,stopped,maintenance,repair,out_of_service,standby',
            'location' => 'nullable|string|max:255',
            'location_id' => 'nullable|integer',
            'department' => 'nullable|string|max:100',
            'criticality' => 'nullable|in:low,medium,high,critical',
            'installation_date' => 'nullable|date',
            'warranty_expiry_date' => 'nullable|date',
            'description' => 'nullable|string',
            'acquisition_date' => 'nullable|date',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'hour_counter' => 'nullable|integer|min:0',
            'specifications' => 'nullable',
            'notes' => 'nullable|string',
            'photo' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Convertir specifications
        if (isset($validated['specifications']) && is_string($validated['specifications'])) {
            $decoded = json_decode($validated['specifications'], true);
            $validated['specifications'] = $decoded ?: null;
        }
        
        // Convertir location_id vide en null
        if (empty($validated['location_id'])) {
            $validated['location_id'] = null;
        }

        $validated['site_id'] = $request->user()->current_site_id;

        // Générer le code si non fourni
        if (empty($validated['code'])) {
            $validated['code'] = Equipment::generateCode(
                $validated['site_id'],
                $validated['type'] ?? 'EQP'
            );
        }

        $equipment = Equipment::create($validated);

        return response()->json([
            'message' => 'Équipement créé avec succès',
            'data' => $equipment->load('location')
        ], 201);
    }

    /**
     * Afficher un équipement
     */
    public function show(Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);

        $equipment->load(['site', 'location', 'workOrders', 'interventionRequests']);

        return response()->json($equipment);
    }

    /**
     * Mettre à jour un équipement
     */
    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'status' => 'nullable|in:operational,degraded,stopped,maintenance,repair,out_of_service,standby',
            'location' => 'nullable|string|max:255',
            'location_id' => 'nullable|integer',
            'department' => 'nullable|string|max:100',
            'criticality' => 'nullable|in:low,medium,high,critical',
            'installation_date' => 'nullable|date',
            'warranty_expiry_date' => 'nullable|date',
            'description' => 'nullable|string',
            'acquisition_date' => 'nullable|date',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'hour_counter' => 'nullable|integer|min:0',
            'specifications' => 'nullable',
            'notes' => 'nullable|string',
            'photo' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Convertir specifications
        if (isset($validated['specifications']) && is_string($validated['specifications'])) {
            $decoded = json_decode($validated['specifications'], true);
            $validated['specifications'] = $decoded ?: null;
        }

        // Convertir location_id vide en null
        if (array_key_exists('location_id', $validated) && empty($validated['location_id'])) {
            $validated['location_id'] = null;
        }

        $equipment->update($validated);

        return response()->json([
            'message' => 'Équipement mis à jour avec succès',
            'data' => $equipment->fresh()->load('location')
        ]);
    }

    /**
     * Supprimer un équipement
     */
    public function destroy(Equipment $equipment): JsonResponse
    {
        $this->authorize('delete', $equipment);

        $equipment->delete();

        return response()->json([
            'message' => 'Équipement supprimé avec succès'
        ]);
    }

    /**
     * Changer le statut
     */
    public function changeStatus(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $validated = $request->validate([
            'status' => 'required|in:operational,degraded,stopped,maintenance,repair,out_of_service,standby',
        ]);

        $equipment->changeStatus($validated['status']);

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'data' => $equipment->fresh()
        ]);
    }

    /**
     * Mettre à jour le compteur horaire
     */
    public function updateHourCounter(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $validated = $request->validate([
            'hour_counter' => 'required|integer|min:' . $equipment->hour_counter,
        ]);

        $equipment->updateHourCounter($validated['hour_counter']);

        return response()->json([
            'message' => 'Compteur horaire mis à jour',
            'data' => $equipment->fresh()
        ]);
    }

    /**
     * Statistiques des équipements
     */
    public function stats(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;

        $stats = [
            'total' => Equipment::where('site_id', $siteId)->count(),
            'operational' => Equipment::where('site_id', $siteId)->where('status', 'operational')->count(),
            'degraded' => Equipment::where('site_id', $siteId)->where('status', 'degraded')->count(),
            'stopped' => Equipment::where('site_id', $siteId)->where('status', 'stopped')->count(),
            'maintenance' => Equipment::where('site_id', $siteId)->where('status', 'maintenance')->count(),
            'in_maintenance' => Equipment::where('site_id', $siteId)->whereIn('status', ['maintenance', 'repair', 'degraded'])->count(),
            'out_of_service' => Equipment::where('site_id', $siteId)->whereIn('status', ['out_of_service', 'stopped'])->count(),
            'needing_maintenance' => Equipment::where('site_id', $siteId)->needingMaintenance()->count(),
        ];

        // Par type
        $stats['by_type'] = Equipment::where('site_id', $siteId)
            ->whereNotNull('type')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        // Par statut
        $stats['by_status'] = Equipment::where('site_id', $siteId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Par criticité
        $stats['by_criticality'] = Equipment::where('site_id', $siteId)
            ->whereNotNull('criticality')
            ->selectRaw('criticality, COUNT(*) as count')
            ->groupBy('criticality')
            ->pluck('count', 'criticality');

        return response()->json($stats);
    }
}
