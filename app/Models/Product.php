<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'category',
        'barcode',
        'is_serialized',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'alert_quantity',
    ];

    protected function casts(): array
    {
        return [
            'is_serialized' => 'boolean',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'alert_quantity' => 'integer',
        ];
    }

    public function imeis(): HasMany
    {
        return $this->hasMany(ProductImei::class);
    }

    public function inStockImeis(): HasMany
    {
        return $this->hasMany(ProductImei::class)->where('status', 'in_stock');
    }

    public function availableImeis(): HasMany
    {
        return $this->hasMany(ProductImei::class)->where('status', 'in_stock');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
