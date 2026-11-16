<?php

namespace App\Actions;

use App\Billing\CostCalculator;
use App\Billing\CreditBalance;
use App\Billing\InsufficientCredits;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PricingCache;
use Illuminate\Support\Facades\DB;

/**
 * Records a new generation with its estimate and queues it for Pixelforge,
 * provided the user can afford the most it can cost.
 */
final class CreateGeneration
{
    public function __construct(
        private readonly CostCalculator $calculator,
        private readonly PricingCache $pricing,
        private readonly CreditBalance $balance,
    ) {}

    /**
     * @param  list<string>  $referenceImages
     *
     * @throws InsufficientCredits
     */
    public function handle(User $user, string $prompt, ImageSize $size, ImageQuality $quality, array $referenceImages): Generation
    {
        $estimate = $this->calculator->estimate($this->pricing->current(), $prompt, $size, $quality, count($referenceImages));

        $generation = DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $estimate): Generation {
            // Serializes this user's submissions, so two at once can't both spend the same credits.
            User::query()->lockForUpdate()->findOrFail($user->id);

            $available = $this->balance->available($user);

            if ($available < $estimate->maxCharge) {
                throw new InsufficientCredits($estimate->maxCharge, $available);
            }

            return $user->generations()->create([
                'status' => GenerationStatus::Pending,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'estimated_cost_min' => $estimate->minCharge,
                'estimated_cost_max' => $estimate->maxCharge,
            ]);
        });

        // Dispatched only once the row is committed, so a webhook can always find it.
        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
