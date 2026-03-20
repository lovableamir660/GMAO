<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('part:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $siteId = $request->user()->current_site_id;

        $parts = Part::query()
            ->where('site_id', $siteId)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($query, $category) {
                $query->where('category', $category);
            })
            ->when($request->boolean('low_stock'), function ($query) {
                $query->whereColumn('quantity_in_stock', '<=', 'minimum_stock');
            })
            ->when($request->boolean('out_of_stock'), function ($query) {
                $query->where('quantity_in_stock', '<=', 0);
            })
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json($parts);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('part:create')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:parts,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'quantity_in_stock' => 'nullable|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'maximum_stock' => 'nullable|integer|min:0',
            'location_in_warehouse' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'manufacturer_reference' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['site_id'] = $request->user()->current_site_id;

        $part = Part::create($validated);

        // Si stock initial, créer un mouvement
        if ($part->quantity_in_stock > 0) {
            StockMovement::create([
                'site_id' => $part->site_id,
                'part_id' => $part->id,
                'user_id' => $request->user()->id,
                'type' => 'in',
                'quantity' => $part->quantity_in_stock,
                'quantity_before' => 0,
                'quantity_after' => $part->quantity_in_stock,
                'unit_price' => $part->unit_price,
                'reason' => 'Stock initial',
            ]);
        }

        return response()->json([
            'message' => 'Pièce créée avec succès',
            'part' => $part,
        ], 201);
    }

    public function show(Request $request, Part $part): JsonResponse
    {
        if (!$request->user()->can('part:view')) {
            abort(403, 'Accès non autorisé');
        }

        if ($part->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette pièce n\'appartient pas à votre site');
        }

        $part->load(['equipments', 'stockMovements' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json($part);
    }

    public function update(Request $request, Part $part): JsonResponse
    {
        if (!$request->user()->can('part:update')) {
            abort(403, 'Accès non autorisé');
        }

        if ($part->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette pièce n\'appartient pas à votre site');
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:parts,code,' . $part->id,
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'maximum_stock' => 'nullable|integer|min:0',
            'location_in_warehouse' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'manufacturer_reference' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $part->update($validated);

        return response()->json([
            'message' => 'Pièce mise à jour avec succès',
            'part' => $part,
        ]);
    }

    public function destroy(Request $request, Part $part): JsonResponse
    {
        if (!$request->user()->can('part:delete')) {
            abort(403, 'Accès non autorisé');
        }

        if ($part->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette pièce n\'appartient pas à votre site');
        }

        $part->delete();

        return response()->json([
            'message' => 'Pièce supprimée avec succès',
        ]);
    }

    // Ajuster le stock
    public function adjustStock(Request $request, Part $part): JsonResponse
    {
        if (!$request->user()->can('stock:adjust')) {
            abort(403, 'Accès non autorisé');
        }

        if ($part->site_id !== $request->user()->current_site_id) {
            abort(403, 'Cette pièce n\'appartient pas à votre site');
        }

        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
            'reference' => 'nullable|string|max:255',
        ]);

        $quantityBefore = $part->quantity_in_stock;
        
        if ($validated['type'] === 'out') {
            if ($part->quantity_in_stock < $validated['quantity']) {
                return response()->json([
                    'message' => 'Stock insuffisant',
                ], 422);
            }
            $part->quantity_in_stock -= $validated['quantity'];
        } else {
            $part->quantity_in_stock += $validated['quantity'];
        }

        DB::transaction(function () use ($part, $validated, $quantityBefore, $request) {
            $part->save();

            StockMovement::create([
                'site_id' => $part->site_id,
                'part_id' => $part->id,
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'quantity' => $validated['type'] === 'out' ? -$validated['quantity'] : $validated['quantity'],
                'quantity_before' => $quantityBefore,
                'quantity_after' => $part->quantity_in_stock,
                'unit_price' => $part->unit_price,
                'reference' => $validated['reference'] ?? null,
                'reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'message' => 'Stock ajusté avec succès',
            'part' => $part->fresh(),
        ]);
    }
}
