<?php

namespace App\Billing;

use App\Pixelforge\Data\TokenType;
use App\Pixelforge\Data\TokenUsage;

/** What a generation will cost the user, before it runs: a guaranteed range. */
final readonly class CostEstimate
{
    public function __construct(
        public TokenUsage $minUsage,
        public TokenUsage $maxUsage,
        public int $minCharge,
        public int $maxCharge,
    ) {}

    /** @return array{min: string, max: string, tokens: array<string, array{min: int, max: int}>} */
    public function toArray(): array
    {
        $tokens = [];

        foreach (TokenType::cases() as $type) {
            $tokens[$type->value] = ['min' => $this->minUsage->of($type), 'max' => $this->maxUsage->of($type)];
        }

        return [
            'min' => Money::format($this->minCharge),
            'max' => Money::format($this->maxCharge),
            'tokens' => $tokens,
        ];
    }
}
