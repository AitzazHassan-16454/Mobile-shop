<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_imei_id',
        'quantity',
        'unit_cost',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productImei(): BelongsTo
    {
        return $this->belongsTo(ProductImei::class, 'product_imei_id');
    }

    public function returnItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function getReturnedQuantityAttribute(): float
    {
        return (float) round((float) $this->returnItems()->sum('quantity'), 2);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return (float) max(0.00, round((float) $this->quantity - $this->returned_quantity, 2));
    }
}
