<?php

use App\Billing\Money;
use Brick\Math\Exception\RoundingNecessaryException;

it('converts exact decimals to nanodollars', function (string $usd, int $nanodollars) {
    expect(Money::fromDecimal($usd))->toBe($nanodollars);
})->with([
    ['0.373875000', 373_875_000],
    ['0.000000001', 1],
    ['5', 5_000_000_000],
    ['0', 0],
]);

it('refuses to round amounts finer than a nanodollar', function () {
    Money::fromDecimal('0.0000000001');
})->throws(RoundingNecessaryException::class);

it('renders the exact decimal format Pixelforge uses', function () {
    expect(Money::toDecimal(373_875_000))->toBe('0.373875000')
        ->and(Money::toDecimal(0))->toBe('0.000000000')
        ->and(Money::toDecimal(12_500_000_000))->toBe('12.500000000');
});

it('formats amounts for people without rounding them', function (int $nanodollars, string $formatted) {
    expect(Money::format($nanodollars))->toBe($formatted);
})->with([
    [373_875_000, '$0.373875'],
    [5_000_000_000, '$5.00'],
    [1_234_500_000_000, '$1,234.50'],
    [-58_100_000, '-$0.0581'],
    [0, '$0.00'],
    [1, '$0.000000001'],
]);
