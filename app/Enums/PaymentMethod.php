<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case JazzCash = 'jazzcash';
    case EasyPaisa = 'easypaisa';
    case Bank = 'bank';
    case Split = 'split';
    case Udhaar = 'udhaar';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::JazzCash => 'JazzCash',
            self::EasyPaisa => 'EasyPaisa',
            self::Bank => 'Bank Transfer (Raast)',
            self::Split => 'Split Tender',
            self::Udhaar => 'Udhaar (Customer Khata)',
        };
    }
}
