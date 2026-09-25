<?php

namespace App\Enums;

enum LedgerType: string
{
    case Sale = 'sale';
    case Payment = 'payment';
    case Advance = 'advance';
    case AdvanceReturn = 'advance_return';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Credit Sale (Udhaar)',
            self::Payment => 'Due Payment',
            self::Advance => 'Advance Received',
            self::AdvanceReturn => 'Advance Refunded',
            self::Return => 'Product Return',
            self::Adjustment => 'Balance Adjustment',
        };
    }
}
