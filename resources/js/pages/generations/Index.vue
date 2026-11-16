<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { computed, watch } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/generations';
import type { Generation } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'History', href: index() }],
    },
});

const props = defineProps<{
    generations: {
        data: Generation[];
        links: { prev: string | null; next: string | null };
        meta: { current_page: number; last_page: number; total: number };
    };
}>();

// Refresh statuses every few seconds, but only while something is running.
const hasRunning = computed(() =>
    props.generations.data.some((generation) =>
        ['pending', 'queued', 'processing'].includes(generation.status),
    ),
);
const { start, stop } = usePoll(
    3000,
    { only: ['generations', 'balance'] },
    { autoStart: false },
);
watch(hasRunning, (running) => (running ? start() : stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="History" />

    <div class="flex flex-1 flex-col gap-6 p-4 lg:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Your generations"
                description="Status updates live. You're charged only for generations that succeed."
            />
            <Button as-child>
                <Link :href="create()"><Sparkles /> New generation</Link>
            </Button>
        </div>

        <div
            v-if="generations.data.length === 0"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center"
        >
            <p class="text-muted-foreground">No generations yet.</p>
            <Button as-child>
                <Link :href="create()"
                    ><Sparkles /> Generate your first image</Link
                >
            </Button>
        </div>

        <div
            v-else
            class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <GenerationCard
                v-for="generation in generations.data"
                :key="generation.id"
                :generation="generation"
            />
        </div>

        <nav
            v-if="generations.meta.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Pagination"
        >
            <Button variant="outline" size="sm" as-child>
                <Link
                    :href="generations.links.prev ?? ''"
                    preserve-scroll
                    :class="{
                        'pointer-events-none opacity-50':
                            !generations.links.prev,
                    }"
                    >Newer</Link
                >
            </Button>
            <span class="text-muted-foreground">
                Page {{ generations.meta.current_page }} of
                {{ generations.meta.last_page }}
            </span>
            <Button variant="outline" size="sm" as-child>
                <Link
                    :href="generations.links.next ?? ''"
                    preserve-scroll
                    :class="{
                        'pointer-events-none opacity-50':
                            !generations.links.next,
                    }"
                    >Older</Link
                >
            </Button>
        </nav>
    </div>
</template>
