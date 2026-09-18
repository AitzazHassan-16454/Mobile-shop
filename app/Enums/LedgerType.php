<?php

namespace App\Enums;

enum LedgerType: string
{
    case Sale = 'sale';
    case Payment = 'payment';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Credit Sale',
            self::Payment => 'Payment Received',
            self::Return => 'Item Return',
            self::Adjustment => 'Balance Adjustment',
        };
    }
}
