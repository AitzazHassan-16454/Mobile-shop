<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    config([
        'fortify.features' => array_merge(config('fortify.features', []), [
            Features::twoFactorAuthentication([
                'confirm' => true,
                'confirmPassword' => true,
            ]),
        ]),
    ]);

    $user = User::factory()->withTwoFactor()->create([
        'name' => 'two-factor-user',
    ]);

    $this->post(route('login.store'), [
        'name' => 'two-factor-user',
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/TwoFactorChallenge'),
        );
});
