<script setup lang="ts">
/**
 * A Lucide icon with the sizes of the canvas: stroke 2, rounded, 24 px.
 * Without a label it is only decoration and screen reader users do not hear it.
 */
import type { LucideIcon } from '@lucide/vue';

const {
    size = 24,
    stroke = 2,
    label,
} = defineProps<{
    /** The icon, imported from `@lucide/vue`. */
    icon: LucideIcon;
    /** The side in pixels. */
    size?: number;
    /** The width of the stroke. */
    stroke?: number;
    /** What the icon says, when there is no text next to it. */
    label?: string;
}>();
</script>

<template>
    <component
        :is="icon"
        :size="size"
        :stroke-width="stroke"
        :aria-hidden="label ? undefined : 'true'"
        :role="label ? 'img' : undefined"
        :aria-label="label"
        class="shrink-0"
    />
</template>
