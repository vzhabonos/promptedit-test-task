<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineProps<{
    max: number;
    errors: Record<string, string | undefined>;
}>();

const urls = defineModel<string[]>({ required: true });

// Bound rather than static: vue-tsc mis-parses a literal "//" in component attributes.
const examplePlaceholder = 'https://example.com/style.png';

function update(position: number, value: string | number): void {
    urls.value[position] = String(value);
}
</script>

<template>
    <fieldset class="grid gap-2">
        <legend class="text-sm font-medium">
            Reference images
            <span class="font-normal text-muted-foreground"
                >(optional, up to {{ max }})</span
            >
        </legend>
        <p class="text-xs text-muted-foreground">
            Public image URLs that guide the style.
        </p>

        <div v-for="(url, position) in urls" :key="position" class="grid gap-1">
            <div class="flex gap-2">
                <Input
                    :model-value="url"
                    type="url"
                    inputmode="url"
                    :placeholder="examplePlaceholder"
                    :aria-label="`Reference image ${position + 1}`"
                    :aria-invalid="!!errors[`reference_images.${position}`]"
                    @update:model-value="update(position, $event)"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Remove reference image ${position + 1}`"
                    @click="urls.splice(position, 1)"
                >
                    <X />
                </Button>
            </div>
            <InputError :message="errors[`reference_images.${position}`]" />
        </div>

        <div v-if="urls.length < max">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="urls.push('')"
            >
                <Plus /> Add reference image
            </Button>
        </div>
        <InputError :message="errors.reference_images" />
    </fieldset>
</template>
