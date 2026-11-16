<?php

namespace App\Http\Requests\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mirrors Pixelforge's own limits, so a bad request never leaves the app. */
class StoreGenerationRequest extends FormRequest
{
    public const int MAX_PROMPT_LENGTH = 4000;

    public const int MAX_REFERENCE_IMAGES = 4;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['array', 'max:'.self::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reference_images.*' => 'reference image URL'];
    }

    public function prompt(): string
    {
        return (string) $this->validated('prompt');
    }

    public function size(): ImageSize
    {
        return ImageSize::from($this->validated('size'));
    }

    public function quality(): ImageQuality
    {
        return ImageQuality::from($this->validated('quality'));
    }

    /** @return list<string> */
    public function referenceImages(): array
    {
        return array_values($this->validated('reference_images', []));
    }
}
