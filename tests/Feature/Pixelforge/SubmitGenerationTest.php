<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function pixelforgeError(string $code, int $status, array $headers = []): PromiseInterface
{
    return Http::response(['error' => ['type' => 'api_error', 'code' => $code, 'message' => "Error: {$code}"]], $status, $headers);
}

/** Runs the job as the queue worker would, on its given attempt. */
function runSubmit(Generation $generation, int $attempt = 1): SubmitGeneration
{
    $job = (new SubmitGeneration($generation->fresh()))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

it('submits with an idempotency key and our id as metadata', function () {
    $generation = Generation::factory()->create(['reference_images' => ['https://example.com/style.png']]);
    Http::fake(['*/generations' => Http::response(pixelforgeGeneration($generation, 'queued', ['id' => 'gen_123']), 202)]);

    runSubmit($generation);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->id)
        && $request->hasHeader('Authorization')
        && $request['metadata'] === ['generation_id' => $generation->id]
        && $request['reference_images'] === ['https://example.com/style.png']);

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBe('gen_123')
        ->and($generation->rates)->toHaveKey('output_tokens', '40.00');
});

it('retries limits and overloads, honouring Retry-After', function (string $code, int $status, int $retryAfter) {
    $generation = Generation::factory()->create();
    Http::fake(['*' => pixelforgeError($code, $status, ['Retry-After' => (string) $retryAfter])]);

    runSubmit($generation)->assertReleased(delay: $retryAfter);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Pending);
})->with([
    ['rate_limit_exceeded', 429, 42],
    ['concurrency_limit_exceeded', 429, 5],
    ['overloaded', 503, 2],
]);

it('backs off exponentially when Pixelforge asks for less', function () {
    $generation = Generation::factory()->create();
    Http::fake(['*' => pixelforgeError('overloaded', 503, ['Retry-After' => '2'])]);

    runSubmit($generation, attempt: 5)->assertReleased(delay: 32);
});

it('retries when Pixelforge cannot be reached', function () {
    $generation = Generation::factory()->create();
    Http::fake(['*' => Http::failedConnection()]);

    runSubmit($generation)->assertReleased();
});

it('fails for free when Pixelforge refuses the generation', function (string $code, int $status, string $message) {
    $generation = Generation::factory()->create();
    Http::fake(['*' => pixelforgeError($code, $status)]);

    runSubmit($generation)->assertNotReleased();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe($code)
        ->and($generation->error_message)->toContain($message)
        ->and($generation->charged_cost)->toBe(0);
})->with([
    ['content_policy_violation', 400, 'content policy'],
    ['daily_limit_exceeded', 429, 'daily generation limit'],
    ['validation_failed', 422, 'rejected the request'],
]);

it('gives up after the last attempt', function () {
    $generation = Generation::factory()->create();
    Http::fake(['*' => pixelforgeError('overloaded', 503)]);

    runSubmit($generation, attempt: 10)->assertNotReleased();

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error_message->toContain('busy')
        ->charged_cost->toBe(0);
});

it('fails the generation when the job crashes', function () {
    $generation = Generation::factory()->create();

    (new SubmitGeneration($generation))->failed(new RuntimeException('Database went away'));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error_code->toBe('submission_failed');
});

it('does not resubmit a generation Pixelforge already accepted', function () {
    $generation = Generation::factory()->queued()->create();
    Http::fake();

    runSubmit($generation);

    Http::assertNothingSent();
});
