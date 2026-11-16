<?php

namespace App\Pixelforge;

use App\Pixelforge\Data\Pricing;
use Illuminate\Support\Facades\Cache;

/**
 * Current pricing for estimates. Kept fresh for 5 minutes and served stale for
 * up to a day while it refreshes, so a brief Pixelforge outage doesn't break
 * the estimate. Actual costs never use this: they use the rates fixed on each
 * generation.
 */
class PricingCache
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /** @throws PixelforgeException when pricing has never been fetched and Pixelforge is unreachable */
    public function current(): Pricing
    {
        /** @var array<string, mixed> $pricing */
        $pricing = Cache::flexible('pixelforge.pricing', [300, 86_400], fn (): array => $this->client->pricing()->toArray());

        return Pricing::fromArray($pricing);
    }
}
