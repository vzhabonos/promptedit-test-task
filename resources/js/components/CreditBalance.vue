<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as credits } from '@/routes/credits';
</script>

<template>
    <Link
        v-if="$page.props.balance !== null"
        :href="credits()"
        class="mx-2 flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm group-data-[collapsible=icon]:hidden hover:bg-sidebar-accent"
    >
        <span class="text-muted-foreground">Balance</span>
        <span class="font-medium tabular-nums">{{ $page.props.balance }}</span>
    </Link>
</template>
