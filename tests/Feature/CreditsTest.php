<?php

use App\Billing\CreditBalance;
use App\Enums\GenerationStatus;
use App\Models\CreditPurchase;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake(['*/pricing' => Http::response(jsonFixture('pricing'))]);
    Queue::fake();
    $this->user = User::factory()->create();
});

function balanceOf(User $user): int
{
    return app(CreditBalance::class)->available($user);
}

it('subtracts charges and holds the maximum of running generations', function () {
    CreditPurchase::factory()->for($this->user)->create(['amount' => 10_000_000_000]);
    Generation::factory()->for($this->user)->create([
        'status' => GenerationStatus::Succeeded, 'charged_cost' => 381_887_500,
    ]);
    Generation::factory()->for($this->user)->create([
        'status' => GenerationStatus::Failed, 'charged_cost' => 0,
    ]);
    Generation::factory()->for($this->user)->queued()->create(['estimated_cost_max' => 86_275_000]);

    expect(balanceOf($this->user))->toBe(10_000_000_000 - 381_887_500 - 86_275_000);
});

it('releases the hold when a generation finishes', function () {
    CreditPurchase::factory()->for($this->user)->create(['amount' => 1_000_000_000]);
    $generation = Generation::factory()->for($this->user)->queued()->create(['estimated_cost_max' => 86_275_000]);

    $generation->update(['status' => GenerationStatus::Failed, 'charged_cost' => 0]);

    expect(balanceOf($this->user))->toBe(1_000_000_000);
});

it('blocks generations the user cannot afford', function () {
    CreditPurchase::factory()->for($this->user)->create(['amount' => 80_000_000]); // $0.08 < max $0.086275

    $this->actingAs($this->user)
        ->post('/generations', ['prompt' => '0123456789', 'size' => '1024x1024', 'quality' => 'medium'])
        ->assertSessionHasErrors(['form' => 'This generation can cost up to $0.086275, but you have $0.08 available. Buy credits to continue.']);

    expect(Generation::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('counts running generations against the balance', function () {
    CreditPurchase::factory()->for($this->user)->create(['amount' => 100_000_000]); // $0.10

    $submit = fn () => $this->actingAs($this->user)
        ->post('/generations', ['prompt' => '0123456789', 'size' => '1024x1024', 'quality' => 'medium']);

    $submit()->assertSessionHasNoErrors();
    $submit()->assertSessionHasErrors('form'); // $0.10 - $0.086275 held is not enough

    expect(Generation::count())->toBe(1);
});

it('tells the estimate whether the user can afford it', function () {
    CreditPurchase::factory()->for($this->user)->create(['amount' => 80_000_000]);

    $this->actingAs($this->user)
        ->postJson('/generations/estimate', ['prompt' => '0123456789', 'size' => '1024x1024', 'quality' => 'medium'])
        ->assertJson(['balance' => '$0.08', 'affordable' => false]);
});

it('adds purchased credits', function () {
    $this->actingAs($this->user)->post('/credits', ['amount' => 10])->assertSessionHasNoErrors();

    expect(balanceOf($this->user))->toBe(10_000_000_000);
});

it('only sells the listed packages', function (mixed $amount) {
    $this->actingAs($this->user)->post('/credits', ['amount' => $amount])->assertSessionHasErrors('amount');

    expect(balanceOf($this->user))->toBe(0);
})->with([7, -5, 'lots', 1_000_000]);
