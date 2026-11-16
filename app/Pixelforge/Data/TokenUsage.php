<?php

namespace App\Pixelforge\Data;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {}

    /** @param array<string, mixed> $usage The API's usage object */
    public static function fromArray(array $usage): self
    {
        $count = static fn (TokenType $type): int => (int) ($usage[$type->value] ?? 0);

        return new self(
            inputText: $count(TokenType::InputText),
            inputImage: $count(TokenType::InputImage),
            output: $count(TokenType::Output),
            reasoning: $count(TokenType::Reasoning),
        );
    }

    public function of(TokenType $type): int
    {
        return match ($type) {
            TokenType::InputText => $this->inputText,
            TokenType::InputImage => $this->inputImage,
            TokenType::Output => $this->output,
            TokenType::Reasoning => $this->reasoning,
        };
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        $tokens = [];

        foreach (TokenType::cases() as $type) {
            $tokens[$type->value] = $this->of($type);
        }

        return $tokens;
    }
}
