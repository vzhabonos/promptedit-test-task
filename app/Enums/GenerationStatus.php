<?php

namespace App\Enums;

enum GenerationStatus: string
{
    /** Stored locally, not yet accepted by Pixelforge. */
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Pending, self::Queued, self::Processing];
    }

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * Statuses only move forward, so a late or replayed event can never
     * take a generation back (e.g. "processing" after "succeeded").
     */
    public function canAdvanceTo(self $next): bool
    {
        return $next->rank() > $this->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
