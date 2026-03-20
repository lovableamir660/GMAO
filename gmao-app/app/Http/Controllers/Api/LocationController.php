<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Location::where('site_id', $request->user()->current_site_id)
            ->with('parent:id,name,code')
            ->withCount('children', 'equipments');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->parent_id) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        $locations = $query->orderBy('name')->paginate($request->per_page ?? 20);

        return response()->json($locations);
    }

    /**
     * Liste simple pour les selects
     */
    public function list(Request $request): JsonResponse
    {
        $locations = Location::where('site_id', $request->user()->current_site_id)
            ->where('is_active', true)
            ->with('parent:id,name,code')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id']);

        return response()->json($locations);
    }

    /**
     * Arborescence complète
     */
    public function tree(Request $request): JsonResponse
    {
        $locations = Location::where('site_id', $request->user()->current_site_id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with('childrenRecursive')
            ->withCount('equipments')
            ->orderBy('name')
            ->get();

        return response()->json($locations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:locations,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $siteId = $request->user()->current_site_id;

        // Générer le code automatiquement si non fourni
        if (empty($validated['code'])) {
            $prefix = 'LOC';
            $lastLocation = Location::where('site_id', $siteId)
                ->where('code', 'like', "{$prefix}-%")
                ->orderByDesc('id')
                ->first();

            $nextNum = 1;
            if ($lastLocation && preg_match("/{$prefix}-(\d+)/", $lastLocation->code, $matches)) {
                $nextNum = intval($matches[1]) + 1;
            }
            $validated['code'] = sprintf("{$prefix}-%03d", $nextNum);
        }

        $validated['site_id'] = $siteId;

        $location = Location::create($validated);
        $location->load('parent:id,name,code');

        return response()->json($location, 201);
    }

    public function show(Location $location): JsonResponse
    {
        $location->load([
            'parent:id,name,code',
            'children:id,parent_id,name,code,is_active',
            'equipments:id,location_id,code,name,status',
        ]);
        $location->loadCount(['children', 'equipments']);

        return response()->json($location);
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:locations,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Empêcher de se mettre comme propre parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $location->id) {
            return response()->json(['message' => 'Un emplacement ne peut pas être son propre parent'], 422);
        }

        $location->update($validated);
        $location->load('parent:id,name,code');

        return response()->json($location);
    }

    public function destroy(Location $location): JsonResponse
    {
        // Vérifier s'il y a des équipements
        if ($location->equipments()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer : des équipements sont liés à cet emplacement'
            ], 422);
        }

        // Vérifier s'il y a des enfants
        if ($location->children()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer : cet emplacement contient des sous-emplacements'
            ], 422);
        }

        $location->delete();

        return response()->json(null, 204);
    }
}
