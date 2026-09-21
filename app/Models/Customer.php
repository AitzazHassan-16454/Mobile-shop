<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(CustomerLedger::class);
    }
}
