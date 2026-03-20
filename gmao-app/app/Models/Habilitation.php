<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habilitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'client_id',
        'code',
        'name',
        'description',
        'category',
        'validity_months',
        'is_mandatory',
        'is_active',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function driverHabilitations(): HasMany
    {
        return $this->hasMany(DriverHabilitation::class);
    }

    public static function generateCode(int $siteId): string
    {
        $prefix = "HAB-";
        
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
}
