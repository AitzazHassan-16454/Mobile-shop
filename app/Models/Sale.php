<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \App\Enums\PaymentMethod $payment_method
 */
class Sale extends Model
{

    protected $fillable = [
        'invoice_no',
        'customer_id',
        'total_amount',
        'discount_amount',
        'trade_in_amount',
        'used_phone_purchase_id',
        'net_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'payment_details',
        'cashier_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'payment_details' => 'array',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'trade_in_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return BelongsTo<UsedPhonePurchase, $this>
     */
    public function usedPhonePurchase(): BelongsTo
    {
        return $this->belongsTo(UsedPhonePurchase::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function getDueAmountAttribute(): float
    {
        return (float) max(0.00, round((float) $this->net_amount - (float) $this->paid_amount, 2));
    }

    public function getTotalReturnedAmountAttribute(): float
    {
        return (float) round((float) $this->returns()->sum('total_return_amount'), 2);
    }

    public function getTotalRefundedAmountAttribute(): float
    {
        return (float) round((float) $this->returns()->sum('refund_amount'), 2);
    }
}
