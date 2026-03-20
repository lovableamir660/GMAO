<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverHabilitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'habilitation_id',
        'obtained_date',
        'expiry_date',
        'certificate_number',
        'status',
        'document_path',
        'notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'obtained_date' => 'date',
        'expiry_date' => 'date',
        'verified_at' => 'datetime',
    ];

    protected $appends = ['is_expired', 'is_expiring_soon', 'days_until_expiry'];

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->isBetween(now(), now()->addDays(30));
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) return null;
        return now()->diffInDays($this->expiry_date, false);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function habilitation(): BelongsTo
    {
        return $this->belongsTo(Habilitation::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function updateStatus(): void
    {
        if ($this->is_expired) {
            $this->update(['status' => 'expired']);
        }
    }
}
