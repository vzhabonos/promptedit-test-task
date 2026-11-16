<?php

namespace App\Actions;

use App\Billing\GenerationBilling;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;

/** Marks a generation that Pixelforge never accepted as failed (and free). */
final class FailGeneration
{
    public function __construct(private readonly GenerationBilling $billing) {}

    public function handle(Generation $generation, string $errorCode, string $errorMessage): void
    {
        DB::transaction(function () use ($generation, $errorCode, $errorMessage): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->id);

            // Pixelforge has already told us more than we know here.
            if ($generation->status !== GenerationStatus::Pending) {
                return;
            }

            $generation->fill([
                'status' => GenerationStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);
            $this->billing->waive($generation);
            $generation->save();
        });
    }
}
