<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const WEBHOOK_SECRET = 'whsec_test';

beforeEach(fn () => config(['services.pixelforge.webhook_secret' => WEBHOOK_SECRET]));

/** @param array<string, mixed> $object */
function generationEvent(string $id, string $type, array $object): array
{
    return ['id' => $id, 'object' => 'event', 'type' => $type, 'created_at' => '2026-11-04T14:03:29Z', 'data' => ['object' => $object]];
}

/** POSTs an event signed the way Pixelforge signs it. */
function deliver(array $event, ?int $timestamp = null, string $secret = WEBHOOK_SECRET, ?string $signature = null): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $timestamp ??= time();
    $signature ??= 't='.$timestamp.',v1='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', '/webhooks/pixelforge', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => $signature,
        'HTTP_PIXELFORGE_EVENT_ID' => $event['id'],
    ], content: $body);
}

it('applies a signed event', function () {
    $generation = Generation::factory()->queued()->create();

    deliver(generationEvent('evt_1', 'generation.succeeded', pixelforgeGeneration($generation, 'succeeded')))
        ->assertNoContent();

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->charged_cost->toBe(381_887_500);
});

it('rejects requests that are not signed by Pixelforge', function (?int $age, string $secret, ?string $signature) {
    $generation = Generation::factory()->queued()->create();
    $event = generationEvent('evt_1', 'generation.succeeded', pixelforgeGeneration($generation, 'succeeded'));

    deliver($event, $age === null ? null : time() - $age, $secret, $signature)->assertBadRequest();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued)
        ->and(DB::table('webhook_events')->count())->toBe(0);
})->with([
    'wrong secret' => [null, 'whsec_attacker', null],
    'replayed (too old)' => [301, WEBHOOK_SECRET, null],
    'missing signature' => [null, WEBHOOK_SECRET, ''],
    'garbage signature' => [null, WEBHOOK_SECRET, 't=abc,v1=xyz'],
]);

it('processes an event delivered twice only once', function () {
    $generation = Generation::factory()->queued()->create();
    $event = generationEvent('evt_1', 'generation.succeeded', pixelforgeGeneration($generation, 'succeeded'));

    deliver($event)->assertNoContent();
    deliver($event)->assertNoContent();

    expect(DB::table('webhook_events')->count())->toBe(1)
        ->and($generation->refresh()->charged_cost)->toBe(381_887_500);
});

it('ends in the right state when events arrive out of order', function () {
    $generation = Generation::factory()->queued()->create();

    deliver(generationEvent('evt_2', 'generation.succeeded', pixelforgeGeneration($generation, 'succeeded')));
    deliver(generationEvent('evt_1', 'generation.processing', pixelforgeGeneration($generation, 'processing')));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->image_url->not->toBeNull();
});

it('leaves no trace when processing fails, so the retry is processed', function () {
    $generation = Generation::factory()->queued()->create();
    $broken = generationEvent('evt_1', 'generation.succeeded', pixelforgeGeneration($generation, 'succeeded', ['usage' => null]));

    $this->withoutExceptionHandling();
    expect(fn () => deliver($broken))->toThrow(UnexpectedValueException::class);
    expect(DB::table('webhook_events')->count())->toBe(0);

    deliver(generationEvent('evt_1', 'generation.succeeded', pixelforgeGeneration($generation, 'succeeded')))
        ->assertNoContent();
    expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('acknowledges test pings', function () {
    deliver(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => '2026-11-04T14:03:29Z', 'data' => []])
        ->assertNoContent();
});
