<script setup lang="ts">
/**
 * The card of a page or of a list still empty: an icon, what is missing and,
 * below, what can be done. The icon is blue; `tone="paid"` makes it green, when
 * the empty page is good news (*Nothing left to sort*).
 *
 * @group basics
 */
import type { LucideIcon } from '@lucide/vue';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const {
    icon,
    title,
    text,
    tone = 'action',
} = defineProps<{
    /** The icon at the top. */
    icon: LucideIcon;
    /** What is missing. */
    title: string;
    /** The reason, or what will happen. */
    text?: string;
    /** The colour of the icon: blue, or green when the empty page is good news. */
    tone?: 'action' | 'paid';
}>();

/** The colours of the square of the icon, for each tone. */
const toneClass: Record<'action' | 'paid', string> = {
    action: cn('bg-action-soft text-action-dark'),
    paid: cn('bg-paid-soft text-paid'),
};

defineSlots<{
    /** The buttons. */
    default?: () => unknown;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 rounded-card-lg bg-card px-6 py-10 text-center shadow-card"
    >
        <span
            :class="
                cn(
                    'flex size-14 items-center justify-center rounded-card',
                    toneClass[tone],
                )
            "
        >
            <FmIcon :icon="icon" :size="28" />
        </span>
        <h2 class="m-0 text-sheet-title">{{ title }}</h2>
        <p v-if="text" class="m-0 max-w-[420px] text-body text-ink-soft">
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
