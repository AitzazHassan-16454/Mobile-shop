<?php

namespace App\Enums;

enum PhoneCondition: string
{
    case New = 'new';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Brand New (Pin Pack / Box Pack)',
            self::Used => 'Used (Kit / Second Hand)',
        };
    }
}
