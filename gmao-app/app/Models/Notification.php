<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'user_id',
        'type',
        'title',
        'message',
        'icon',
        'color',
        'link',
        'reference_type',
        'reference_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // Types de notifications
    const TYPE_STOCK_CRITICAL = 'stock_critical';
    const TYPE_WO_OVERDUE = 'wo_overdue';
    const TYPE_WO_ASSIGNED = 'wo_assigned';
    const TYPE_PM_UPCOMING = 'pm_upcoming';
    const TYPE_EQUIPMENT_DOWN = 'equipment_down';
    const TYPE_WO_COMPLETED = 'wo_completed';
    const TYPE_WO_CREATED = 'wo_created';

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('site_id', $user->current_site_id)
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
