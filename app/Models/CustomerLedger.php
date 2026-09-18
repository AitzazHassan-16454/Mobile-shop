<?php

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedger extends Model
{
    use HasFactory;

    protected $table = 'customer_ledger';

    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'balance_after',
        'reference_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
