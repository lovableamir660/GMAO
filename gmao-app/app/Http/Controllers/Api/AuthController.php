<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    /**
     * Login utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        $user = Auth::user();

        // Définir le site courant pour Spatie
        if ($user->current_site_id) {
            app()[PermissionRegistrar::class]->setPermissionsTeamId($user->current_site_id);
        }

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $this->getUserData($user),
        ]);
    }

    /**
     * Logout utilisateur
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * Récupérer l'utilisateur connecté
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->current_site_id) {
            app()[PermissionRegistrar::class]->setPermissionsTeamId($user->current_site_id);
        }

        return response()->json([
            'user' => $this->getUserData($user),
        ]);
    }

    /**
     * Changer de site courant (avec vérification d'accès)
     */
    public function switchSite(Request $request): JsonResponse
    {
        $request->validate([
            'site_id' => 'required|exists:sites,id',
        ]);

        $user = $request->user();

        // ✅ Vérifier que l'utilisateur a accès à ce site
        if (!$user->hasAccessToSite($request->site_id)) {
            return response()->json([
                'message' => 'Vous n\'avez pas accès à ce site',
            ], 403);
        }

        $user->update(['current_site_id' => $request->site_id]);

        // Mettre à jour le contexte Spatie
        app()[PermissionRegistrar::class]->setPermissionsTeamId($request->site_id);

        return response()->json([
            'message' => 'Site changé avec succès',
            'user' => $this->getUserData($user->fresh()),
        ]);
    }

    /**
     * Formater les données utilisateur avec rôles, permissions et sites autorisés
     */
    private function getUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'current_site_id' => $user->current_site_id,
            'current_site' => $user->currentSite,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            // ✅ AJOUTÉ : liste des sites autorisés
            'authorized_sites' => $this->getAuthorizedSites($user),
        ];
    }

    /**
     * Récupérer les sites autorisés pour un utilisateur
     */
    private function getAuthorizedSites(User $user): \Illuminate\Support\Collection
    {
        if ($user->hasRole('super-admin')) {
            return Site::active()->orderBy('name')->get(['id', 'code', 'name', 'city', 'site_type']);
        }

        return $user->sites()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['sites.id', 'sites.code', 'sites.name', 'sites.city', 'sites.site_type']);
    }
}
