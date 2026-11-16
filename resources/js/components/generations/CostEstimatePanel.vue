<script setup lang="ts">
import { computed } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import { formatMoneyRange, formatRange } from '@/lib/format';
import type { CostEstimate, TokenType } from '@/types';

const props = defineProps<{
    estimate: CostEstimate | null;
    loading: boolean;
    error: string | null;
    markupPercent: number;
}>();

const lines: { type: TokenType; label: string; note: string }[] = [
    {
        type: 'input_text_tokens',
        label: 'Prompt',
        note: 'about 1 token per 4 characters, ±15%',
    },
    {
        type: 'input_image_tokens',
        label: 'Reference images',
        note: '765 tokens each',
    },
    {
        type: 'output_tokens',
        label: 'Image',
        note: 'exact, set by size and quality',
    },
    {
        type: 'reasoning_tokens',
        label: 'Reasoning',
        note: 'varies; only known when it finishes',
    },
];

const price = computed(() =>
    props.estimate
        ? formatMoneyRange(props.estimate.min, props.estimate.max)
        : null,
);
</script>

<template>
    <section
        class="rounded-xl border bg-card p-5 shadow-sm"
        aria-live="polite"
        aria-labelledby="estimate-heading"
    >
        <div class="flex items-center justify-between gap-2">
            <h2
                id="estimate-heading"
                class="text-sm font-medium text-muted-foreground"
            >
                Estimated cost
            </h2>
            <Spinner v-if="loading" class="text-muted-foreground" />
        </div>

        <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
        <template v-else-if="estimate">
            <p
                class="mt-1 text-3xl font-semibold tracking-tight tabular-nums"
                :class="{ 'opacity-60': loading }"
            >
                {{ price }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                You pay what Pixelforge charges plus {{ markupPercent }}%, for
                the tokens actually used. You'll never pay more than
                {{ estimate.max }}. Failed generations are free.
            </p>

            <dl class="mt-4 space-y-2 border-t pt-4 text-sm">
                <div
                    v-for="line in lines"
                    :key="line.type"
                    class="flex items-baseline justify-between gap-4"
                >
                    <dt>
                        {{ line.label }}
                        <span class="block text-xs text-muted-foreground">{{
                            line.note
                        }}</span>
                    </dt>
                    <dd class="whitespace-nowrap tabular-nums">
                        {{
                            formatRange(
                                estimate.tokens[line.type].min,
                                estimate.tokens[line.type].max,
                            )
                        }}
                        tokens
                    </dd>
                </div>
            </dl>
        </template>
        <p v-else class="mt-2 text-sm text-muted-foreground">Calculating…</p>
    </section>
</template>
