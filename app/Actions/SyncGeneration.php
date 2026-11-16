<?php

namespace App\Actions;

use App\Billing\GenerationBilling;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\Data\GenerationData;
use App\Pixelforge\Data\TokenRates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * Applies what Pixelforge says about a generation to our record. Everything
 * goes through here: the create response, webhooks and polling. It is safe to
 * call any number of times and in any order.
 */
final class SyncGeneration
{
    public function __construct(private readonly GenerationBilling $billing) {}

    public function handle(GenerationData $data): ?Generation
    {
        return DB::transaction(function () use ($data): ?Generation {
            $generation = $this->findAndLock($data);

            if ($generation === null) {
                Log::warning('Pixelforge reported on a generation we do not know.', ['pixelforge_id' => $data->id]);

                return null;
            }

            if ($this->shouldApply($generation, $data)) {
                $this->apply($generation, $data);
                $generation->save();
            }

            return $generation;
        });
    }

    /**
     * Webhooks can arrive before our create request returns, so we can't rely
     * on pixelforge_id being stored yet: match on the id we sent as metadata.
     */
    private function findAndLock(GenerationData $data): ?Generation
    {
        return Generation::query()
            ->when(
                $data->localId,
                fn ($query, string $localId) => $query->whereKey($localId),
                fn ($query) => $query->where('pixelforge_id', $data->id),
            )
            ->lockForUpdate()
            ->first();
    }

    private function shouldApply(Generation $generation, GenerationData $data): bool
    {
        // A failure we recorded before Pixelforge ever confirmed the generation
        // was our guess (e.g. every attempt timed out). If Pixelforge reports on
        // it, the request did get through, and Pixelforge is the source of truth.
        $unconfirmedLocalFailure = $generation->status === GenerationStatus::Failed && $generation->pixelforge_id === null;

        return $unconfirmedLocalFailure || $generation->status->canAdvanceTo($data->status);
    }

    private function apply(Generation $generation, GenerationData $data): void
    {
        $generation->fill([
            'pixelforge_id' => $data->id,
            'status' => $data->status,
            'rates' => $data->rates?->toArray() ?? $generation->rates,
            'image_url' => $data->imageUrl,
            'image_url_expires_at' => $data->imageUrlExpiresAt,
            'error_code' => $data->errorCode,
            'error_message' => $data->errorMessage,
            'completed_at' => $data->completedAt,
        ]);

        match ($data->status) {
            GenerationStatus::Succeeded => $this->billing->charge(
                $generation,
                $data->usage ?? throw new UnexpectedValueException("Succeeded generation {$data->id} has no usage."),
                TokenRates::fromArray($generation->rates ?? throw new UnexpectedValueException("Generation {$data->id} has no rates.")),
            ),
            GenerationStatus::Failed => $this->billing->waive($generation),
            default => null,
        };
    }
}
