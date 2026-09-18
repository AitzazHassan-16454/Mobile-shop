<?php

namespace App\Enums;

enum ImeiStatus: string
{
    case InStock = 'in_stock';
    case Sold = 'sold';
    case Repairing = 'repairing';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::Sold => 'Sold',
            self::Repairing => 'In Repair',
            self::Returned => 'Returned to Supplier',
        };
    }
}
