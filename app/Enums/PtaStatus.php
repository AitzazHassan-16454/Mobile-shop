<?php

namespace App\Enums;

enum PtaStatus: string
{
    case Approved = 'approved';
    case NonPta = 'non_pta';
    case Jv = 'jv';
    case Cpid = 'cpid';
    case Software = 'software';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'PTA Approved',
            self::NonPta => 'Non-PTA',
            self::Jv => 'JV / Network Locked',
            self::Cpid => 'CPID Approved',
            self::Software => 'Software Approved',
        };
    }
}
