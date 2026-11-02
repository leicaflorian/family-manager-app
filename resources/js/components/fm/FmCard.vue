<script setup lang="ts">
/**
 * The white card of the pages. With `list` it holds rows (`FmRow`) separated by
 * a line, without padding inside because the rows have their own, and rows
 * that come and go are animated. The links of the section go beside the title,
 * in `actions`, as `FmSectionLink` (or `FmMenu` with `text`). With the tone
 * `overdue` the title is red with the alert icon and the card has a red ring;
 * a short explanation can go under the card, in `note`.
 *
 * @group basics
 */
import { vAutoAnimate } from '@formkit/auto-animate/vue';
import { CircleAlert } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const {
    title,
    list = false,
    tone = 'default',
} = defineProps<{
    /** The title of the section, above the content. */
    title?: string;
    /** It holds a list of rows. */
    list?: boolean;
    /** The colour of the title and of the ring: `overdue` is red, for what is late. */
    tone?: 'default' | 'overdue';
}>();

defineSlots<{
    /** The content of the card. */
    default?: () => unknown;
    /** The links beside the title, on the right: `FmSectionLink`, one or more. */
    actions?: () => unknown;
    /** A short explanation under the card, in grey. */
    note?: () => unknown;
}>();
</script>

<template>
    <section class="flex flex-col gap-2" :aria-label="title">
        <div
            v-if="title || $slots.actions"
            class="flex items-center justify-between gap-3"
        >
            <h2
                v-if="title"
                class="m-0 flex items-center gap-2 text-[17px] font-bold"
                :class="tone === 'overdue' && 'text-overdue'"
            >
                <FmIcon
                    v-if="tone === 'overdue'"
                    :icon="CircleAlert"
                    :size="20"
                />{{ title }}
            </h2>
            <div v-if="$slots.actions" class="flex items-center gap-4">
                <slot name="actions" />
            </div>
        </div>
        <div
            v-auto-animate
            data-card
            :data-tone="tone"
            class="flex flex-col overflow-hidden bg-white"
            :class="[
                list
                    ? 'divide-y divide-divider rounded-2xl'
                    : 'gap-3.5 rounded-[20px] p-5',
                tone === 'overdue' && 'shadow-[inset_0_0_0_1.5px_#F4C7CE]',
            ]"
        >
            <slot />
        </div>
        <p v-if="$slots.note" class="m-0 px-1 text-[13px] text-ink-soft">
            <slot name="note" />
        </p>
    </section>
</template>
