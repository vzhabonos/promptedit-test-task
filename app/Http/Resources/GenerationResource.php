<?php

namespace App\Http\Resources;

use App\Billing\Money;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $imageExpired = $this->image_url_expires_at?->isPast() ?? false;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->reference_images,
            'estimated_cost' => [
                'min' => Money::format($this->estimated_cost_min),
                'max' => Money::format($this->estimated_cost_max),
            ],
            // What the user paid; Pixelforge's own cost is for operators only.
            'charged_cost' => $this->charged_cost === null ? null : Money::format($this->charged_cost),
            'usage' => $this->usage,
            'image_url' => $imageExpired ? null : $this->image_url,
            'image_expired' => $imageExpired,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
        ];
    }
}
