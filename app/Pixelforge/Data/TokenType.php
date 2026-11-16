<?php

namespace App\Pixelforge\Data;

/** The token kinds Pixelforge bills; values are the API's field names. */
enum TokenType: string
{
    case InputText = 'input_text_tokens';
    case InputImage = 'input_image_tokens';
    case Output = 'output_tokens';
    case Reasoning = 'reasoning_tokens';
}
