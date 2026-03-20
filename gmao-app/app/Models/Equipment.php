<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'site_id',
        'code',
        'name',
        'type',
        'category',
        'brand',
        'model',
        'serial_number',
        'year',
        'status',
        'location',
        'location_id',
        'department',
        'criticality',
        'installation_date',
        'warranty_expiry_date',
        'description',
        'acquisition_date',
        'acquisition_cost',
        'warranty_expiry',
        'last_maintenance_date',
        'next_maintenance_date',
        'hour_counter',
        'specifications',
        'notes',
        'photo',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'warranty_expiry' => 'date',
        'warranty_expiry_date' => 'date',
        'installation_date' => 'date',
        'last_maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
        'hour_counter' => 'integer',
        'specifications' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'status_label',
        'type_label',
        'display_name',
        'alerts',
        'pending_maintenances_count',
    ];

    // ===== RELATIONS =====

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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
        return $this->code . ' - ' . $this->name;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'operational' => 'Opérationnel',
            'degraded' => 'Dégradé',
            'stopped' => 'Arrêté',
            'maintenance' => 'En maintenance',
            'repair' => 'En réparation',
            'out_of_service' => 'Hors service',
            'standby' => 'En veille',
        ];

        return $labels[$this->status] ?? 'Inconnu';
    }

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'Machine' => 'Machine',
            'Moteur' => 'Moteur',
            'Pompe' => 'Pompe',
            'Compresseur' => 'Compresseur',
            'Convoyeur' => 'Convoyeur',
            'Robot' => 'Robot',
            'Automate' => 'Automate',
            'Vérin' => 'Vérin',
            'Ventilateur' => 'Ventilateur',
            'Transformateur' => 'Transformateur',
            'Groupe électrogène' => 'Groupe électrogène',
            'Chariot élévateur' => 'Chariot élévateur',
            'Pont roulant' => 'Pont roulant',
            'Autre' => 'Autre',
        ];

        return $labels[$this->type] ?? $this->type ?? 'Autre';
    }

    public function getPendingMaintenancesCountAttribute(): int
    {
        try {
            return $this->preventiveMaintenances()
                ->where('is_active', true)
                ->whereDate('next_execution_date', '<=', now())
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getAlertsAttribute(): array
    {
        $alerts = [];

        // Alerte garantie
        $warrantyDate = $this->warranty_expiry_date ?? $this->warranty_expiry;
        if ($warrantyDate) {
            $daysUntilExpiry = now()->diffInDays($warrantyDate, false);
            if ($daysUntilExpiry < 0) {
                $alerts[] = [
                    'type' => 'info',
                    'message' => 'Garantie expirée depuis ' . abs($daysUntilExpiry) . ' jours',
                    'category' => 'warranty'
                ];
            } elseif ($daysUntilExpiry <= 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Garantie expire dans {$daysUntilExpiry} jours",
                    'category' => 'warranty'
                ];
            }
        }

        // Alerte maintenance planifiée
        if ($this->next_maintenance_date) {
            $daysUntilMaintenance = now()->diffInDays($this->next_maintenance_date, false);
            if ($daysUntilMaintenance < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Maintenance en retard de ' . abs($daysUntilMaintenance) . ' jours',
                    'category' => 'maintenance'
                ];
            } elseif ($daysUntilMaintenance <= 7) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Maintenance prévue dans {$daysUntilMaintenance} jours",
                    'category' => 'maintenance'
                ];
            }
        }

        // Alerte maintenances préventives en retard
        try {
            $overdueCount = $this->preventiveMaintenances()
                ->where('is_active', true)
                ->whereDate('next_execution_date', '<', now())
                ->count();

            if ($overdueCount > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "{$overdueCount} maintenance(s) préventive(s) en retard",
                    'category' => 'preventive'
                ];
            }
        } catch (\Exception $e) {
        }

        // Alerte statut non opérationnel
        if (in_array($this->status, ['out_of_service', 'stopped'])) {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'Équipement hors service',
                'category' => 'status'
            ];
        } elseif (in_array($this->status, ['maintenance', 'repair', 'degraded'])) {
            $alerts[] = [
                'type' => 'warning',
                'message' => $this->status_label,
                'category' => 'status'
            ];
        }

        return $alerts;
    }

    // ===== SCOPES =====

    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('status', 'operational')->where('is_active', true);
    }

    public function scopeInMaintenance(Builder $query): Builder
    {
        return $query->whereIn('status', ['maintenance', 'repair', 'degraded']);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByCriticality(Builder $query, string $criticality): Builder
    {
        return $query->where('criticality', $criticality);
    }

    public function scopeByLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeNeedingMaintenance(Builder $query): Builder
    {
        return $query->whereNotNull('next_maintenance_date')
            ->whereDate('next_maintenance_date', '<=', now());
    }

    public function scopeWithExpiredWarranty(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNotNull('warranty_expiry_date')
              ->whereDate('warranty_expiry_date', '<', now());
        })->orWhere(function ($q) {
            $q->whereNotNull('warranty_expiry')
              ->whereDate('warranty_expiry', '<', now());
        });
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%")
              ->orWhere('brand', 'like', "%{$search}%")
              ->orWhere('model', 'like', "%{$search}%")
              ->orWhere('serial_number', 'like', "%{$search}%")
              ->orWhere('location', 'like', "%{$search}%")
              ->orWhere('department', 'like', "%{$search}%");
        });
    }

    // ===== METHODS =====

    public function updateHourCounter(int $hours): bool
    {
        if ($hours < $this->hour_counter) {
            return false;
        }

        $this->hour_counter = $hours;
        return $this->save();
    }

    public function changeStatus(string $status): bool
    {
        $validStatuses = ['operational', 'degraded', 'stopped', 'maintenance', 'repair', 'out_of_service', 'standby'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    // ✅ AJOUTÉ : Passer en maintenance (appelé par WorkOrderController & InterventionRequest)
    public function setInMaintenance(): bool
    {
        return $this->changeStatus('maintenance');
    }

    // ✅ AJOUTÉ : Remettre en opérationnel (appelé par WorkOrderController)
    public function setActive(): bool
    {
        return $this->changeStatus('operational');
    }

    public function isOperational(): bool
    {
        return $this->status === 'operational' && $this->is_active;
    }

    public static function generateCode(int $siteId, string $type = 'EQP'): string
    {
        $site = Site::find($siteId);
        $prefix = $site ? strtoupper(substr($site->code ?? $site->name, 0, 3)) : 'EQP';
        
        $typePrefix = strtoupper(substr($type, 0, 3));
        
        $lastEquipment = static::where('site_id', $siteId)
            ->where('code', 'like', "{$prefix}-{$typePrefix}-%")
            ->orderByRaw('CAST(SUBSTRING(code, -4) AS UNSIGNED) DESC')
            ->first();

        if ($lastEquipment && preg_match('/(\d+)$/', $lastEquipment->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $typePrefix, $nextNumber);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($equipment) {
            if (empty($equipment->code)) {
                $equipment->code = self::generateCode(
                    $equipment->site_id, 
                    $equipment->type ?? 'EQP'
                );
            }
        });
    }
}
