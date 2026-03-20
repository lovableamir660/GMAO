<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'city',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'contact_name',
        'contact_phone',
        'contact_email',
        'site_type',
        'capacity',
        'operating_hours',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'country' => 'Algérie',
        'site_type' => 'other',
        'is_active' => true,
    ];

    /**
     * Scope pour les sites actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('site_type', $type);
    }

    /**
     * Adresse complète formatée
     */
    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->postal_code,
            $this->city,
            $this->country,
        ]));
    }
}
