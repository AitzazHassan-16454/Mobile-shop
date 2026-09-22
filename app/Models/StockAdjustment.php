<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_imei_id',
        'user_id',
        'type',
        'quantity',
        'reason',
        'notes',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function imei(): BelongsTo
    {
        return $this->belongsTo(ProductImei::class, 'product_imei_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
