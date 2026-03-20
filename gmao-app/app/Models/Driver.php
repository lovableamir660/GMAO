<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\TruckDriverHistory;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'code',
        'first_name',
        'last_name',
        'phone',
        'email',
        'license_number',
        'license_type',
        'license_expiry_date',
        'medical_checkup_date',
        'hire_date',
        'status',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'photo',
        'created_by',
    ];

    protected $casts = [
        'license_expiry_date' => 'date',
        'medical_checkup_date' => 'date',
        'hire_date' => 'date',
    ];

    protected $appends = ['full_name', 'habilitations_status'];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getHabilitationsStatusAttribute(): string
    {
        $expired = $this->habilitations()
            ->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->whereNotNull('expiry_date')
                    ->where('expiry_date', '<', now());
            })
            ->count();

        $expiringSoon = $this->habilitations()
            ->where('status', 'valid')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();

        if ($expired > 0)
            return 'expired';
        if ($expiringSoon > 0)
            return 'expiring_soon';
        return 'valid';
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function habilitations(): HasMany
    {
        return $this->hasMany(DriverHabilitation::class);
    }

    public function currentTruck(): HasOne
    {
        return $this->hasOne(Truck::class, 'current_driver_id');
    }

    public function hasValidHabilitation(int $habilitationId): bool
    {
        return $this->habilitations()
            ->where('habilitation_id', $habilitationId)
            ->where('status', 'valid')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            })
            ->exists();
    }

    public function hasAllClientHabilitations(int $clientId): bool
    {
        $requiredHabilitations = Habilitation::where('client_id', $clientId)
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($requiredHabilitations as $habId) {
            if (!$this->hasValidHabilitation($habId)) {
                return false;
            }
        }

        return true;
    }

    public function getMissingHabilitations(int $clientId): array
    {
        $requiredHabilitations = Habilitation::where('client_id', $clientId)
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->get();

        $missing = [];
        foreach ($requiredHabilitations as $hab) {
            if (!$this->hasValidHabilitation($hab->id)) {
                $missing[] = $hab;
            }
        }

        return $missing;
    }

    public static function generateCode(int $siteId): string
    {
        $prefix = "CHF-";

        $lastCode = self::where('site_id', $siteId)
            ->where('code', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(code, 5) AS UNSIGNED) DESC')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, 4);
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

    public function truckHistory(): HasMany
    {
        return $this->hasMany(TruckDriverHistory::class)->orderByDesc('assigned_at');
    }

    public function currentAssignment(): ?TruckDriverHistory
    {
        return $this->truckHistory()->whereNull('unassigned_at')->first();
    }
}
