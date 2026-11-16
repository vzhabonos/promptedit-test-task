<?php

namespace App\Jobs;

use App\Actions\FailGeneration;
use App\Actions\SyncGeneration;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a pending generation to Pixelforge. Rate limits, concurrency limits,
 * overloads and network errors are retried with growing delays; anything
 * else fails the generation, which costs the user nothing.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** About five minutes of retrying in total; see backoff(). */
    public int $tries = 10;

    private const int MAX_BACKOFF_SECONDS = 60;

    public function __construct(public Generation $generation) {}

    public function handle(PixelforgeClient $client, SyncGeneration $sync, FailGeneration $fail): void
    {
        // Already accepted (a webhook may even have beaten the response) or failed.
        if ($this->generation->status !== GenerationStatus::Pending) {
            return;
        }

        try {
            $sync->handle($client->createGeneration($this->generation));
        } catch (PixelforgeException $exception) {
            if ($exception->isRetryable() && $this->attempts() < $this->tries) {
                $this->release($this->backoff($exception));

                return;
            }

            Log::warning('Pixelforge did not accept a generation.', [
                'generation_id' => $this->generation->id,
                'error_code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ]);

            $fail->handle($this->generation, $exception->errorCode, $exception->userMessage());
        }
    }

    /** Unexpected errors (bugs, database outages) must not leave a generation stuck as pending. */
    public function failed(?Throwable $exception): void
    {
        app(FailGeneration::class)->handle(
            $this->generation,
            'submission_failed',
            'We could not submit this generation. You were not charged; please try again.',
        );
    }

    /** Wait at least as long as Pixelforge asks, backing off exponentially: 2, 4, 8 … 60 seconds. */
    private function backoff(PixelforgeException $exception): int
    {
        $exponential = min(2 ** $this->attempts(), self::MAX_BACKOFF_SECONDS);

        return max($exception->retryAfter ?? 0, $exponential);
    }
}
