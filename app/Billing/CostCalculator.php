<?php

namespace App\Billing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pixelforge\Data\Pricing;
use App\Pixelforge\Data\TokenRates;
use App\Pixelforge\Data\TokenType;
use App\Pixelforge\Data\TokenUsage;
use Brick\Math\BigDecimal;

/** The single place where token usage becomes money. */
final class CostCalculator
{
    public const int MARKUP_PERCENT = 25;

    private const int TOKENS_PER_RATE_UNIT = 1_000_000;

    /** What Pixelforge charges us, in nanodollars. Exact: nothing is rounded. */
    public function providerCost(TokenUsage $usage, TokenRates $rates): int
    {
        $total = BigDecimal::zero();

        foreach (TokenType::cases() as $type) {
            $total = $total->plus($rates->of($type)->multipliedBy($usage->of($type)));
        }

        return Money::fromDecimal($total->dividedByExact(self::TOKENS_PER_RATE_UNIT));
    }

    /**
     * What the user pays for a given provider cost: cost plus markup,
     * rounded up to a whole nanodollar so we never undercharge.
     */
    public function userCharge(int $providerCost): int
    {
        return intdiv($providerCost * (100 + self::MARKUP_PERCENT) + 99, 100);
    }

    public function estimate(Pricing $pricing, string $prompt, ImageSize $size, ImageQuality $quality, int $referenceImages): CostEstimate
    {
        ['min' => $min, 'max' => $max] = $pricing->usageRange($prompt, $size, $quality, $referenceImages);

        return new CostEstimate(
            minUsage: $min,
            maxUsage: $max,
            minCharge: $this->userCharge($this->providerCost($min, $pricing->rates)),
            maxCharge: $this->userCharge($this->providerCost($max, $pricing->rates)),
        );
    }
}
