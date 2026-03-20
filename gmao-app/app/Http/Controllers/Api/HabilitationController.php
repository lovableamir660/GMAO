<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habilitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HabilitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Habilitation::where('site_id', $request->user()->current_site_id)
            ->with('client:id,name,code')
            // Ajouter les compteurs de chauffeurs
            ->withCount(['driverHabilitations as drivers_count'])
            ->withCount(['driverHabilitations as valid_count' => function ($q) {
                $q->where(function ($query) {
                    $query->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>', now());
                });
            }])
            ->withCount(['driverHabilitations as expiring_soon_count' => function ($q) {
                $q->whereNotNull('expiry_date')
                  ->where('expiry_date', '>', now())
                  ->where('expiry_date', '<=', now()->addDays(30));
            }])
            ->withCount(['driverHabilitations as expired_count' => function ($q) {
                $q->whereNotNull('expiry_date')
                  ->where('expiry_date', '<=', now());
            }]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        if ($request->client_id) {
            if ($request->client_id === 'null') {
                $query->whereNull('client_id');
            } else {
                $query->where('client_id', $request->client_id);
            }
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_mandatory') && $request->is_mandatory !== '') {
            $query->where('is_mandatory', $request->boolean('is_mandatory'));
        }

        $habilitations = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($habilitations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'validity_months' => 'nullable|integer|min:1',
            'renewal_notice_days' => 'nullable|integer|min:1',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $siteId = $request->user()->current_site_id;
        $validated['site_id'] = $siteId;
        $validated['code'] = Habilitation::generateCode($siteId);

        $habilitation = Habilitation::create($validated);

        return response()->json([
            'message' => 'Habilitation créée avec succès',
            'habilitation' => $habilitation,
        ], 201);
    }

    public function show(Habilitation $habilitation): JsonResponse
    {
        $habilitation->load('client:id,name,code');
        
        // Charger les compteurs
        $habilitation->loadCount(['driverHabilitations as drivers_count']);
        $habilitation->loadCount(['driverHabilitations as valid_count' => function ($q) {
            $q->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            });
        }]);
        $habilitation->loadCount(['driverHabilitations as expiring_soon_count' => function ($q) {
            $q->whereNotNull('expiry_date')
              ->where('expiry_date', '>', now())
              ->where('expiry_date', '<=', now()->addDays(30));
        }]);
        $habilitation->loadCount(['driverHabilitations as expired_count' => function ($q) {
            $q->whereNotNull('expiry_date')
              ->where('expiry_date', '<=', now());
        }]);

        // Charger les chauffeurs avec cette habilitation
        $habilitation->load(['driverHabilitations' => function ($query) {
            $query->with(['driver:id,first_name,last_name,matricule'])
                  ->orderBy('expiry_date');
        }]);

        // Transformer pour le frontend
        $habilitation->drivers = $habilitation->driverHabilitations->map(function ($dh) {
            return [
                'id' => $dh->id,
                'driver' => $dh->driver,
                'obtained_date' => $dh->obtained_date,
                'expiry_date' => $dh->expiry_date,
                'is_expired' => $dh->expiry_date && Carbon::parse($dh->expiry_date)->isPast(),
                'is_expiring_soon' => $dh->expiry_date && 
                    Carbon::parse($dh->expiry_date)->isFuture() && 
                    Carbon::parse($dh->expiry_date)->lte(now()->addDays(30)),
            ];
        });

        return response()->json($habilitation);
    }

    public function update(Request $request, Habilitation $habilitation): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'validity_months' => 'nullable|integer|min:1',
            'renewal_notice_days' => 'nullable|integer|min:1',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $habilitation->update($validated);

        return response()->json([
            'message' => 'Habilitation mise à jour',
            'habilitation' => $habilitation,
        ]);
    }

    public function destroy(Habilitation $habilitation): JsonResponse
    {
        // Vérifier si des chauffeurs ont cette habilitation
        $driversCount = $habilitation->driverHabilitations()->count();
        if ($driversCount > 0) {
            return response()->json([
                'message' => "Impossible de supprimer : {$driversCount} chauffeur(s) possède(nt) cette habilitation"
            ], 422);
        }

        $habilitation->delete();

        return response()->json(['message' => 'Habilitation supprimée']);
    }

    public function list(Request $request): JsonResponse
    {
        $query = Habilitation::where('site_id', $request->user()->current_site_id)
            ->where('is_active', true);

        if ($request->client_id) {
            $query->where(function ($q) use ($request) {
                $q->where('client_id', $request->client_id)
                  ->orWhereNull('client_id');
            });
        }

        $habilitations = $query->orderBy('name')
            ->get(['id', 'code', 'name', 'client_id', 'is_mandatory', 'validity_months']);

        return response()->json($habilitations);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = Habilitation::where('site_id', $request->user()->current_site_id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return response()->json($categories);
    }
}
