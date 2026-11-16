<?php

use App\Billing\CostCalculator;
use App\Billing\Money;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pixelforge\Data\Pricing;
use App\Pixelforge\Data\TokenRates;
use App\Pixelforge\Data\TokenUsage;
use Brick\Math\Exception\RoundingNecessaryException;

beforeEach(function () {
    $this->calculator = new CostCalculator;
    $this->pricing = Pricing::fromArray(jsonFixture('pricing'));
});

it('matches the most-expensive-generation example from the docs', function () {
    $estimate = $this->calculator->estimate(
        $this->pricing,
        prompt: str_repeat('a', 400),
        size: ImageSize::Landscape,
        quality: ImageQuality::High,
        referenceImages: 2,
    );

    expect($estimate->maxUsage)->toEqual(new TokenUsage(inputText: 115, inputImage: 1530, output: 6450, reasoning: 8000))
        ->and($this->calculator->providerCost($estimate->maxUsage, $this->pricing->rates))
        ->toBe(Money::fromDecimal('0.373875000'));
});

it('prices actual usage exactly', function (array $usage, string $expectedCost) {
    $cost = $this->calculator->providerCost(TokenUsage::fromArray($usage), $this->pricing->rates);

    expect(Money::toDecimal($cost))->toBe($expectedCost);
})->with([
    'succeeded generation from the docs' => [
        ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184, 'total_tokens' => 10411],
        '0.305510000',
    ],
    'usage report day from the docs' => [
        ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950],
        '0.169080000',
    ],
    'nothing used' => [[], '0.000000000'],
]);

it('fails loudly rather than rounding a cost finer than a nanodollar', function () {
    $rates = TokenRates::fromArray([
        'input_text_tokens' => '0.0001',
        'input_image_tokens' => '0',
        'output_tokens' => '0',
        'reasoning_tokens' => '0',
    ]);

    $this->calculator->providerCost(new TokenUsage(inputText: 1, inputImage: 0, output: 0, reasoning: 0), $rates);
})->throws(RoundingNecessaryException::class);

it('adds the markup and rounds up to a whole nanodollar', function (int $providerCost, int $userCharge) {
    expect($this->calculator->userCharge($providerCost))->toBe($userCharge);
})->with([
    [305_510_000, 381_887_500],
    [4, 5],
    [1, 2],
    [0, 0],
]);

it('estimates a guaranteed price range', function () {
    $estimate = $this->calculator->estimate(
        $this->pricing,
        prompt: '0123456789',
        size: ImageSize::Square,
        quality: ImageQuality::Medium,
        referenceImages: 0,
    );

    // 10 characters -> ~3 text tokens, +/-15% -> 2..4
    expect($estimate->minUsage)->toEqual(new TokenUsage(inputText: 2, inputImage: 0, output: 1100, reasoning: 200))
        ->and($estimate->maxUsage)->toEqual(new TokenUsage(inputText: 4, inputImage: 0, output: 1100, reasoning: 2000))
        ->and(Money::toDecimal($estimate->minCharge))->toBe('0.058137500')
        ->and(Money::toDecimal($estimate->maxCharge))->toBe('0.086275000');
});

it('counts prompt characters, not bytes', function () {
    ['max' => $max] = $this->pricing->usageRange('ééééé', ImageSize::Square, ImageQuality::Low, 0);

    // 5 characters -> 2 tokens (10 bytes would give 3), +15% -> 3
    expect($max->inputText)->toBe(3);
});
