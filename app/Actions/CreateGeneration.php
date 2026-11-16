<?php

namespace App\Actions;

use App\Billing\CostCalculator;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PricingCache;

/** Records a new generation with its estimate and queues it for Pixelforge. */
final class CreateGeneration
{
    public function __construct(
        private readonly CostCalculator $calculator,
        private readonly PricingCache $pricing,
    ) {}

    /** @param list<string> $referenceImages */
    public function handle(User $user, string $prompt, ImageSize $size, ImageQuality $quality, array $referenceImages): Generation
    {
        $estimate = $this->calculator->estimate($this->pricing->current(), $prompt, $size, $quality, count($referenceImages));

        $generation = $user->generations()->create([
            'status' => GenerationStatus::Pending,
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
            'estimated_cost_min' => $estimate->minCharge,
            'estimated_cost_max' => $estimate->maxCharge,
        ]);

        // Dispatched only once the row is committed, so a webhook can always find it.
        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
