<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'cnic',
        'address',
        'notes',
        'current_balance',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<CustomerLedger, $this>
     */
    public function ledgers(): HasMany
    {
        return $this->hasMany(CustomerLedger::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function getDueBalanceAttribute(): float
    {
        return (float) max(0.00, (float) $this->current_balance);
    }

    public function getAdvanceBalanceAttribute(): float
    {
        return (float) ((float) $this->current_balance < 0 ? abs((float) $this->current_balance) : 0.00);
    }
}
