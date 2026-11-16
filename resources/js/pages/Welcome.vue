<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { homeUrl } from '@/lib/navigation';
import { login, register } from '@/routes';

const points = [
    {
        title: 'See the price first',
        body: 'Every generation shows a guaranteed price range before you start.',
    },
    {
        title: 'Pay for what you use',
        body: 'You are charged the exact cost of the tokens used, plus 25%.',
    },
    {
        title: 'Failures are free',
        body: 'If a generation fails, you pay nothing.',
    },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="flex items-center justify-between gap-4 p-6">
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-6 fill-current" />
                {{ $page.props.name }}
            </div>
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="homeUrl()">Open the app</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Create an account</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-3xl flex-1 flex-col justify-center gap-10 px-6 pb-16"
        >
            <div class="space-y-4">
                <h1
                    class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Generate images from a prompt.
                </h1>
                <p class="max-w-xl text-lg text-muted-foreground">
                    Powered by Pixelforge. Write a prompt, pick a size and
                    quality, and see exactly what it will cost.
                </p>
                <Button size="lg" as-child>
                    <Link
                        :href="$page.props.auth.user ? homeUrl() : register()"
                    >
                        <Sparkles /> Start generating
                    </Link>
                </Button>
            </div>

            <ul class="grid gap-4 sm:grid-cols-3">
                <li
                    v-for="point in points"
                    :key="point.title"
                    class="rounded-xl border p-4"
                >
                    <h2 class="font-medium">{{ point.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ point.body }}
                    </p>
                </li>
            </ul>
        </main>
    </div>
</template>
