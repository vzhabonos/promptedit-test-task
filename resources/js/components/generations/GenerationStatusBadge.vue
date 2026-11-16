<script setup lang="ts">
import { CircleAlert, CircleCheck, Clock, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { GenerationStatus } from '@/types';

const props = defineProps<{ status: GenerationStatus }>();

const appearance = {
    pending: {
        label: 'Waiting for Pixelforge',
        icon: Clock,
        spin: false,
        class: 'bg-muted text-muted-foreground',
    },
    queued: {
        label: 'Queued',
        icon: Clock,
        spin: false,
        class: 'bg-muted text-muted-foreground',
    },
    processing: {
        label: 'Processing',
        icon: LoaderCircle,
        spin: true,
        class: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    },
    succeeded: {
        label: 'Succeeded',
        icon: CircleCheck,
        spin: false,
        class: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    failed: {
        label: 'Failed',
        icon: CircleAlert,
        spin: false,
        class: 'bg-red-500/10 text-red-700 dark:text-red-300',
    },
} as const;

const current = computed(() => appearance[props.status]);
</script>

<template>
    <Badge variant="outline" :class="['border-transparent', current.class]">
        <component
            :is="current.icon"
            :class="{ 'animate-spin': current.spin }"
        />
        {{ current.label }}
    </Badge>
</template>
