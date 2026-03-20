<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group', 'key', 'value', 'type', 'label', 'description', 'is_system', 'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Retourne la valeur castée selon le type
     */
    public function getCastedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json', 'list' => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }

    /**
     * Scope pour un groupe
     */
    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Récupérer une valeur par groupe.clé
     */
    public static function getValue(string $group, string $key, $default = null)
    {
        $setting = static::where('group', $group)->where('key', $key)->first();
        return $setting ? $setting->casted_value : $default;
    }
}
