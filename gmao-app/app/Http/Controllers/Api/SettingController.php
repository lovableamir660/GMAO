<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Toutes les settings (groupées)
     */
    public function index(): JsonResponse
    {
        $settings = Setting::orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group');

        return response()->json($settings);
    }

    /**
     * Settings d'un groupe spécifique
     */
    public function group(string $group): JsonResponse
    {
        $settings = Setting::group($group)
            ->orderBy('sort_order')
            ->get();

        return response()->json($settings);
    }

    /**
     * Settings publiques (pour le frontend sans admin)
     * Retourne un format plat groupe.clé => valeur
     */
    public function publicSettings(): JsonResponse
    {
        $settings = SettingService::all();
        return response()->json($settings);
    }

    /**
     * Mettre à jour une setting
     */
    public function update(Request $request, Setting $setting): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'nullable',
        ]);

        $value = $validated['value'];

        // Encoder en JSON si c'est une liste ou du JSON
        if (in_array($setting->type, ['json', 'list']) && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $setting->update(['value' => $value]);
        SettingService::clearCache();

        return response()->json([
            'message' => 'Paramètre mis à jour',
            'setting' => $setting->fresh(),
        ]);
    }

    /**
     * Mise à jour en lot (par groupe)
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $item) {
            $setting = Setting::find($item['id']);
            $value = $item['value'];

            if (in_array($setting->type, ['json', 'list']) && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $setting->update(['value' => $value]);
        }

        SettingService::clearCache();

        return response()->json([
            'message' => count($validated['settings']) . ' paramètre(s) mis à jour',
        ]);
    }

    /**
     * Créer un nouveau paramètre (ex: ajouter un type personnalisé)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => 'required|string|max:50',
            'key' => 'required|string|max:100',
            'value' => 'nullable',
            'type' => 'required|in:string,integer,boolean,json,list',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Vérifier unicité
        $exists = Setting::where('group', $validated['group'])
            ->where('key', $validated['key'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ce paramètre existe déjà'], 422);
        }

        if (in_array($validated['type'], ['json', 'list']) && is_array($validated['value'])) {
            $validated['value'] = json_encode($validated['value'], JSON_UNESCAPED_UNICODE);
        }

        $setting = Setting::create($validated);
        SettingService::clearCache();

        return response()->json([
            'message' => 'Paramètre créé',
            'setting' => $setting,
        ], 201);
    }

    /**
     * Supprimer un paramètre (sauf system)
     */
    public function destroy(Setting $setting): JsonResponse
    {
        if ($setting->is_system) {
            return response()->json(['message' => 'Paramètre système non supprimable'], 403);
        }

        $setting->delete();
        SettingService::clearCache();

        return response()->json(['message' => 'Paramètre supprimé']);
    }

    /**
     * Réinitialiser un groupe aux valeurs par défaut
     */
    public function resetGroup(string $group): JsonResponse
    {
        // Appeler le seeder pour ce groupe
        $seeder = new \Database\Seeders\SettingsSeeder();
        $seeder->seedGroup($group);

        SettingService::clearCache();

        return response()->json(['message' => "Groupe '$group' réinitialisé"]);
    }

    /**
     * Liste des options pour les selects (endpoint public)
     */
    public function options(string $group, string $key): JsonResponse
    {
        $options = SettingService::getListOptions($group, $key);
        return response()->json($options);
    }
}
