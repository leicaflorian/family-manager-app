<script setup lang="ts">
/**
 * The white card of the pages. With `list` it holds rows (`FmRow`) separated by
 * a line, without padding inside because the rows have their own, and rows
 * that come and go are animated. The links of the section go beside the title,
 * in `actions`, as `FmSectionLink` (or `FmMenu` with `text`). With the tone
 * `overdue` the title is red with the alert icon and the card has a red ring;
 * a short explanation can go under the card, in `note`. With `count` the
 * number of the rows in all goes beside the title, in grey.
 *
 * @group basics
 */
import { vAutoAnimate } from '@formkit/auto-animate/vue';
import { CircleAlert } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const {
    title,
    count,
    list = false,
    bare = false,
    tone = 'default',
} = defineProps<{
    /** The title of the section, above the content. */
    title?: string;
    /** How many there are in all, beside the title; nothing when 0. */
    count?: number;
    /** It holds a list of rows. */
    list?: boolean;
    /** No surface of its own: the content (tiles, labels, cards of its own) sits on the page under the title. */
    bare?: boolean;
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
                class="m-0 flex gap-2 text-section"
                :class="[
                    tone === 'overdue' && 'text-overdue',
                    // The number sits on the line of the title, as in the boards
                    count ? 'items-baseline' : 'items-center',
                ]"
            >
                <FmIcon
                    v-if="tone === 'overdue'"
                    :icon="CircleAlert"
                    :size="20"
                />{{ title
                }}<template v-if="count">
                    {{ ' '
                    }}<span class="text-body-sm text-ink-soft">{{
                        count
                    }}</span></template
                >
            </h2>
            <div v-if="$slots.actions" class="flex items-center gap-4">
                <slot name="actions" />
            </div>
        </div>
        <div
            v-auto-animate
            data-card
            :data-tone="tone"
            class="flex flex-col"
            :class="[
                !bare && 'overflow-hidden bg-card shadow-card',
                bare && 'gap-2.5',
                !bare && list
                    ? 'divide-y divide-divider rounded-card'
                    : !bare && 'gap-3.5 rounded-card-lg p-5',
                tone === 'overdue' &&
                    'inset-ring-[1.5px] inset-ring-overdue-edge',
            ]"
        >
            <slot />
        </div>
        <p v-if="$slots.note" class="m-0 px-1 text-meta text-ink-soft">
            <slot name="note" />
        </p>
    </section>
</template>
