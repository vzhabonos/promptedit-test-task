<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;

/** Any failed call to Pixelforge, carrying the API's error code. */
final class PixelforgeException extends RuntimeException
{
    /** Pixelforge created nothing, so the same request can safely be sent again. */
    private const array RETRYABLE_CODES = [
        'rate_limit_exceeded',
        'concurrency_limit_exceeded',
        'overloaded',
        'connection_failed',
    ];

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly ?int $status = null,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');

        return new self(
            message: (string) $response->json('error.message', "Pixelforge responded with HTTP {$response->status()}."),
            errorCode: (string) $response->json('error.code', "http_{$response->status()}"),
            status: $response->status(),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public static function connectionFailed(ConnectionException $exception): self
    {
        return new self('Could not reach Pixelforge.', 'connection_failed', previous: $exception);
    }

    /**
     * Server errors are retryable too: with an Idempotency-Key a retry can
     * never create a second generation.
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true) || $this->status >= 500;
    }

    /** What we tell the user when their generation could not be submitted. */
    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy.',
            'daily_limit_exceeded' => 'The daily generation limit has been reached. Please try again after midnight UTC.',
            'validation_failed' => "Pixelforge rejected the request: {$this->getMessage()}",
            default => $this->isRetryable()
                ? 'Pixelforge is busy right now. Please try again in a minute.'
                : 'Pixelforge could not accept this generation. Please try again later.',
        };
    }
}
