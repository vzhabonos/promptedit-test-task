<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('polls generations whose webhooks are overdue', function () {
    $quiet = Generation::factory()->queued()->create(['updated_at' => now()->subMinutes(3)]);
    $recent = Generation::factory()->queued()->create();
    $finished = Generation::factory()->queued()->create(['status' => GenerationStatus::Succeeded, 'updated_at' => now()->subHour()]);

    Http::fake(["*/generations/{$quiet->pixelforge_id}" => Http::response(pixelforgeGeneration($quiet, 'succeeded'))]);

    $this->artisan('pixelforge:reconcile')->assertSuccessful();

    Http::assertSentCount(1);
    expect($quiet->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($quiet->charged_cost)->toBe(381_887_500)
        ->and($recent->refresh()->status)->toBe(GenerationStatus::Queued)
        ->and($finished->refresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('stops for this run when rate limited', function () {
    Generation::factory()->queued()->count(3)->create(['updated_at' => now()->subMinutes(3)]);
    Http::fake(['*' => Http::response(['error' => ['code' => 'rate_limit_exceeded', 'message' => 'Slow down']], 429, ['Retry-After' => '30'])]);

    $this->artisan('pixelforge:reconcile')->assertSuccessful();

    Http::assertSentCount(1);
});

it('keeps going past a generation it cannot fetch', function () {
    [$missing, $ok] = Generation::factory()->queued()->count(2)->create(['updated_at' => now()->subMinutes(3)]);
    Http::fake([
        "*/generations/{$missing->pixelforge_id}" => Http::response(['error' => ['code' => 'not_found', 'message' => 'No such generation']], 404),
        "*/generations/{$ok->pixelforge_id}" => Http::response(pixelforgeGeneration($ok, 'failed')),
    ]);

    $this->artisan('pixelforge:reconcile')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), $ok->pixelforge_id));
    expect($ok->refresh()->status)->toBe(GenerationStatus::Failed);
});
