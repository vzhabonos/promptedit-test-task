<?php

namespace App\Pixelforge;

use Illuminate\Container\Attributes\Config;

/**
 * Verifies the Pixelforge-Signature header: "t=<unix time>,v1=<signature>",
 * where the signature is the hex HMAC-SHA256 of "<t>.<raw body>".
 */
final class WebhookSignature
{
    /** Older signatures are rejected so a captured request can't be replayed. */
    public const int TOLERANCE_SECONDS = 300;

    public function __construct(
        #[Config('services.pixelforge.webhook_secret')] private readonly ?string $secret,
    ) {}

    public function isValid(string $payload, ?string $header, int $now): bool
    {
        if (blank($this->secret) || blank($header)) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            match ($key) {
                't' => $timestamp = ctype_digit($value) ? (int) $value : null,
                'v1' => $signatures[] = $value,
                default => null,
            };
        }

        if ($timestamp === null || abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) $this->secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
