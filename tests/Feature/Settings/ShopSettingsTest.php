<?php

use App\Models\AppSetting;
use App\Models\User;

test('shop settings page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('shop-settings.edit'));

    $response->assertOk();
});

test('shop settings can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('shop-settings.update'), [
            'shop_name' => 'Faizan Mobile',
            'shop_phone' => '0300-0000000',
            'shop_address' => 'Shop #3, Saddar',
            'invoice_style' => 'bold_banner',
            'return_policy' => '7 days checking warranty.',
            'invoice_footer' => 'Shukriya!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('shop-settings.edit'));

    expect(AppSetting::get('shop_name'))->toBe('Faizan Mobile');
    expect(AppSetting::get('shop_phone'))->toBe('0300-0000000');
    expect(AppSetting::get('shop_address'))->toBe('Shop #3, Saddar');
    expect(AppSetting::get('invoice_style'))->toBe('bold_banner');
    expect(AppSetting::get('return_policy'))->toBe('7 days checking warranty.');
    expect(AppSetting::get('invoice_footer'))->toBe('Shukriya!');
});

test('shop name and phone are required', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('shop-settings.update'), []);

    $response->assertSessionHasErrors(['shop_name', 'shop_phone']);
});

test('invoice footer is optional and defaults to empty', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('shop-settings.update'), [
            'shop_name' => 'Faizan Mobile',
            'shop_phone' => '0300-0000000',
        ]);

    expect(AppSetting::get('invoice_footer'))->toBeNull();
});
