<?php

namespace Database\Factories;

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
        ];
    }
}
