<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Truck extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id',
        'code',
        'internal_code',
        'registration_number',
        'brand',
        'model',
        'year',
        'type',
        'capacity',
        'capacity_unit',
        'fuel_type',
        'mileage',
        'status',
        'registration_date',
        'insurance_expiry_date',
        'technical_inspection_date',
        'next_maintenance_date',
        'notes',
        'photo',
        'current_driver_id',
        'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'capacity' => 'decimal:2',
        'mileage' => 'integer',
        'registration_date' => 'date',
        'insurance_expiry_date' => 'date',
        'technical_inspection_date' => 'date',
        'next_maintenance_date' => 'date',
    ];

    protected $appends = [
        'status_label',
        'display_name',
        'alerts',
    ];

    // ===== RELATIONS =====

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function currentDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'current_driver_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function interventionRequests(): HasMany
    {
        return $this->hasMany(InterventionRequest::class);
    }

    public function preventiveMaintenances(): HasMany
    {
        return $this->hasMany(PreventiveMaintenance::class);
    }

    // ===== ACCESSORS =====

    public function getDisplayNameAttribute(): string
    {
        $prefix = $this->internal_code ? $this->internal_code . ' | ' : '';
        return $prefix . $this->registration_number . ' - ' . $this->brand . ' ' . $this->model;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'available' => 'Disponible',
            'in_use' => 'En service',
            'maintenance' => 'En maintenance',
            'out_of_service' => 'Hors service',
        ];

        return $labels[$this->status] ?? 'Inconnu';
    }

    public function getAlertsAttribute(): array
    {
        $alerts = [];

        // Alerte assurance
        if ($this->insurance_expiry_date) {
            $daysUntilExpiry = now()->diffInDays($this->insurance_expiry_date, false);
            if ($daysUntilExpiry < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Assurance expirée depuis ' . abs($daysUntilExpiry) . ' jours',
                    'category' => 'insurance'
                ];
            } elseif ($daysUntilExpiry <= 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Assurance expire dans {$daysUntilExpiry} jours",
                    'category' => 'insurance'
                ];
            }
        }

        // Alerte contrôle technique
        if ($this->technical_inspection_date) {
            $daysUntilExpiry = now()->diffInDays($this->technical_inspection_date, false);
            if ($daysUntilExpiry < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Contrôle technique expiré depuis ' . abs($daysUntilExpiry) . ' jours',
                    'category' => 'inspection'
                ];
            } elseif ($daysUntilExpiry <= 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Contrôle technique expire dans {$daysUntilExpiry} jours",
                    'category' => 'inspection'
                ];
            }
        }

        // Alerte maintenance planifiée
        if ($this->next_maintenance_date) {
            $daysUntil = now()->diffInDays($this->next_maintenance_date, false);
            if ($daysUntil < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Maintenance en retard de ' . abs($daysUntil) . ' jours',
                    'category' => 'maintenance'
                ];
            } elseif ($daysUntil <= 7) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Maintenance prévue dans {$daysUntil} jours",
                    'category' => 'maintenance'
                ];
            }
        }

        return $alerts;
    }

    // ===== SCOPES =====

    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeInMaintenance(Builder $query): Builder
    {
        return $query->where('status', 'maintenance');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
                ->orWhere('internal_code', 'like', "%{$search}%")
                ->orWhere('registration_number', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%");
        });
    }

    // ===== METHODS =====
    public function updateMileage(int $newMileage, ?string $source = null): bool
    {
        if ($newMileage < $this->mileage) {
            return false;
        }

        $this->mileage = $newMileage;
        return $this->save();
    }
    
    public function changeStatus(string $status): bool
    {
        $validStatuses = ['available', 'in_use', 'maintenance', 'out_of_service'];

        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    public function setInMaintenance(): bool
    {
        return $this->changeStatus('maintenance');
    }

    public function setActive(): bool
    {
        return $this->changeStatus('available');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public static function generateCode(int $siteId): string
    {
        $site = Site::find($siteId);
        $prefix = $site ? strtoupper(substr($site->code ?? $site->name, 0, 3)) : 'TRK';

        // ✅ withTrashed() pour inclure les camions supprimés (soft-deleted)
        $lastTruck = static::withTrashed()
            ->where('site_id', $siteId)
            ->where('code', 'like', "{$prefix}-CAM-%")
            ->orderByRaw('CAST(SUBSTRING(code, -4) AS UNSIGNED) DESC')
            ->first();

        if ($lastTruck && preg_match('/(\d+)$/', $lastTruck->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-CAM-%04d', $prefix, $nextNumber);
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($truck) {
            if (empty($truck->code)) {
                $truck->code = self::generateCode($truck->site_id);
            }
        });
    }
}
