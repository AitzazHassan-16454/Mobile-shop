<?php

namespace App\Models;

use Database\Factories\InstallmentPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon $next_due_date
 */
class InstallmentPlan extends Model
{
    /** @use HasFactory<InstallmentPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id', 'sale_id', 'total_amount', 'down_payment', 'monthly_amount',
        'duration_months', 'paid_installments', 'next_due_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'monthly_amount' => 'decimal:2',
            'next_due_date' => 'date',
            'paid_installments' => 'integer',
            'duration_months' => 'integer',
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
     * @return HasMany<InstallmentPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class);
    }
}
