<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money columns are integer nanodollars.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $pixelforge_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<string> $reference_images
 * @property int $estimated_cost_min
 * @property int $estimated_cost_max
 * @property int|null $provider_cost
 * @property int|null $charged_cost
 * @property array<string, string>|null $rates
 * @property array<string, int>|null $usage
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'pixelforge_id', 'status', 'prompt', 'size', 'quality', 'reference_images',
    'estimated_cost_min', 'estimated_cost_max', 'provider_cost', 'charged_cost', 'rates', 'usage',
    'image_url', 'image_url_expires_at', 'error_code', 'error_message', 'completed_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'estimated_cost_min' => 'integer',
            'estimated_cost_max' => 'integer',
            'provider_cost' => 'integer',
            'charged_cost' => 'integer',
            'rates' => 'array',
            'usage' => 'array',
            'image_url_expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query */
    protected function scopeActive(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::active());
    }
}
