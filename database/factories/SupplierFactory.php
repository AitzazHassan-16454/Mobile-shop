<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'phone' => fake()->unique()->numerify('03#########'),
            'address' => fake()->address(),
            'current_balance' => 0.00,
        ];
    }
}
