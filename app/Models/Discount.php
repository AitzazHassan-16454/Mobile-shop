<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'min_purchase_amount',
        'max_discount_amount',
        'start_date',
        'end_date',
        'usage_limit',
        'used_count',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'value' => 'float',
        'min_purchase_amount' => 'float',
        'max_discount_amount' => 'float',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Check if the discount is currently valid and usable.
     */
    public function isValid(?float $subtotal = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        $now = now()->startOfDay();

        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        if ($subtotal !== null && $this->min_purchase_amount !== null && $subtotal < $this->min_purchase_amount) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if (! $this->isValid($subtotal)) {
            return 0.00;
        }

        if ($this->type === 'percentage') {
            $discount = ($subtotal * $this->value) / 100;
            if ($this->max_discount_amount !== null && $discount > $this->max_discount_amount) {
                $discount = $this->max_discount_amount;
            }

            return round($discount, 2);
        }

        return round(min($subtotal, $this->value), 2);
    }
}
