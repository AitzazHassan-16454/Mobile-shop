<?php

namespace App\Models;

use App\Enums\ImeiStatus;
use App\Enums\PhoneCondition;
use App\Enums\PtaStatus;
use Database\Factories\ProductImeiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property \App\Enums\PhoneCondition $condition
 * @property \App\Enums\PtaStatus $pta_status
 * @property \App\Enums\ImeiStatus $status
 * @property \Illuminate\Support\Carbon $sold_at
 */
class ProductImei extends Model
{
    /** @use HasFactory<ProductImeiFactory> */
    use HasFactory;

    protected $table = 'product_imeis';

    protected $fillable = [
        'product_id',
        'imei_1',
        'imei_2',
        'color',
        'storage',
        'condition',
        'pta_status',
        'purchase_cost',
        'warranty_days',
        'status',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'condition' => PhoneCondition::class,
            'pta_status' => PtaStatus::class,
            'status' => ImeiStatus::class,
            'purchase_cost' => 'decimal:2',
            'warranty_days' => 'integer',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasOne<SaleItem, $this>
     */
    public function saleItem(): HasOne
    {
        return $this->hasOne(SaleItem::class);
    }
}
