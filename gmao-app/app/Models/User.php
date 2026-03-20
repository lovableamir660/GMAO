<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_site_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ===== RELATIONS =====

    /**
     * Site actuellement sélectionné
     */
    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    /**
     * Tous les sites autorisés pour cet utilisateur
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_user')->withTimestamps();
    }

    // ===== MÉTHODES =====

    /**
     * Vérifier si l'utilisateur a accès à un site donné
     */
    public function hasAccessToSite(int $siteId): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }
        return $this->sites()->where('sites.id', $siteId)->exists();
    }

    /**
     * Changer le site courant (avec vérification)
     */
    public function switchSite(int $siteId): bool
    {
        if (!$this->hasAccessToSite($siteId)) {
            return false;
        }

        $this->current_site_id = $siteId;
        return $this->save();
    }

    /**
     * Obtenir les IDs de tous les sites autorisés
     */
    public function authorizedSiteIds(): array
    {
        if ($this->hasRole('super-admin')) {
            return Site::pluck('id')->toArray();
        }

        return $this->sites()->pluck('sites.id')->toArray();
    }
}
