export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type TokenType =
    | 'input_text_tokens'
    | 'input_image_tokens'
    | 'output_tokens'
    | 'reasoning_tokens';

/** Money arrives pre-formatted (e.g. "$0.058125") and exact: the server owns all money math. */
export type Generation = {
    id: string;
    status: GenerationStatus;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    estimated_cost: { min: string; max: string };
    charged_cost: string | null;
    usage: Record<TokenType, number> | null;
    image_url: string | null;
    image_expired: boolean;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
};

export type CostEstimate = {
    min: string;
    max: string;
    tokens: Record<TokenType, { min: number; max: number }>;
};

export type Option = { value: string; label: string };
