<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'code',
        'name',
        'description',
        'category',
        'unit',
        'unit_price',
        'quantity_in_stock',
        'minimum_stock',
        'maximum_stock',
        'location_in_warehouse',
        'barcode',
        'manufacturer',
        'manufacturer_reference',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'equipment_part')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function workOrderParts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    // Helpers
    public function isLowStock(): bool
    {
        return $this->quantity_in_stock <= $this->minimum_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity_in_stock <= 0;
    }
}
