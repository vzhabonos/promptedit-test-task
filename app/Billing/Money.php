<?php

namespace App\Billing;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * All money is stored as integer nanodollars (1 USD = 1,000,000,000).
 *
 * Pixelforge reports costs as exact decimals with nine places, so every
 * provider cost maps onto a whole number of nanodollars with no rounding.
 */
final class Money
{
    public const int SCALE = 9;

    /**
     * Throws if the amount has more than nine decimal places: silently
     * rounding money would hide a bug.
     */
    public static function fromDecimal(BigNumber|string $usd): int
    {
        return BigDecimal::of($usd)
            ->toScale(self::SCALE, RoundingMode::Unnecessary)
            ->withPointMovedRight(self::SCALE)
            ->toInt();
    }

    /** The exact amount, e.g. "0.373875000" (the format GET /v1/usage uses). */
    public static function toDecimal(int $nanodollars): string
    {
        return BigDecimal::ofUnscaledValue($nanodollars, self::SCALE)->toString();
    }

    /**
     * Human-readable and still exact: trailing zeros are dropped but nothing
     * is rounded, e.g. "$0.373875", "$5.00", "-$0.0581".
     */
    public static function format(int $nanodollars): string
    {
        $amount = BigDecimal::ofUnscaledValue(abs($nanodollars), self::SCALE)->strippedOfTrailingZeros();
        $amount = $amount->toScale(max(2, $amount->getScale()));

        [$whole, $fraction] = explode('.', $amount->toString());

        return ($nanodollars < 0 ? '-' : '').'$'.number_format((int) $whole).'.'.$fraction;
    }
}
