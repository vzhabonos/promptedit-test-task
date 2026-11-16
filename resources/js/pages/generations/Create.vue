<script setup lang="ts">
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core';
import { Head, useForm, useHttp } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import CostEstimatePanel from '@/components/generations/CostEstimatePanel.vue';
import ReferenceImagesField from '@/components/generations/ReferenceImagesField.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { create } from '@/routes/generations';
import type { CostEstimate, Option } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: create() }],
    },
});

const props = defineProps<{
    sizes: Option[];
    qualities: Option[];
    limits: { prompt: number; referenceImages: number };
    markupPercent: number;
    previous: {
        prompt: string;
        size: string;
        quality: string;
        reference_images: string[];
    } | null;
}>();

type GenerationInput = {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
};

const form = useForm<GenerationInput>({
    prompt: props.previous?.prompt ?? '',
    size: props.previous?.size ?? '1024x1024',
    quality: props.previous?.quality ?? 'medium',
    reference_images: [...(props.previous?.reference_images ?? [])],
});

/** Empty URL rows are just unused inputs, not part of the request. */
const withoutBlankUrls = (data: GenerationInput): GenerationInput => ({
    ...data,
    reference_images: data.reference_images
        .map((url) => url.trim())
        .filter(Boolean),
});

form.transform(withoutBlankUrls);

// The estimate is priced by the server, so the formula lives in one place.
const estimator = useHttp<GenerationInput, CostEstimate>(form.data());
estimator.transform(() => withoutBlankUrls(form.data()));
const estimateError = ref<string | null>(null);

async function refreshEstimate(): Promise<void> {
    estimator.cancel();

    try {
        // Invalid input (422) resolves with the field errors set, not a throw.
        await estimator.post(GenerationController.estimate.url());
        estimateError.value = estimator.hasErrors
            ? 'Fix the highlighted fields to see the price.'
            : null;
    } catch (error) {
        if (error instanceof HttpCancelledError) {
            return;
        }

        estimateError.value =
            messageFrom(error) ??
            'We could not price this right now. Please try again.';
    }
}

function messageFrom(error: unknown): string | null {
    if (!(error instanceof HttpResponseError)) {
        return null;
    }

    try {
        return JSON.parse(error.response.data).message ?? null;
    } catch {
        return null;
    }
}

watchDebounced(
    () => [form.prompt, form.size, form.quality, form.reference_images],
    refreshEstimate,
    { debounce: 300, deep: true, immediate: true },
);

// Live validation from the estimate request, then the real submit's errors.
const errors = computed<Record<string, string | undefined>>(() => ({
    ...estimator.errors,
    ...form.errors,
}));

function submit(): void {
    form.submit(GenerationController.store());
}

const optionClass = (selected: boolean) =>
    cn(
        'flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border px-3 py-3 text-sm transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
        selected
            ? 'border-primary bg-primary/5 font-medium'
            : 'hover:bg-accent',
    );
</script>

<template>
    <Head title="Generate" />

    <div class="flex flex-1 flex-col gap-6 p-4 lg:p-6">
        <Heading
            title="Generate an image"
            description="Describe the image, pick a size and quality, and see the price before you start."
        />

        <form
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            @submit.prevent="submit"
        >
            <div class="space-y-6">
                <div class="grid gap-2">
                    <div class="flex items-baseline justify-between">
                        <Label for="prompt">Prompt</Label>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            :class="{
                                'text-destructive':
                                    form.prompt.length > limits.prompt,
                            }"
                        >
                            {{ form.prompt.length.toLocaleString() }} /
                            {{ limits.prompt.toLocaleString() }}
                        </span>
                    </div>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        v-focus
                        rows="5"
                        required
                        :maxlength="limits.prompt"
                        placeholder="A lighthouse on a cliff at dawn, oil painting"
                        :aria-invalid="!!errors.prompt"
                        class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                    />
                    <InputError :message="errors.prompt" />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Size</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="size in sizes"
                            :key="size.value"
                            :class="optionClass(form.size === size.value)"
                        >
                            <input
                                v-model="form.size"
                                type="radio"
                                name="size"
                                :value="size.value"
                                class="sr-only"
                            />
                            <span
                                aria-hidden="true"
                                class="rounded-sm border-2 border-current opacity-60"
                                :class="{
                                    'h-5 w-5': size.value === '1024x1024',
                                    'h-4 w-6': size.value === '1536x1024',
                                    'h-6 w-4': size.value === '1024x1536',
                                }"
                            />
                            <span>{{ size.label }}</span>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                >{{ size.value }}</span
                            >
                        </label>
                    </div>
                    <InputError :message="errors.size" />
                </fieldset>

                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Quality</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="quality in qualities"
                            :key="quality.value"
                            :class="optionClass(form.quality === quality.value)"
                        >
                            <input
                                v-model="form.quality"
                                type="radio"
                                name="quality"
                                :value="quality.value"
                                class="sr-only"
                            />
                            {{ quality.label }}
                        </label>
                    </div>
                    <InputError :message="errors.quality" />
                </fieldset>

                <ReferenceImagesField
                    v-model="form.reference_images"
                    :max="limits.referenceImages"
                    :errors="errors"
                />
            </div>

            <div class="space-y-4 lg:sticky lg:top-4">
                <CostEstimatePanel
                    :estimate="estimator.response"
                    :loading="estimator.processing"
                    :error="estimateError"
                    :markup-percent="markupPercent"
                />
                <InputError :message="errors.form" />
                <Button
                    type="submit"
                    size="lg"
                    class="w-full"
                    :disabled="
                        form.processing ||
                        estimator.response?.affordable === false
                    "
                >
                    <Spinner v-if="form.processing" />
                    <Sparkles v-else />
                    Generate
                </Button>
            </div>
        </form>
    </div>
</template>
