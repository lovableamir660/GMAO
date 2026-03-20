<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InterventionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'asset_type',
        'equipment_id',
        'truck_id',
        'requested_by',
        'code',
        'title',
        'description',
        'urgency',
        'status',
        'machine_stopped',
        'location_details',
        'contact_phone',
        'validated_by',
        'validated_at',
        'validation_comment',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'work_order_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'machine_stopped' => 'boolean',
            'validated_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    protected $appends = ['asset_name', 'asset_code', 'asset_location', 'urgency_label', 'status_label'];

    protected $attributes = [
        'asset_type' => 'equipment',
        'status' => 'pending',
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

    public function getUrgencyLabelAttribute(): ?string
    {
        if (!$this->urgency) {
            return null;
        }

        $labels = [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
        ];

        return $labels[$this->urgency] ?? 'Inconnu';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
            'converted' => 'Convertie en OT',
            'cancelled' => 'Annulée',
            default => $this->status ?? 'Inconnu',
        };
    }

    public function getUrgencyColorAttribute(): string
    {
        return match ($this->urgency) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'converted' => 'blue',
            'cancelled' => 'gray',
            default => 'gray',
        };
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

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function intervention(): HasOne
    {
        return $this->hasOne(Intervention::class);
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

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }

    public function scopeCritical($query)
    {
        return $query->where('urgency', 'critical');
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('urgency', ['high', 'critical']);
    }

    public function scopeMachineStopped($query)
    {
        return $query->where('machine_stopped', true);
    }

    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ===== MÉTHODES =====

    /**
     * Générer un code unique — lit le préfixe depuis settings
     */
    public static function generateCode(): string
    {
        $year = date('Y');

        // ═══ Lire le préfixe depuis la table settings ═══
        $codePrefix = Setting::where('group', 'intervention_request')
            ->where('key', 'code_prefix')
            ->value('value') ?? 'DI';

        $count = self::whereYear('created_at', $year)->count() + 1;
        return sprintf('%s-%s-%04d', $codePrefix, $year, $count);
    }


    /**
     * Vérifier si en attente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Vérifier si approuvée
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Vérifier si rejetée
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Vérifier si convertie
     */
    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    /**
     * Peut être validée
     */
    public function canBeValidated(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Peut être rejetée
     */
    public function canBeRejected(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Peut être convertie en OT
     */
    public function canBeConverted(): bool
    {
        return $this->status === 'approved' && !$this->work_order_id;
    }

    /**
     * Approuver la demande
     */
    public function approve(int $userId, ?string $comment = null): bool
    {
        if (!$this->canBeValidated()) {
            return false;
        }

        $this->status = 'approved';
        $this->validated_by = $userId;
        $this->validated_at = now();
        $this->validation_comment = $comment;

        return $this->save();
    }

    /**
     * Rejeter la demande
     */
    public function reject(int $userId, string $reason): bool
    {
        if (!$this->canBeRejected()) {
            return false;
        }

        $this->status = 'rejected';
        $this->rejected_by = $userId;
        $this->rejected_at = now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    /**
     * Convertir en OT/Intervention
     */
    public function convertToWorkOrder(int $workOrderId): bool
    {
        if (!$this->canBeConverted()) {
            return false;
        }

        $this->status = 'converted';
        $this->work_order_id = $workOrderId;
        $this->converted_at = now();

        // Mettre l'asset en maintenance si machine arrêtée
        if ($this->machine_stopped) {
            $this->asset?->setInMaintenance();
        }

        return $this->save();
    }

    /**
     * Annuler la demande
     */
    public function cancel(): bool
    {
        if ($this->isConverted()) {
            return false;
        }

        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Vérifier si c'est pour un camion
     */
    public function isForTruck(): bool
    {
        return $this->asset_type === 'truck';
    }

    /**
     * Vérifier si c'est pour un équipement
     */
    public function isForEquipment(): bool
    {
        return $this->asset_type === 'equipment';
    }
}
