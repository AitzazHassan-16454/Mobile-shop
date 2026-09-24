<?php

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InstallmentPlan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('yearly dues page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('yearly-dues.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('YearlyDues/Index')
            ->has('year')
            ->has('year_options')
            ->has('dues')
            ->has('installments')
            ->has('collections'));
});

test('yearly dues aggregates outstanding customer balances', function () {
    Customer::factory()->create(['name' => 'Imran Khan', 'current_balance' => 25000]);
    Customer::factory()->create(['name' => 'Ahmed Ali', 'current_balance' => 15000]);
    Customer::factory()->create(['name' => 'No Balance', 'current_balance' => 0]);

    $response = $this->actingAs($this->user)
        ->get(route('yearly-dues.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dues.total', 40000)
            ->where('dues.count', 2)
            ->where('dues.top_debtors.0.name', 'Imran Khan'));
});

test('earliest and current year bound the year selector', function () {
    Customer::factory()->create(['current_balance' => 1000]);

    $this->actingAs($this->user)
        ->get(route('yearly-dues.index', [$this->team->slug, 'year' => now()->year - 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('year', now()->year - 1));

    $this->actingAs($this->user)
        ->get(route('yearly-dues.index', [$this->team->slug, 'year' => 1990]))
        ->assertInertia(fn ($page) => $page->where('year', 2020));
});

test('single installment due soon appears as a maturation reminder', function () {
    $customer = Customer::factory()->create(['name' => 'Sana Javed']);

    InstallmentPlan::create([
        'customer_id' => $customer->id,
        'total_amount' => 60000,
        'down_payment' => 10000,
        'monthly_amount' => 10000,
        'duration_months' => 5,
        'paid_installments' => 3,
        'next_due_date' => now()->addDays(3),
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('yearly-dues.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('installments.reminders_count', 1)
            ->where('installments.reminders.0.customer.name', 'Sana Javed')
            ->where('installments.reminders.0.remaining', 20000));
});

test('overdue installment is flagged with overdue days', function () {
    $customer = Customer::factory()->create(['name' => 'Rabia Idress']);

    InstallmentPlan::create([
        'customer_id' => $customer->id,
        'total_amount' => 30000,
        'down_payment' => 5000,
        'monthly_amount' => 5000,
        'duration_months' => 5,
        'paid_installments' => 2,
        'next_due_date' => now()->subDays(5),
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('yearly-dues.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('installments.reminders.0.overdue_days', 5));
});

test('collection target persists and gates the progress value', function () {
    AppSetting::set('yearly_collection_target', '2500000');

    CustomerLedger::create([
        'customer_id' => Customer::factory()->create()->id,
        'type' => 'payment',
        'amount' => 250000,
        'balance_after' => -250000,
        'notes' => 'Payment Received (Bank)',
    ]);

    $this->actingAs($this->user)
        ->get(route('yearly-dues.index', $this->team->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('collections.target_numeric', 2500000)
            ->where('collections.collected', 250000));

    $this->actingAs($this->user)
        ->patch(route('yearly-dues.target.update', $this->team->slug), ['target' => 3000000])
        ->assertRedirect();

    expect(AppSetting::get('yearly_collection_target'))->toBe('3000000');
});
