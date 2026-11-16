<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CreditController from '@/actions/App/Http/Controllers/CreditController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/format';
import { index } from '@/routes/credits';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Credits', href: index() }],
    },
});

defineProps<{
    packages: number[];
    purchases: { id: number; amount: string; created_at: string }[];
}>();
</script>

<template>
    <Head title="Credits" />

    <div class="flex max-w-3xl flex-1 flex-col gap-8 p-4 lg:p-6">
        <Heading
            title="Credits"
            description="Credits are US dollars. Generations are paid from your balance; failed ones are free."
        />

        <section class="rounded-xl border bg-card p-6">
            <p class="text-sm text-muted-foreground">Available balance</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight tabular-nums">
                {{ $page.props.balance }}
            </p>
            <p class="mt-2 text-xs text-muted-foreground">
                While a generation runs, the most it can cost is set aside. When
                it finishes, you're charged what it actually cost and the rest
                is released.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">Buy credits</h2>
            <p class="text-xs text-muted-foreground">
                This is a demo: no payment is taken.
            </p>
            <div class="flex flex-wrap gap-3">
                <Form
                    v-for="amount in packages"
                    :key="amount"
                    v-bind="CreditController.store.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="amount" :value="amount" />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                    >
                        Add ${{ amount }}
                    </Button>
                </Form>
            </div>
        </section>

        <section v-if="purchases.length" class="space-y-3">
            <h2 class="text-sm font-medium">Recent purchases</h2>
            <ul class="divide-y rounded-xl border">
                <li
                    v-for="purchase in purchases"
                    :key="purchase.id"
                    class="flex justify-between px-4 py-3 text-sm"
                >
                    <span class="tabular-nums">+{{ purchase.amount }}</span>
                    <time
                        :datetime="purchase.created_at"
                        class="text-muted-foreground"
                        >{{ formatRelativeTime(purchase.created_at) }}</time
                    >
                </li>
            </ul>
        </section>
    </div>
</template>
