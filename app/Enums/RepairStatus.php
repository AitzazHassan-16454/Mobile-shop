<?php

namespace App\Enums;

enum RepairStatus: string
{
    case Received = 'received';
    case InDiagnosis = 'in_diagnosis';
    case WaitingParts = 'waiting_parts';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received (Token Generated)',
            self::InDiagnosis => 'In Diagnosis',
            self::WaitingParts => 'Waiting for Spare Parts',
            self::Ready => 'Ready for Delivery',
            self::Delivered => 'Delivered & Payment Cleared',
            self::Cancelled => 'Cancelled / Unrepairable',
        };
    }
}
