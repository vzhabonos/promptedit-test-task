<?php

namespace App\Console\Commands;

use App\Actions\SyncGeneration;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Webhook delivery isn't guaranteed, so generations that have gone quiet
 * are polled and synced the same way a webhook would sync them.
 */
class ReconcileGenerations extends Command
{
    protected $signature = 'pixelforge:reconcile {--limit=10 : Most generations to poll per run}';

    protected $description = 'Poll Pixelforge for generations whose webhooks are overdue';

    /** Generations normally finish in 5-30s (60-90s when slow), with webhooks along the way. */
    private const int QUIET_FOR_SECONDS = 120;

    public function handle(PixelforgeClient $client, SyncGeneration $sync): int
    {
        // The limit keeps us well inside 60 requests/minute; Pixelforge only
        // runs 3 generations at once per account, so it is never the bottleneck.
        $generations = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->where('updated_at', '<', now()->subSeconds(self::QUIET_FOR_SECONDS))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($generations as $generation) {
            try {
                $sync->handle($client->getGeneration((string) $generation->pixelforge_id));
            } catch (PixelforgeException $exception) {
                Log::warning('Could not poll a Pixelforge generation.', [
                    'generation_id' => $generation->id,
                    'error_code' => $exception->errorCode,
                ]);

                if ($exception->isRetryable()) {
                    break; // Rate limited or unreachable: the next run tries again.
                }
            }
        }

        $this->components->info("Polled {$generations->count()} generation(s).");

        return self::SUCCESS;
    }
}
