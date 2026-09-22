<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'name' => 'Piece',
                'short_name' => 'Pcs',
                'allow_decimal' => false,
                'is_active' => true,
                'description' => 'Single item unit for discrete items like mobile phones, chargers, and cases.',
            ],
            [
                'name' => 'Box',
                'short_name' => 'Box',
                'allow_decimal' => false,
                'is_active' => true,
                'description' => 'Packaged box unit containing multiple accessories or parts.',
            ],
            [
                'name' => 'Set',
                'short_name' => 'Set',
                'allow_decimal' => false,
                'is_active' => true,
                'description' => 'Combo set (e.g. Handsfree + Adapter set).',
            ],
            [
                'name' => 'Packet',
                'short_name' => 'Pkt',
                'allow_decimal' => false,
                'is_active' => true,
                'description' => 'Small packet of connectors or spare parts.',
            ],
            [
                'name' => 'Dozen',
                'short_name' => 'Dzn',
                'allow_decimal' => false,
                'is_active' => true,
                'description' => 'Pack of 12 items.',
            ],
            [
                'name' => 'Kilogram',
                'short_name' => 'Kg',
                'allow_decimal' => true,
                'is_active' => true,
                'description' => 'Weight measurement for bulk materials.',
            ],
            [
                'name' => 'Meter',
                'short_name' => 'Mtr',
                'allow_decimal' => true,
                'is_active' => true,
                'description' => 'Length measurement for wire rolls or ribbon cables.',
            ],
        ];

        foreach ($units as $unitData) {
            Unit::firstOrCreate(
                ['short_name' => $unitData['short_name']],
                $unitData
            );
        }
    }
}
