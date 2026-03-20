<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckDriverHistory extends Model
{
    use HasFactory;

    protected $table = 'truck_driver_history';

    protected $fillable = [
        'site_id',
        'truck_id',
        'driver_id',
        'assigned_at',
        'unassigned_at',
        'start_mileage',
        'end_mileage',
        'assignment_reason',
        'unassignment_reason',
        'notes',
        'assigned_by',
        'unassigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    protected $appends = ['duration', 'distance'];

    // Raisons d'attribution
    const ASSIGNMENT_REASONS = [
        'regular' => 'Attribution régulière',
        'mission' => 'Mission spécifique',
        'replacement' => 'Remplacement',
        'training' => 'Formation',
        'temporary' => 'Temporaire',
    ];

    // Raisons de fin d'attribution
    const UNASSIGNMENT_REASONS = [
        'end_mission' => 'Fin de mission',
        'breakdown' => 'Panne véhicule',
        'maintenance' => 'Maintenance',
        'leave' => 'Congé chauffeur',
        'reassignment' => 'Réaffectation',
        'termination' => 'Fin de contrat',
    ];

    public function getDurationAttribute(): ?string
    {
        if (!$this->assigned_at) return null;
        
        $end = $this->unassigned_at ?? now();
        $diff = $this->assigned_at->diff($end);
        
        if ($diff->days > 0) {
            return $diff->days . ' jour(s)';
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure(s)';
        } else {
            return $diff->i . ' minute(s)';
        }
    }

    public function getDistanceAttribute(): ?int
    {
        if ($this->start_mileage && $this->end_mileage) {
            return $this->end_mileage - $this->start_mileage;
        }
        return null;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function unassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unassigned_by');
    }

    public function isActive(): bool
    {
        return $this->unassigned_at === null;
    }
}
