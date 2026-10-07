<?php

namespace App\Models;

use App\Enums\TradeInStatus;
use Database\Factories\UsedPhonePurchaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property TradeInStatus $status
 * @property \Illuminate\Support\Carbon $reviewed_at
 * @property \Illuminate\Support\Carbon $applied_at
 */
class UsedPhonePurchase extends Model
{
    /** @use HasFactory<UsedPhonePurchaseFactory> */
    use HasFactory;

    protected $fillable = [
        'voucher_no',
        'seller_name',
        'seller_father_name',
        'seller_cnic',
        'seller_phone',
        'seller_address',
        'cnic_front_image',
        'cnic_back_image',
        'device_model',
        'imei_1',
        'imei_2',
        'purchase_amount',
        'payment_method',
        'agreement_signed',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_amount' => 'decimal:2',
            'agreement_signed' => 'boolean',
            'status' => TradeInStatus::class,
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TradeInStatus::Pending->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', TradeInStatus::Approved->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnapplied(Builder $query): Builder
    {
        return $query->whereNull('applied_at');
    }

    /**
     * Credits that are both approved and still unused, i.e. the only ones
     * allowed to be redeemed as a trade-in discount on a sale.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query->approved()->unapplied();
    }

    public function isRedeemable(): bool
    {
        return $this->status === TradeInStatus::Approved && $this->applied_at === null;
    }
}
