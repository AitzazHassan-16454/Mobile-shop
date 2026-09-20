<?php

use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('installment page can be rendered', function () {
    $this->actingAs($this->user)
        ->get(route('installments.index', $this->team->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Installments/Index')
            ->has('plans')
            ->has('customers')
            ->has('summary'));
});

test('can create plan and collect an installment payment', function () {
    $customer = Customer::factory()->create();

    $createResponse = $this->actingAs($this->user)
        ->post(route('installments.store', $this->team->slug), [
            'customer_id' => $customer->id,
            'total_amount' => 120000,
            'down_payment' => 20000,
            'duration_months' => 5,
            'next_due_date' => '2026-10-19',
        ]);

    $createResponse->assertRedirect();
    $plan = InstallmentPlan::latest()->first();
    expect((float) $plan->monthly_amount)->toBe(20000.00);

    $paymentResponse = $this->actingAs($this->user)
        ->post(route('installments.payments.store', [$this->team->slug, $plan->id]), [
            'amount' => 20000,
            'payment_method' => 'cash',
        ]);

    $paymentResponse->assertRedirect();
    $plan->refresh();
    expect($plan->paid_installments)->toBe(1);
    expect($plan->status)->toBe('active');
    $this->assertDatabaseHas('installment_payments', [
        'installment_plan_id' => $plan->id,
        'amount' => 20000.00,
    ]);
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
