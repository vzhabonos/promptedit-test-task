<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- A framed picture: mountains and a sun. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill-rule="evenodd"
            d="M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm1 2v14h14V5H5Z"
        />
        <path d="M6 17l4-5 3 3.5 2-2.5 3 4H6Z" />
        <path d="M9 7.25a1.75 1.75 0 1 1 0 3.5a1.75 1.75 0 1 1 0-3.5Z" />
    </svg>
</template>
