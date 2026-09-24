<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsedPhonePurchase extends Model
{
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
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_amount' => 'decimal:2',
            'agreement_signed' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }
}
