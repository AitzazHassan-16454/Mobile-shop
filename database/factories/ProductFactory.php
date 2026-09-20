<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['iPhone 15 Pro', 'Samsung Galaxy S24', 'Xiaomi Redmi Note 13', 'Fast Charger 20W', 'Tempered Glass Shield', 'Type-C Cable 1m']),
            'brand' => fake()->randomElement(['Apple', 'Samsung', 'Xiaomi', 'Anker', 'Ronin']),
            'category' => fake()->randomElement(['Mobile Handsets', 'Chargers & Cables', 'Protectors & Covers', 'Handsfree']),
            'barcode' => fake()->unique()->numerify('890##########'),
            'is_serialized' => false,
            'sale_price' => fake()->randomFloat(2, 500, 350000),
            'cost_price' => fake()->randomFloat(2, 300, 250000),
            'stock_quantity' => fake()->numberBetween(0, 50),
            'alert_quantity' => 5,
        ];
    }

    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Mobile Handsets',
            'is_serialized' => true,
            'barcode' => null,
        ]);
    }

    public function accessory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_serialized' => false,
        ]);
    }
}
