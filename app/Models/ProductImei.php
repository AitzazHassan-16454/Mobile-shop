<?php

namespace App\Models;

use App\Enums\ImeiStatus;
use App\Enums\PhoneCondition;
use App\Enums\PtaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductImei extends Model
{
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItem(): HasOne
    {
        return $this->hasOne(SaleItem::class);
    }
}
