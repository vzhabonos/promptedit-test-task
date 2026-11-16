<?php

namespace App\Billing;

use App\Models\Generation;
use App\Pixelforge\Data\TokenRates;
use App\Pixelforge\Data\TokenUsage;

/**
 * Records what a finished generation costs. Callers hold the generation's
 * row lock and save it, so each generation is billed exactly once.
 */
final class GenerationBilling
{
    public function __construct(private readonly CostCalculator $calculator) {}

    public function charge(Generation $generation, TokenUsage $usage, TokenRates $rates): void
    {
        $providerCost = $this->calculator->providerCost($usage, $rates);

        $generation->usage = $usage->toArray();
        $generation->provider_cost = $providerCost;
        $generation->charged_cost = $this->calculator->userCharge($providerCost);
    }

    /** Failed generations are free: Pixelforge doesn't bill them and neither do we. */
    public function waive(Generation $generation): void
    {
        $generation->provider_cost = 0;
        $generation->charged_cost = 0;
    }
}
