<?php

use App\Actions\SyncGeneration;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\Data\GenerationData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sync(Generation $generation, string $status, array $overrides = []): void
{
    app(SyncGeneration::class)->handle(GenerationData::fromArray(pixelforgeGeneration($generation, $status, $overrides)));
}

it('records the exact cost of a succeeded generation', function () {
    $generation = Generation::factory()->queued()->create();

    sync($generation, 'succeeded');

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost)->toBe(305_510_000)
        ->and($generation->charged_cost)->toBe(381_887_500)
        ->and($generation->usage)->toMatchArray(['output_tokens' => 6450, 'reasoning_tokens' => 3184])
        ->and($generation->image_url)->not->toBeNull()
        ->and($generation->completed_at?->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z');
});

it('makes failed generations free', function () {
    $generation = Generation::factory()->queued()->create();

    sync($generation, 'failed');

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->provider_cost)->toBe(0)
        ->and($generation->charged_cost)->toBe(0)
        ->and($generation->error_code)->toBe('internal_error');
});

it('never moves a finished generation back', function () {
    $generation = Generation::factory()->queued()->create();

    sync($generation, 'succeeded');
    sync($generation, 'processing');
    sync($generation, 'failed');

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charged_cost)->toBe(381_887_500);
});

it('bills a generation once however often it is reported', function () {
    $generation = Generation::factory()->queued()->create();

    sync($generation, 'succeeded');
    sync($generation, 'succeeded', ['usage' => ['output_tokens' => 99_999]]);

    expect($generation->refresh()->provider_cost)->toBe(305_510_000);
});

it('matches webhooks that arrive before the create request returns', function () {
    $generation = Generation::factory()->create(); // pending: no pixelforge_id yet

    sync($generation, 'processing', ['id' => 'gen_early']);
    sync($generation, 'queued', ['id' => 'gen_early']); // the late create response

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->pixelforge_id)->toBe('gen_early');
});

it('lets Pixelforge overrule a failure we only assumed', function () {
    // Every submit attempt timed out, but one of them did reach Pixelforge.
    $generation = Generation::factory()->create([
        'status' => GenerationStatus::Failed,
        'error_code' => 'connection_failed',
        'provider_cost' => 0,
        'charged_cost' => 0,
    ]);

    sync($generation, 'succeeded');

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->error_code)->toBeNull()
        ->and($generation->charged_cost)->toBe(381_887_500);
});

it('ignores generations it does not know', function () {
    $generation = Generation::factory()->make(['id' => '01JUNKNOWN0000000000000000']);

    expect(app(SyncGeneration::class)->handle(GenerationData::fromArray(pixelforgeGeneration($generation, 'succeeded'))))
        ->toBeNull();
});
