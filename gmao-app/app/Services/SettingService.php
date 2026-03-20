<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    const CACHE_KEY = 'app_settings';
    const CACHE_TTL = 3600; // 1 heure

    /**
     * Récupérer toutes les settings groupées (avec cache)
     */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('group')
                ->map(fn($items) => $items->mapWithKeys(fn($item) => [
                    $item->key => $item->casted_value
                ]))
                ->toArray();
        });
    }

    /**
     * Récupérer une valeur spécifique
     */
    public static function get(string $group, string $key, $default = null)
    {
        $all = self::all();
        return $all[$group][$key] ?? $default;
    }

    /**
     * Récupérer un groupe entier
     */
    public static function group(string $group): array
    {
        $all = self::all();
        return $all[$group] ?? [];
    }

    /**
     * Vider le cache (à appeler après toute modification)
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Récupérer les options d'une liste (pour les selects)
     * Retourne un tableau [{value, label}] pour le frontend
     */
    public static function getListOptions(string $group, string $key): array
    {
        $value = self::get($group, $key, []);

        if (!is_array($value)) return [];

        // Si c'est un tableau associatif {key: label}
        if (array_keys($value) !== range(0, count($value) - 1)) {
            return collect($value)->map(fn($label, $val) => [
                'value' => $val,
                'label' => $label,
            ])->values()->toArray();
        }

        // Si c'est un tableau simple [value1, value2]
        return collect($value)->map(fn($val) => [
            'value' => $val,
            'label' => $val,
        ])->toArray();
    }
}
