<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Charger les permissions d'un rôle via requête directe (contourne le filtre teams)
     */
    private function loadPermissionsForRole($role)
    {
        $permissions = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $role->id)
            ->select('permissions.id', 'permissions.name')
            ->get();

        $role->setRelation('permissions', $permissions);

        return $role;
    }

    /**
     * Liste des rôles avec leurs permissions
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('role:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $roles = Role::all();

        $roles->each(function ($role) {
            $this->loadPermissionsForRole($role);
        });

        return response()->json($roles);
    }

    /**
     * Détails d'un rôle
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('role:view')) {
            abort(403, 'Accès non autorisé');
        }

        $this->loadPermissionsForRole($role);

        return response()->json($role);
    }

    /**
     * Créer un nouveau rôle
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('role:create')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        if (!empty($validated['permissions'])) {
            // Insérer directement dans la table pivot
            $permissionIds = DB::table('permissions')
                ->whereIn('name', $validated['permissions'])
                ->pluck('id');

            $inserts = $permissionIds->map(fn($id) => [
                'permission_id' => $id,
                'role_id' => $role->id,
            ])->toArray();

            DB::table('role_has_permissions')->insert($inserts);
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->loadPermissionsForRole($role);

        return response()->json([
            'message' => 'Rôle créé avec succès',
            'role' => $role,
        ], 201);
    }

    /**
     * Mettre à jour un rôle (permissions)
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('role:update')) {
            abort(403, 'Accès non autorisé');
        }

        if ($role->name === 'SuperAdmin') {
            return response()->json([
                'message' => 'Le rôle SuperAdmin ne peut pas être modifié',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (array_key_exists('permissions', $validated)) {
            // Supprimer les anciennes permissions
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();

            // Insérer les nouvelles
            if (!empty($validated['permissions'])) {
                $permissionIds = DB::table('permissions')
                    ->whereIn('name', $validated['permissions'])
                    ->pluck('id');

                $inserts = $permissionIds->map(fn($id) => [
                    'permission_id' => $id,
                    'role_id' => $role->id,
                ])->toArray();

                DB::table('role_has_permissions')->insert($inserts);
            }

            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->loadPermissionsForRole($role);

        return response()->json([
            'message' => 'Rôle mis à jour avec succès',
            'role' => $role,
        ]);
    }

    /**
     * Supprimer un rôle
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('role:delete')) {
            abort(403, 'Accès non autorisé');
        }

        $protected = ['SuperAdmin', 'AdminSite', 'Technicien', 'Planificateur', 'Magasinier', 'Lecteur'];

        if (in_array($role->name, $protected)) {
            return response()->json([
                'message' => 'Ce rôle système ne peut pas être supprimé',
            ], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Ce rôle est encore assigné à des utilisateurs',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rôle supprimé avec succès',
        ]);
    }

    /**
     * Liste de toutes les permissions groupées par module (dynamique depuis la table)
     */
    public function permissions(Request $request): JsonResponse
    {
        if (!$request->user()->can('role:view')) {
            abort(403, 'Accès non autorisé');
        }

        // Requête directe pour contourner le scope teams
        $permissions = DB::table('permissions')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Grouper par module (avant le ":")
        $grouped = [];
        $modules = [];

        foreach ($permissions as $perm) {
            $parts = explode(':', $perm->name);
            $module = $parts[0] ?? 'other';
            $action = $parts[1] ?? $perm->name;

            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }

            $grouped[$module][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'module' => $module,
                'action' => $action,
            ];

            // Collecter les modules uniques
            if (!in_array($module, $modules)) {
                $modules[] = $module;
            }
        }

        // Collecter les actions uniques
        $actions = [];
        foreach ($permissions as $perm) {
            $parts = explode(':', $perm->name);
            $action = $parts[1] ?? $perm->name;
            if (!in_array($action, $actions)) {
                $actions[] = $action;
            }
        }

        return response()->json([
            'permissions' => $permissions,
            'grouped' => $grouped,
            'modules' => $modules,
            'actions' => $actions,
        ]);
    }

}
