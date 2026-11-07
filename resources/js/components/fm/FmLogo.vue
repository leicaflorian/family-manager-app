<script setup lang="ts">
/**
 * The Family Manager logo: the roof and the family, white (`on-hero`, white in
 * both themes) on the light blue square (`brand`).
 * The white version, without the square, goes on the blue panel of the sign-in pages.
 *
 * @group basics
 */
const { size = 36, white = false } = defineProps<{
    /** The side in pixels. */
    size?: number;
    /** Only the white drawing, without the square. */
    white?: boolean;
}>();
</script>

<template>
    <svg
        aria-hidden="true"
        :width="size"
        :height="size"
        viewBox="0 0 500 500"
        class="shrink-0 text-on-hero"
    >
        <rect
            v-if="!white"
            width="500"
            height="500"
            class="fill-brand"
            rx="80"
        />
        <path
            d="M 60,300 L 250,110 L 440,300"
            stroke="currentColor"
            stroke-width="55"
            stroke-linecap="round"
            stroke-linejoin="round"
            fill="none"
        />
        <circle cx="155" cy="300" r="65" fill="currentColor" />
        <circle cx="345" cy="300" r="65" fill="currentColor" />
        <circle cx="250" cy="315" r="30" fill="currentColor" />
    </svg>
</template>
