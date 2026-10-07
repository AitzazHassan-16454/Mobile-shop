<?php

namespace Database\Factories;

use App\Enums\TradeInStatus;
use App\Models\UsedPhonePurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsedPhonePurchase>
 */
class UsedPhonePurchaseFactory extends Factory
{
    protected $model = UsedPhonePurchase::class;

    public function definition(): array
    {
        return [
            'voucher_no' => 'UPP-'.fake()->unique()->numerify('######'),
            'seller_name' => fake()->name(),
            'seller_father_name' => fake()->name('male'),
            'seller_cnic' => fake()->numerify('35202-#######-#'),
            'seller_phone' => fake()->numerify('03##########'),
            'seller_address' => fake()->city(),
            'device_model' => 'iPhone 11',
            'imei_1' => fake()->numerify('35############'),
            'purchase_amount' => fake()->numberBetween(10000, 90000),
            'payment_method' => 'cash',
            'agreement_signed' => true,
            'status' => TradeInStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => TradeInStatus::Pending]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => TradeInStatus::Approved]);
    }

    public function rejected(string $reason = 'Rejected by reviewer'): static
    {
        return $this->state(fn (): array => [
            'status' => TradeInStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }

    public function applied(): static
    {
        return $this->state(fn (): array => ['applied_at' => now()->subDay()]);
    }
}
