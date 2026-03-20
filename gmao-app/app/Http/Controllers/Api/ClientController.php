<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::where('site_id', $request->user()->current_site_id)
            ->withCount('habilitations');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('city', 'like', "%{$request->search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $clients = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($clients);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $siteId = $request->user()->current_site_id;
        $validated['site_id'] = $siteId;
        $validated['code'] = Client::generateCode($siteId);

        $client = Client::create($validated);

        return response()->json([
            'message' => 'Client créé avec succès',
            'client' => $client,
        ], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load(['habilitations' => function ($q) {
            $q->where('is_active', true);
        }]);
        $client->loadCount('habilitations');

        return response()->json($client);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $client->update($validated);

        return response()->json([
            'message' => 'Client mis à jour',
            'client' => $client,
        ]);
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(['message' => 'Client supprimé']);
    }

    public function list(Request $request): JsonResponse
    {
        $clients = Client::where('site_id', $request->user()->current_site_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json($clients);
    }
}
