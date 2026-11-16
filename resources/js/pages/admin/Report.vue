<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNumber } from '@/lib/format';
import { report } from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Spend report', href: report() }],
    },
});

type Money = { charged: string; provider_cost: string; profit: string };

defineProps<{
    from: string;
    to: string;
    reconciled: boolean;
    days: (Money & {
        day: string;
        generations: number;
        pixelforge: {
            generations: number;
            cost: string;
            matches: boolean;
        } | null;
    })[];
    rows: (Money & { day: string; user: string; generations: number })[];
    totals: Money & { generations: number };
}>();
</script>

<template>
    <Head title="Spend report" />

    <div class="flex flex-1 flex-col gap-8 p-4 lg:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Spend report"
                description="Per UTC day of completion, the same days Pixelforge bills on. Amounts are exact."
            />
            <Form
                v-bind="report.form()"
                class="flex flex-wrap items-end gap-2"
                v-slot="{ errors }"
            >
                <div class="grid gap-1">
                    <Label for="from">From</Label>
                    <Input
                        id="from"
                        type="date"
                        name="from"
                        :default-value="from"
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="to">To</Label>
                    <Input id="to" type="date" name="to" :default-value="to" />
                </div>
                <Button type="submit" variant="outline">Show</Button>
                <p v-if="errors.to" class="w-full text-sm text-destructive">
                    {{ errors.to }}
                </p>
            </Form>
        </div>

        <dl class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Generations</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatNumber(totals.generations) }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Credit spend</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ totals.charged }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Provider costs</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ totals.provider_cost }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Profit</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ totals.profit }}
                </dd>
            </div>
        </dl>

        <section class="space-y-3">
            <h2 class="font-medium">By day</h2>
            <p
                v-if="!reconciled"
                class="rounded-md bg-amber-500/10 px-3 py-2 text-sm text-amber-800 dark:text-amber-200"
            >
                Pixelforge's usage report couldn't be loaded, so provider costs
                aren't reconciled right now.
            </p>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Day (UTC)</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Generations
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Credit spend
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Profit
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Pixelforge says
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y tabular-nums">
                        <tr v-for="day in days" :key="day.day">
                            <td class="px-4 py-2">{{ day.day }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ day.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ day.charged }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ day.provider_cost }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ day.profit }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <span
                                    v-if="day.pixelforge"
                                    class="inline-flex items-center gap-1"
                                    :class="
                                        day.pixelforge.matches
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-red-700 dark:text-red-300'
                                    "
                                    :title="
                                        day.pixelforge.matches
                                            ? 'Matches our records'
                                            : 'Differs from our records'
                                    "
                                >
                                    <CircleCheck
                                        v-if="day.pixelforge.matches"
                                        class="size-4"
                                    />
                                    <CircleAlert v-else class="size-4" />
                                    {{ day.pixelforge.cost }} ·
                                    {{ day.pixelforge.generations }}
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                        </tr>
                        <tr v-if="days.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No completed generations in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-medium">By day and user</h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Day (UTC)</th>
                            <th class="px-4 py-2 font-medium">User</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Generations
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Credit spend
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y tabular-nums">
                        <tr v-for="row in rows" :key="`${row.day}-${row.user}`">
                            <td class="px-4 py-2">{{ row.day }}</td>
                            <td class="px-4 py-2">{{ row.user }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ row.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ row.charged }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ row.provider_cost }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ row.profit }}
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No completed generations in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
