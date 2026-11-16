<?php

namespace App\Pixelforge\Data;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pixelforge's published pricing (GET /v1/pricing): the current rates plus
 * the rules for how many tokens a generation uses.
 */
final readonly class Pricing
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens  Quality => size => exact token count
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  Quality => token range
     */
    public function __construct(
        public TokenRates $rates,
        public int $charactersPerTextToken,
        public BigDecimal $textTokenVariance,
        public int $tokensPerReferenceImage,
        public array $outputTokens,
        public array $reasoningTokens,
    ) {}

    /** @param array<string, mixed> $pricing The API's pricing object */
    public static function fromArray(array $pricing): self
    {
        $tokens = $pricing['tokens'];
        $variance = $tokens['text_token_variance'];

        return new self(
            rates: TokenRates::fromArray($pricing['rates']),
            charactersPerTextToken: (int) $tokens['characters_per_text_token'],
            textTokenVariance: is_float($variance) ? BigDecimal::fromFloatShortest($variance) : BigDecimal::of($variance),
            tokensPerReferenceImage: (int) $tokens['per_reference_image'],
            outputTokens: $tokens['output'],
            reasoningTokens: $tokens['reasoning'],
        );
    }

    /**
     * The fewest and the most tokens a generation can be billed for. Output
     * and reference image tokens are exact; text and reasoning tokens are
     * only known once the generation completes.
     *
     * @return array{min: TokenUsage, max: TokenUsage}
     */
    public function usageRange(string $prompt, ImageSize $size, ImageQuality $quality, int $referenceImages): array
    {
        $textTokens = BigDecimal::of(intdiv(mb_strlen($prompt) + $this->charactersPerTextToken - 1, $this->charactersPerTextToken));
        $imageTokens = $referenceImages * $this->tokensPerReferenceImage;
        $outputTokens = $this->outputTokens[$quality->value][$size->value];
        $reasoning = $this->reasoningTokens[$quality->value];

        return [
            'min' => new TokenUsage(
                inputText: $textTokens->multipliedBy(BigDecimal::one()->minus($this->textTokenVariance))->toScale(0, RoundingMode::Floor)->toInt(),
                inputImage: $imageTokens,
                output: $outputTokens,
                reasoning: $reasoning['min'],
            ),
            'max' => new TokenUsage(
                inputText: $textTokens->multipliedBy(BigDecimal::one()->plus($this->textTokenVariance))->toScale(0, RoundingMode::Ceiling)->toInt(),
                inputImage: $imageTokens,
                output: $outputTokens,
                reasoning: $reasoning['max'],
            ),
        ];
    }
}
