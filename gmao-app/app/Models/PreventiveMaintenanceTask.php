<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventiveMaintenanceTask extends Model
{
    protected $fillable = [
        'preventive_maintenance_id',
        'order',
        'description',
        'estimated_duration',
        'instructions',
        'requires_part',
    ];

    protected function casts(): array
    {
        return [
            'requires_part' => 'boolean',
        ];
    }

    public function preventiveMaintenance(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenance::class);
    }
}
