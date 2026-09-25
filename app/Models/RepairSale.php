<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_name',
        'quantity',
        'cost_price',
        'sell_price',
        'total_amount',
        'payment_method',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProfitAttribute(): float
    {
        return (float) round(((float) $this->total_amount) - ((float) $this->cost_price * (float) $this->quantity), 2);
    }
}
