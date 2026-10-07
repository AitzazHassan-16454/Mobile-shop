<?php

namespace App\Models;

use Database\Factories\InstallmentPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon $paid_at
 */
class InstallmentPayment extends Model
{
    /** @use HasFactory<InstallmentPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'installment_plan_id', 'amount', 'paid_at', 'payment_method', 'reference_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<InstallmentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }
}
