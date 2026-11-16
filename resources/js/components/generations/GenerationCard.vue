<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImageOff, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import GenerationStatusBadge from '@/components/generations/GenerationStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatMoneyRange, formatRelativeTime } from '@/lib/format';
import { create } from '@/routes/generations';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();

// Pixelforge's signed URLs expire after 24 hours.
const imageBroken = ref(false);
const showImage = computed(
    () => props.generation.image_url !== null && !imageBroken.value,
);
const isRunning = computed(() =>
    ['pending', 'queued', 'processing'].includes(props.generation.status),
);
</script>

<template>
    <article class="flex flex-col overflow-hidden rounded-xl border bg-card">
        <div class="flex aspect-[4/3] items-center justify-center bg-muted/50">
            <a
                v-if="showImage"
                :href="generation.image_url!"
                target="_blank"
                rel="noopener"
                class="size-full"
            >
                <img
                    :src="generation.image_url!"
                    :alt="generation.prompt"
                    loading="lazy"
                    class="size-full object-contain"
                    @error="imageBroken = true"
                />
            </a>
            <div
                v-else
                class="flex flex-col items-center gap-2 p-4 text-center text-sm text-muted-foreground"
            >
                <Spinner v-if="isRunning" class="size-6" />
                <template v-else-if="generation.status === 'succeeded'">
                    <ImageOff class="size-6" />
                    Image link expired
                </template>
                <template v-else>
                    <ImageOff class="size-6" />
                    No image
                </template>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2">
                <GenerationStatusBadge :status="generation.status" />
                <time
                    :datetime="generation.created_at"
                    :title="new Date(generation.created_at).toLocaleString()"
                    class="text-xs text-muted-foreground"
                >
                    {{ formatRelativeTime(generation.created_at) }}
                </time>
            </div>

            <p class="line-clamp-3 text-sm" :title="generation.prompt">
                {{ generation.prompt }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ generation.size }} · {{ generation.quality }} quality
                <template v-if="generation.reference_images.length">
                    · {{ generation.reference_images.length }} reference image{{
                        generation.reference_images.length > 1 ? 's' : ''
                    }}
                </template>
            </p>

            <p
                v-if="
                    generation.status === 'failed' && generation.error_message
                "
                class="rounded-md bg-red-500/10 px-3 py-2 text-sm text-red-700 dark:text-red-300"
            >
                {{ generation.error_message }}
            </p>

            <dl class="mt-auto space-y-1 border-t pt-3 text-sm tabular-nums">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Estimated</dt>
                    <dd class="text-right">
                        {{
                            formatMoneyRange(
                                generation.estimated_cost.min,
                                generation.estimated_cost.max,
                            )
                        }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Charged</dt>
                    <dd
                        v-if="generation.status === 'failed'"
                        class="text-right"
                    >
                        $0.00 · not charged
                    </dd>
                    <dd
                        v-else-if="generation.charged_cost"
                        class="text-right font-medium"
                    >
                        {{ generation.charged_cost }}
                    </dd>
                    <dd v-else class="text-right text-muted-foreground">
                        when it finishes
                    </dd>
                </div>
            </dl>

            <Button
                v-if="generation.status === 'failed'"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="create({ query: { from: generation.id } })">
                    <RotateCcw /> Try again
                </Link>
            </Button>
        </div>
    </article>
</template>
