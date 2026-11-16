<?php

namespace App\Pixelforge\Data;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

/** A Pixelforge generation object, as returned by the API and sent in webhooks. */
final readonly class GenerationData
{
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        /** Our own generation id, echoed back from the metadata we sent. */
        public ?string $localId,
        public ?TokenRates $rates,
        public ?TokenUsage $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $completedAt,
    ) {}

    /** @param array<string, mixed> $generation */
    public static function fromArray(array $generation): self
    {
        $date = static fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value)->utc();

        return new self(
            id: $generation['id'],
            status: GenerationStatus::from($generation['status']),
            localId: $generation['metadata']['generation_id'] ?? null,
            rates: isset($generation['rates']) ? TokenRates::fromArray($generation['rates']) : null,
            usage: isset($generation['usage']) ? TokenUsage::fromArray($generation['usage']) : null,
            imageUrl: $generation['image_url'] ?? null,
            imageUrlExpiresAt: $date($generation['image_url_expires_at'] ?? null),
            errorCode: $generation['error']['code'] ?? null,
            errorMessage: $generation['error']['message'] ?? null,
            completedAt: $date($generation['completed_at'] ?? null),
        );
    }
}
