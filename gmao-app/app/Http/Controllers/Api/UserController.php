<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('user:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $users = User::query()
            ->with(['currentSite', 'roles'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->site_id, function ($query, $siteId) {
                $query->where('current_site_id', $siteId);
            })
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json($users);
    }

    /**
     * Mettre à jour les sites d'un utilisateur
     */
    public function updateSites(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('user:update')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'site_ids' => 'required|array',
            'site_ids.*' => 'exists:sites,id',
        ]);

        // Sync remplace toutes les associations existantes
        $user->sites()->sync($validated['site_ids']);

        // Si le site courant n'est plus dans la liste, mettre le premier
        if (!in_array($user->current_site_id, $validated['site_ids'])) {
            $user->current_site_id = $validated['site_ids'][0] ?? null;
            $user->save();
        }

        return response()->json([
            'message' => 'Sites mis à jour avec succès',
            'user' => $user->load('sites'),
        ]);
    }


    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('user:create')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'current_site_id' => 'required|exists:sites,id',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'current_site_id' => $validated['current_site_id'],
        ]);

        // Assigner les rôles pour le site
        if (!empty($validated['roles'])) {
            app()[PermissionRegistrar::class]->setPermissionsTeamId($validated['current_site_id']);
            foreach ($validated['roles'] as $roleName) {
                $user->assignRole($roleName);
            }
        }

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => $user->load(['currentSite', 'roles']),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('user:view')) {
            abort(403, 'Accès non autorisé');
        }

        $user->load(['currentSite', 'roles']);

        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('user:update')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'current_site_id' => 'sometimes|required|exists:sites,id',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Utilisateur mis à jour avec succès',
            'user' => $user->load(['currentSite', 'roles']),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('user:delete')) {
            abort(403, 'Accès non autorisé');
        }

        // Empêcher la suppression de soi-même
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé avec succès',
        ]);
    }

    /**
     * Gérer les rôles d'un utilisateur pour un site spécifique
     */
    public function updateRoles(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('user:assign_roles')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $siteId = $validated['site_id'];

        // Définir le contexte du site
        app()[PermissionRegistrar::class]->setPermissionsTeamId($siteId);

        // Supprimer les anciens rôles pour ce site
        $user->roles()->wherePivot('site_id', $siteId)->detach();

        // Assigner les nouveaux rôles
        foreach ($validated['roles'] as $roleName) {
            $user->assignRole($roleName);
        }

        return response()->json([
            'message' => 'Rôles mis à jour avec succès',
            'user' => $user->fresh(['currentSite', 'roles']),
        ]);
    }

    /**
     * Liste des rôles disponibles
     */
    public function getRoles(Request $request): JsonResponse
    {
        $roles = Role::all(['id', 'name']);

        return response()->json($roles);
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request, User $user): JsonResponse
    {
        // L'utilisateur peut changer son propre mot de passe ou un admin peut changer celui des autres
        if ($user->id !== $request->user()->id && !$request->user()->can('user:update')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'current_password' => $user->id === $request->user()->id ? 'required' : 'nullable',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Vérifier le mot de passe actuel si l'utilisateur change son propre mot de passe
        if ($user->id === $request->user()->id) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Le mot de passe actuel est incorrect',
                ], 422);
            }
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Mot de passe changé avec succès',
        ]);
    }
}
