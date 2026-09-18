<?php

namespace Database\Factories;

use App\Enums\ImeiStatus;
use App\Enums\PhoneCondition;
use App\Enums\PtaStatus;
use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImeiFactory extends Factory
{
    protected $model = ProductImei::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->phone(),
            'imei_1' => fake()->unique()->numerify('35#############'),
            'imei_2' => fake()->unique()->numerify('35#############'),
            'color' => fake()->randomElement(['Black', 'Silver', 'Natural Titanium', 'Blue', 'White']),
            'storage' => fake()->randomElement(['128GB', '256GB', '512GB', '1TB']),
            'condition' => fake()->randomElement([PhoneCondition::New, PhoneCondition::Used]),
            'pta_status' => fake()->randomElement([PtaStatus::Approved, PtaStatus::NonPta, PtaStatus::Jv, PtaStatus::Cpid]),
            'purchase_cost' => fake()->randomFloat(2, 50000, 300000),
            'warranty_days' => 7,
            'status' => ImeiStatus::InStock,
            'sold_at' => null,
        ];
    }
}
