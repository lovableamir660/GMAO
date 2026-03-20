<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PreventiveMaintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'asset_type',
        'equipment_id',
        'truck_id',
        'code',
        'name',
        'description',
        'frequency_type',
        'frequency_value',
        'counter_threshold',
        'counter_unit',
        'mileage_interval',
        'last_mileage',
        'next_mileage',
        'start_date',
        'end_date',
        'last_execution_date',
        'next_execution_date',
        'priority',
        'estimated_duration',
        'assigned_to',
        'is_active',
        'advance_days',
        'advance_mileage',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'last_execution_date' => 'date',
            'next_execution_date' => 'date',
            'is_active' => 'boolean',
            'mileage_interval' => 'integer',
            'last_mileage' => 'integer',
            'next_mileage' => 'integer',
            'advance_mileage' => 'integer',
        ];
    }

    protected $appends = ['asset_name', 'asset_code', 'frequency_label', 'is_due', 'is_due_soon', 'status'];

    protected $attributes = [
        'asset_type' => 'equipment',
        'is_active' => true,
        'priority' => 'medium',
        'advance_days' => 7,
    ];

    // ===== ACCESSEURS =====

    public function getAssetAttribute()
    {
        return $this->asset_type === 'truck' ? $this->truck : $this->equipment;
    }

    public function getAssetNameAttribute(): ?string
    {
        if ($this->asset_type === 'truck') {
            return $this->truck?->display_name;
        }
        return $this->equipment?->name;
    }

    public function getAssetCodeAttribute(): ?string
    {
        if ($this->asset_type === 'truck') {
            return $this->truck?->code;
        }
        return $this->equipment?->code;
    }

    public function getFrequencyLabelAttribute(): string
    {
        $labels = [
            'daily' => 'jour(s)',
            'weekly' => 'semaine(s)',
            'monthly' => 'mois',
            'yearly' => 'an(s)',
            'counter' => $this->counter_unit ?? 'unités',
            'mileage' => 'km',
        ];

        $unit = $labels[$this->frequency_type] ?? '';

        if ($this->frequency_type === 'counter') {
            return "Tous les {$this->counter_threshold} {$unit}";
        }

        if ($this->frequency_type === 'mileage') {
            return "Tous les " . number_format($this->mileage_interval, 0, ',', ' ') . " km";
        }

        return "Tous les {$this->frequency_value} {$unit}";
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
            default => $this->priority,
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray',
        };
    }

    public function getIsDueAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->next_execution_date && $this->next_execution_date->lte(Carbon::today())) {
            return true;
        }

        if ($this->asset_type === 'truck' && $this->next_mileage && $this->truck) {
            return $this->truck->mileage >= $this->next_mileage;
        }

        return false;
    }

    public function getIsDueSoonAttribute(): bool
    {
        if (!$this->is_active || $this->is_due) {
            return false;
        }

        if ($this->next_execution_date && $this->advance_days) {
            $triggerDate = $this->next_execution_date->copy()->subDays($this->advance_days);
            if (Carbon::today()->gte($triggerDate)) {
                return true;
            }
        }

        if ($this->asset_type === 'truck' && $this->next_mileage && $this->truck && $this->advance_mileage) {
            $triggerMileage = $this->next_mileage - $this->advance_mileage;
            if ($this->truck->mileage >= $triggerMileage) {
                return true;
            }
        }

        return false;
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        if ($this->is_due) {
            return 'overdue';
        }
        if ($this->is_due_soon) {
            return 'due_soon';
        }
        return 'on_track';
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'inactive' => 'Inactif',
            'overdue' => 'En retard',
            'due_soon' => 'À venir',
            'on_track' => 'À jour',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'inactive' => 'gray',
            'overdue' => 'red',
            'due_soon' => 'orange',
            'on_track' => 'green',
            default => 'gray',
        };
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->next_execution_date) {
            return null;
        }
        return Carbon::today()->diffInDays($this->next_execution_date, false);
    }

    public function getMileageUntilDueAttribute(): ?int
    {
        if ($this->asset_type !== 'truck' || !$this->next_mileage || !$this->truck) {
            return null;
        }
        return $this->next_mileage - $this->truck->mileage;
    }

    // ===== RELATIONS =====

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceTask::class)->orderBy('order');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(PreventiveMaintenancePart::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceLog::class)->orderByDesc('scheduled_date');
    }

    // ===== SCOPES =====

    public function scopeForEquipments($query)
    {
        return $query->where('asset_type', 'equipment');
    }

    public function scopeForTrucks($query)
    {
        return $query->where('asset_type', 'truck');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeDue($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereDate('next_execution_date', '<=', Carbon::today())
                  ->orWhere(function ($sq) {
                      $sq->where('asset_type', 'truck')
                         ->whereNotNull('next_mileage')
                         ->whereRaw('next_mileage <= (SELECT mileage FROM trucks WHERE trucks.id = preventive_maintenances.truck_id)');
                  });
            });
    }

    public function scopeDueSoon($query, int $days = null)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($days) {
                $q->whereDate('next_execution_date', '<=', Carbon::today()->addDays($days ?? 7))
                  ->whereDate('next_execution_date', '>', Carbon::today());
            });
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    // ===== MÉTHODES =====

    /**
     * Générer un code unique
     * ✅ CORRIGÉ — utilise le dernier numéro au lieu de count()
     */
    public static function generateCode(): string
    {
        $year = date('Y');
        $prefix = "PM-{$year}-";

        $lastCode = self::where('code', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(code, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s%04d', $prefix, $nextNumber);
    }

    /**
     * Calculer la prochaine date d'exécution
     */
    public function calculateNextExecutionDate(): ?Carbon
    {
        $baseDate = $this->last_execution_date ?? $this->start_date;

        if (!$baseDate) {
            return null;
        }

        $nextDate = match ($this->frequency_type) {
            'daily' => $baseDate->copy()->addDays($this->frequency_value),
            'weekly' => $baseDate->copy()->addWeeks($this->frequency_value),
            'monthly' => $baseDate->copy()->addMonths($this->frequency_value),
            'yearly' => $baseDate->copy()->addYears($this->frequency_value),
            'mileage' => null,
            default => null,
        };

        if ($this->end_date && $nextDate && $nextDate->gt($this->end_date)) {
            return null;
        }

        return $nextDate;
    }

    /**
     * Calculer le prochain kilométrage (camions)
     */
    public function calculateNextMileage(): ?int
    {
        if ($this->asset_type !== 'truck' || !$this->mileage_interval) {
            return null;
        }

        $baseMileage = $this->last_mileage ?? $this->truck?->mileage ?? 0;

        return $baseMileage + $this->mileage_interval;
    }

    /**
     * Mettre à jour la prochaine exécution
     */
    public function updateNextExecution(): void
    {
        $this->next_execution_date = $this->calculateNextExecutionDate();

        if ($this->asset_type === 'truck') {
            $this->next_mileage = $this->calculateNextMileage();
        }

        $this->save();
    }

    /**
     * Marquer comme exécuté
     */
    public function markAsExecuted(?int $mileage = null): void
    {
        $this->last_execution_date = Carbon::today();

        if ($this->asset_type === 'truck') {
            $this->last_mileage = $mileage ?? $this->truck?->mileage;

            if ($mileage && $this->truck) {
                $this->truck->updateMileage($mileage);
            }
        }

        $this->save();
        $this->updateNextExecution();
    }

    /**
     * Vérifier si une génération d'OT est nécessaire
     */
    public function needsWorkOrderGeneration(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->next_execution_date && $this->advance_days) {
            $triggerDate = $this->next_execution_date->copy()->subDays($this->advance_days);
            if (Carbon::today()->gte($triggerDate)) {
                return true;
            }
        }

        if ($this->asset_type === 'truck' && $this->next_mileage && $this->truck) {
            $triggerMileage = $this->next_mileage - ($this->advance_mileage ?? 500);
            if ($this->truck->mileage >= $triggerMileage) {
                return true;
            }
        }

        return false;
    }

    /**
     * Générer un OT à partir de cette maintenance préventive
     */
    public function generateWorkOrder(?int $userId = null): ?WorkOrder
    {
        $workOrder = WorkOrder::create([
            'site_id' => $this->site_id,
            'asset_type' => $this->asset_type,
            'equipment_id' => $this->equipment_id,
            'truck_id' => $this->truck_id,
            'code' => WorkOrder::generateCode($this->site_id),
            'title' => "MP: {$this->name}",
            'description' => $this->description,
            'type' => 'preventive',
            'priority' => $this->priority,
            'status' => $this->assigned_to ? 'assigned' : 'pending',
            'scheduled_start' => $this->next_execution_date ?? Carbon::today(),
            'estimated_duration' => $this->estimated_duration,
            'assigned_to' => $this->assigned_to,
            'requested_by' => $userId ?? $this->created_by,
        ]);

        $this->logs()->create([
            'work_order_id' => $workOrder->id,
            'scheduled_date' => $this->next_execution_date ?? Carbon::today(),
            'status' => 'generated',
            'created_by' => $userId,
        ]);

        return $workOrder;
    }

    public function isForTruck(): bool
    {
        return $this->asset_type === 'truck';
    }

    public function isForEquipment(): bool
    {
        return $this->asset_type === 'equipment';
    }

    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }
}
