<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverHabilitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Driver::where('site_id', $request->user()->current_site_id)
            ->with(['currentTruck:id,code,registration_number', 'habilitations.habilitation:id,name,code']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $drivers = $query->orderBy('last_name')->paginate($request->per_page ?? 15);

        return response()->json($drivers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'license_number' => 'nullable|string|max:100',
            'license_type' => 'nullable|string|max:50',
            'license_expiry_date' => 'nullable|date',
            'medical_checkup_date' => 'nullable|date',
            'hire_date' => 'nullable|date',
            'status' => 'in:active,inactive,suspended',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $siteId = $request->user()->current_site_id;
        $validated['site_id'] = $siteId;
        $validated['code'] = Driver::generateCode($siteId);
        $validated['created_by'] = $request->user()->id;

        $driver = Driver::create($validated);

        return response()->json([
            'message' => 'Chauffeur créé avec succès',
            'driver' => $driver,
        ], 201);
    }

    public function show(Driver $driver): JsonResponse
    {
        $driver->load([
            'currentTruck',
            'habilitations.habilitation.client:id,name',
            'habilitations.verifiedBy:id,name',
            'createdBy:id,name',
        ]);

        return response()->json($driver);
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'license_number' => 'nullable|string|max:100',
            'license_type' => 'nullable|string|max:50',
            'license_expiry_date' => 'nullable|date',
            'medical_checkup_date' => 'nullable|date',
            'hire_date' => 'nullable|date',
            'status' => 'in:active,inactive,suspended',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $driver->update($validated);

        return response()->json([
            'message' => 'Chauffeur mis à jour',
            'driver' => $driver,
        ]);
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $driver->delete();

        return response()->json(['message' => 'Chauffeur supprimé']);
    }

    public function list(Request $request): JsonResponse
    {
        $drivers = Driver::where('site_id', $request->user()->current_site_id)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->get(['id', 'code', 'first_name', 'last_name']);

        return response()->json($drivers);
    }

    // Habilitations du chauffeur
    public function addHabilitation(Request $request, Driver $driver): JsonResponse
    {
        $validated = $request->validate([
            'habilitation_id' => 'required|exists:habilitations,id',
            'obtained_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:obtained_date',
            'certificate_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $validated['driver_id'] = $driver->id;
        $validated['status'] = 'valid';
        $validated['verified_by'] = $request->user()->id;
        $validated['verified_at'] = now();

        // Vérifier si déjà existante
        $existing = DriverHabilitation::where('driver_id', $driver->id)
            ->where('habilitation_id', $validated['habilitation_id'])
            ->first();

        if ($existing) {
            $existing->update($validated);
            $driverHab = $existing;
            $message = 'Habilitation mise à jour';
        } else {
            $driverHab = DriverHabilitation::create($validated);
            $message = 'Habilitation ajoutée';
        }

        $driverHab->load('habilitation');

        return response()->json([
            'message' => $message,
            'driver_habilitation' => $driverHab,
        ]);
    }

    public function removeHabilitation(Driver $driver, DriverHabilitation $habilitation): JsonResponse
    {
        if ($habilitation->driver_id !== $driver->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $habilitation->delete();

        return response()->json(['message' => 'Habilitation retirée']);
    }

    public function checkClientEligibility(Request $request, Driver $driver): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
        ]);

        $clientId = $request->client_id;
        $isEligible = $driver->hasAllClientHabilitations($clientId);
        $missingHabilitations = $driver->getMissingHabilitations($clientId);

        return response()->json([
            'is_eligible' => $isEligible,
            'missing_habilitations' => $missingHabilitations,
        ]);
    }

    public function expiringHabilitations(Request $request): JsonResponse
    {
        $days = $request->days ?? 30;

        $expiring = DriverHabilitation::whereHas('driver', function ($q) use ($request) {
                $q->where('site_id', $request->user()->current_site_id);
            })
            ->with(['driver:id,code,first_name,last_name', 'habilitation:id,code,name'])
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)])
            ->orderBy('expiry_date')
            ->get();

        return response()->json($expiring);
    }

    public function expiredHabilitations(Request $request): JsonResponse
    {
        $expired = DriverHabilitation::whereHas('driver', function ($q) use ($request) {
                $q->where('site_id', $request->user()->current_site_id);
            })
            ->with(['driver:id,code,first_name,last_name', 'habilitation:id,code,name'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->orderBy('expiry_date')
            ->get();

        return response()->json($expired);
    }
}
