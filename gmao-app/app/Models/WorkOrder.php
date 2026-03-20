<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Setting;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'asset_type',
        'equipment_id',
        'truck_id',
        'intervention_request_id',
        'requested_by',
        'assigned_to',
        'code',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'estimated_duration',
        'actual_duration',
        'work_performed',
        'root_cause',
        'technician_notes',
        'diagnosis',
        'mileage_at_intervention',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'approved_by',
        'approved_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'actual_start' => 'datetime',
            'actual_end' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'labor_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'mileage_at_intervention' => 'integer',
        ];
    }

    protected $appends = ['asset_name', 'asset_code', 'status_label', 'priority_label', 'type_label', 'duration_formatted'];

    protected $attributes = [
        'asset_type' => 'equipment',
        'status' => 'pending',
        'priority' => 'medium',
        'type' => 'corrective',
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

    public function getAssetLocationAttribute(): ?string
    {
        if ($this->asset_type === 'truck') {
            return $this->truck?->site?->name;
        }
        return $this->equipment?->location?->name ?? $this->equipment?->site?->name;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'approved' => 'Approuvé',
            'assigned' => 'Assigné',
            'in_progress' => 'En cours',
            'on_hold' => 'En pause',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'approved' => 'blue',
            'assigned' => 'purple',
            'in_progress' => 'orange',
            'on_hold' => 'gray',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
            default => $this->priority,
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'corrective' => 'Corrective',
            'preventive' => 'Préventive',
            'improvement' => 'Amélioration',
            'inspection' => 'Inspection',
            default => $this->type,
        };
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->actual_duration) {
            return null;
        }

        $hours = floor($this->actual_duration / 60);
        $minutes = $this->actual_duration % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        }
        return "{$minutes}min";
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }

        return $this->scheduled_end && $this->scheduled_end->isPast();
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

    public function interventionRequest(): BelongsTo
    {
        return $this->belongsTo(InterventionRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WorkOrderTimeLog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WorkOrderHistory::class)->orderByDesc('created_at');
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled'])
            ->where('scheduled_end', '<', now());
    }

    public function scopeCorrective($query)
    {
        return $query->where('type', 'corrective');
    }

    public function scopePreventive($query)
    {
        return $query->where('type', 'preventive');
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

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    // ===== MÉTHODES =====

    /**
     * Générer un code unique pour l'OT
     * Lit le préfixe depuis settings (group=work_order, key=code_prefix)
     */
    public static function generateCode(?int $siteId = null): string
    {
        $year = date('Y');

        // ═══ Lire le préfixe depuis la table settings ═══
        $codePrefix = Setting::where('group', 'work_order')
            ->where('key', 'code_prefix')
            ->value('value') ?? 'OT';

        $prefix = "{$codePrefix}-{$year}-";

        $query = self::where('code', 'like', "{$prefix}%");

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $lastCode = $query->orderByRaw('CAST(SUBSTRING(code, -4) AS UNSIGNED) DESC')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $code = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        while (self::where('code', $code)->exists()) {
            $newNumber++;
            $code = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }


    /**
     * Ajouter un historique
     */
    public function addHistory(int $userId, string $action, string $description, ?string $oldValue = null, ?string $newValue = null): WorkOrderHistory
    {
        return $this->histories()->create([
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
        ]);
    }

    /**
     * Démarrer l'intervention
     */
    public function start(int $userId): bool
    {
        if (!in_array($this->status, ['pending', 'approved', 'assigned'])) {
            return false;
        }

        $oldStatus = $this->status;
        $this->status = 'in_progress';
        $this->actual_start = now();

        // Mettre l'asset en maintenance
        $this->asset?->setInMaintenance();

        $this->save();
        $this->addHistory($userId, 'started', 'Intervention démarrée', $oldStatus, 'in_progress');

        return true;
    }

    /**
     * Mettre en pause
     */
    public function pause(int $userId, ?string $reason = null): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        $this->status = 'on_hold';
        $this->save();
        $this->addHistory($userId, 'paused', $reason ?? 'Intervention mise en pause', 'in_progress', 'on_hold');

        return true;
    }

    /**
     * Reprendre
     */
    public function resume(int $userId): bool
    {
        if ($this->status !== 'on_hold') {
            return false;
        }

        $this->status = 'in_progress';
        $this->save();
        $this->addHistory($userId, 'resumed', 'Intervention reprise', 'on_hold', 'in_progress');

        return true;
    }

    /**
     * Terminer l'intervention
     */
    public function complete(int $userId, array $data = []): bool
    {
        if (!in_array($this->status, ['in_progress', 'on_hold'])) {
            return false;
        }

        $oldStatus = $this->status;

        $this->status = 'completed';
        $this->completed_by = $userId;
        $this->completed_at = now();
        $this->actual_end = now();

        // Calculer la durée
        if ($this->actual_start) {
            $this->actual_duration = $this->actual_start->diffInMinutes(now());
        }

        // Données supplémentaires
        if (isset($data['work_performed'])) {
            $this->work_performed = $data['work_performed'];
        }
        if (isset($data['root_cause'])) {
            $this->root_cause = $data['root_cause'];
        }
        if (isset($data['technician_notes'])) {
            $this->technician_notes = $data['technician_notes'];
        }
        if (isset($data['mileage_at_intervention']) && $this->asset_type === 'truck') {
            $this->mileage_at_intervention = $data['mileage_at_intervention'];
            $this->truck?->updateMileage($data['mileage_at_intervention']);
        }

        // Calculer les coûts
        $this->calculateCosts();

        // Remettre l'asset en service
        $this->asset?->setActive();

        $this->save();
        $this->addHistory($userId, 'completed', 'Intervention terminée', $oldStatus, 'completed');

        return true;
    }

    /**
     * Annuler l'intervention
     */
    public function cancel(int $userId, string $reason): bool
    {
        if ($this->status === 'completed') {
            return false;
        }

        $oldStatus = $this->status;

        $this->status = 'cancelled';
        $this->cancelled_by = $userId;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;

        // Remettre l'asset en service si était en maintenance
        if ($oldStatus === 'in_progress') {
            $this->asset?->setActive();
        }

        $this->save();
        $this->addHistory($userId, 'cancelled', "Intervention annulée: {$reason}", $oldStatus, 'cancelled');

        return true;
    }

    /**
     * Assigner un technicien
     */
    public function assign(int $technicianId, int $byUserId): bool
    {
        $oldAssigned = $this->assigned_to;

        $this->assigned_to = $technicianId;

        if ($this->status === 'pending' || $this->status === 'approved') {
            $this->status = 'assigned';
        }

        $this->save();

        $oldName = $oldAssigned ? User::find($oldAssigned)?->name : 'Non assigné';
        $newName = User::find($technicianId)?->name;

        $this->addHistory($byUserId, 'assigned', "Assigné à {$newName}", $oldName, $newName);

        return true;
    }

    /**
     * Calculer les coûts
     */
    public function calculateCosts(): void
    {
        // Coût main d'œuvre depuis les time logs
        $this->labor_cost = $this->timeLogs()->sum('cost') ?? 0;

        // Coût pièces
        $this->parts_cost = $this->parts()->sum(\DB::raw('quantity * unit_price')) ?? 0;

        // Total
        $this->total_cost = $this->labor_cost + $this->parts_cost;
    }

    /**
     * Vérifier si pour un camion
     */
    public function isForTruck(): bool
    {
        return $this->asset_type === 'truck';
    }

    /**
     * Vérifier si pour un équipement
     */
    public function isForEquipment(): bool
    {
        return $this->asset_type === 'equipment';
    }

    /**
     * Peut être démarré
     */
    public function canStart(): bool
    {
        return in_array($this->status, ['pending', 'approved', 'assigned']);
    }

    /**
     * Peut être terminé
     */
    public function canComplete(): bool
    {
        return in_array($this->status, ['in_progress', 'on_hold']);
    }

    /**
     * Peut être annulé
     */
    public function canCancel(): bool
    {
        return $this->status !== 'completed' && $this->status !== 'cancelled';
    }
}
