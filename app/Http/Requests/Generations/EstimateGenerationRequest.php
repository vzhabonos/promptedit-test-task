<?php

namespace App\Http\Requests\Generations;

/** Same input as creating a generation, but estimates are shown while the prompt is still empty. */
class EstimateGenerationRequest extends StoreGenerationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'prompt' => ['nullable', 'string', 'max:'.self::MAX_PROMPT_LENGTH],
        ];
    }
}
