<script setup lang="ts">
/**
 * The card of a page or of a list still empty: an icon, what is missing and,
 * below, what can be done.
 */
import type { LucideIcon } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const { icon, title, text } = defineProps<{
    /** The icon at the top. */
    icon: LucideIcon;
    /** What is missing. */
    title: string;
    /** The reason, or what will happen. */
    text?: string;
}>();

defineSlots<{
    /** The buttons. */
    default?: () => unknown;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 rounded-[20px] bg-white px-6 py-10 text-center"
    >
        <span
            class="flex size-14 items-center justify-center rounded-2xl bg-action-soft text-action-dark"
        >
            <FmIcon :icon="icon" :size="28" />
        </span>
        <h2 class="m-0 text-[19px] font-extrabold">{{ title }}</h2>
        <p
            v-if="text"
            class="m-0 max-w-[420px] text-base leading-normal text-ink-soft"
        >
            {{ text }}
        </p>
        <div
            v-if="$slots.default"
            class="mt-2 flex flex-wrap justify-center gap-2.5"
        >
            <slot />
        </div>
    </div>
</template>
