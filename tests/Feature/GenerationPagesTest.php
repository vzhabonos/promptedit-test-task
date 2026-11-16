<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\CreditPurchase;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
});

it('keeps guests out', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/generations'],
    ['GET', '/generations/create'],
    ['POST', '/generations'],
    ['POST', '/generations/estimate'],
]);

function fakePricing(): void
{
    Http::fake(['*/pricing' => Http::response(jsonFixture('pricing'))]);
}

it('queues a generation with its estimated price range', function () {
    fakePricing();
    CreditPurchase::factory()->for($this->user)->create();
    $this->actingAs($this->user)
        ->post('/generations', [
            'prompt' => '0123456789',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
        ])
        ->assertRedirect('/generations');

    $generation = $this->user->generations()->sole();
    expect($generation)
        ->status->toBe(GenerationStatus::Pending)
        ->estimated_cost_min->toBe(58_137_500)
        ->estimated_cost_max->toBe(86_275_000);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
});

it('validates input against Pixelforge limits', function (array $input, string $field) {
    fakePricing();
    $this->actingAs($this->user)
        ->post('/generations', [...['prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'medium'], ...$input])
        ->assertSessionHasErrors($field);

    expect(Generation::count())->toBe(0);
})->with([
    'missing prompt' => [['prompt' => ''], 'prompt'],
    'prompt too long' => [['prompt' => str_repeat('a', 4001)], 'prompt'],
    'unknown size' => [['size' => '512x512'], 'size'],
    'unknown quality' => [['quality' => 'ultra'], 'quality'],
    'too many references' => [['reference_images' => array_map(fn ($i) => "https://example.com/{$i}.png", range(1, 5))], 'reference_images'],
    'not a web URL' => [['reference_images' => ['ftp://example.com/a.png']], 'reference_images.0'],
]);

it('reports when pricing is unavailable instead of guessing', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'overloaded']], 503)]);

    $this->actingAs($this->user)
        ->post('/generations', ['prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'medium'])
        ->assertSessionHasErrors('form');

    expect(Generation::count())->toBe(0);
});

it('estimates the price range of a request', function () {
    fakePricing();
    $this->actingAs($this->user)
        ->postJson('/generations/estimate', [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
        ])
        ->assertOk()
        // Pixelforge's maximum from the docs ($0.373875) plus 25%.
        ->assertJsonPath('max', '$0.46734375')
        ->assertJsonPath('tokens.reasoning_tokens', ['min' => 1000, 'max' => 8000]);
});

it('shows only your own generations', function () {
    Generation::factory()->for($this->user)->create();
    Generation::factory()->create();

    $this->actingAs($this->user)
        ->get('/generations')
        ->assertInertia(fn (Assert $page) => $page
            ->component('generations/Index')
            ->has('generations.data', 1)
            ->where('generations.data.0.estimated_cost', ['min' => '$0.0581375', 'max' => '$0.086275']));
});

it('reopens the form with a previous generation\'s settings', function () {
    $mine = Generation::factory()->for($this->user)->create(['prompt' => 'Mine']);
    $theirs = Generation::factory()->create();

    $this->actingAs($this->user)
        ->get("/generations/create?from={$mine->id}")
        ->assertInertia(fn (Assert $page) => $page->component('generations/Create')->where('previous.prompt', 'Mine'));

    $this->actingAs($this->user)
        ->get("/generations/create?from={$theirs->id}")
        ->assertInertia(fn (Assert $page) => $page->where('previous', null));

    $this->actingAs($this->user)
        ->get("/generations/create?from[]={$mine->id}")
        ->assertInertia(fn (Assert $page) => $page->where('previous', null));
});
