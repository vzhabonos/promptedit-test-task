<?php

use App\Models\Generation;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/** @return array<string, mixed> Decoded tests/Fixtures/{$name}.json */
function jsonFixture(string $name): array
{
    return json_decode((string) file_get_contents(fixture("{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A Pixelforge generation object for one of our generations, shaped like the
 * API docs' examples. Succeeded generations use the docs' usage, which costs
 * exactly $0.305510000 at the default rates.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelforgeGeneration(Generation $generation, string $status, array $overrides = []): array
{
    $finished = in_array($status, ['succeeded', 'failed'], true);

    return array_replace([
        'id' => $generation->pixelforge_id ?? 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'object' => 'generation',
        'status' => $status,
        'prompt' => $generation->prompt,
        'size' => $generation->size->value,
        'quality' => $generation->quality->value,
        'reference_images' => $generation->reference_images,
        'metadata' => ['generation_id' => $generation->id],
        'image_url' => $status === 'succeeded' ? 'https://pixelforge.promptedit.dev/images/gen.png?signature=abc' : null,
        'image_url_expires_at' => $status === 'succeeded' ? '2026-11-05T14:03:29Z' : null,
        'usage' => $status === 'succeeded' ? [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ] : null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => $status === 'failed' ? [
            'code' => 'internal_error',
            'message' => 'Something went wrong on our side while generating. You were not charged; please retry.',
        ] : null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => $status === 'queued' ? null : '2026-11-04T14:03:13Z',
        'completed_at' => $finished ? '2026-11-04T14:03:29Z' : null,
    ], $overrides);
}
