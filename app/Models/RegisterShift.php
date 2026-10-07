<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon $opened_at
 * @property \Illuminate\Support\Carbon $closed_at
 */
class RegisterShift extends Model
{

    protected $fillable = [
        'user_id',
        'opened_at',
        'closed_at',
        'opening_float',
        'cash_sales',
        'jazzcash_sales',
        'easypaisa_sales',
        'bank_sales',
        'udhaar_sales',
        'wasooli_cash',
        'expenses_amount',
        'expected_cash',
        'actual_cash',
        'discrepancy',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'jazzcash_sales' => 'decimal:2',
            'easypaisa_sales' => 'decimal:2',
            'bank_sales' => 'decimal:2',
            'udhaar_sales' => 'decimal:2',
            'wasooli_cash' => 'decimal:2',
            'expenses_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'discrepancy' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ShopExpense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(ShopExpense::class);
    }
}
