<?php

namespace App\Models;

use App\Enums\RepairStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_no',
        'customer_name',
        'customer_phone',
        'device_model',
        'imei',
        'pattern_or_pin',
        'problem_description',
        'condition_notes',
        'estimated_cost',
        'advance_paid',
        'status',
        'spare_parts_cost',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RepairStatus::class,
            'estimated_cost' => 'decimal:2',
            'advance_paid' => 'decimal:2',
            'spare_parts_cost' => 'decimal:2',
            'delivered_at' => 'datetime',
        ];
    }
}
