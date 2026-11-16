<?php

namespace App\Pixelforge\Data;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/** US dollars per 1M tokens, kept as the exact decimal strings Pixelforge sends. */
final readonly class TokenRates
{
    /** @param array<string, string> $rates Keyed by TokenType value */
    private function __construct(private array $rates) {}

    /** @param array<string, mixed> $rates The API's rates object */
    public static function fromArray(array $rates): self
    {
        $parsed = [];

        foreach (TokenType::cases() as $type) {
            $rate = $rates[$type->value] ?? throw new InvalidArgumentException("Missing rate for {$type->value}.");
            $parsed[$type->value] = BigDecimal::of((string) $rate)->toString();
        }

        return new self($parsed);
    }

    public function of(TokenType $type): BigDecimal
    {
        return BigDecimal::of($this->rates[$type->value]);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->rates;
    }
}
