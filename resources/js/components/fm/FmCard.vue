<script setup lang="ts">
/**
 * The white card of the pages. With `list` it holds rows (`FmRow`) separated by
 * a line, without padding inside because the rows have their own, and rows
 * that come and go are animated. The links of the section go beside the title,
 * in `actions`, as `FmSectionLink` (or `FmMenu` with `text`).
 */
import { vAutoAnimate } from '@formkit/auto-animate/vue';

const { title, list = false } = defineProps<{
    /** The title of the section, above the content. */
    title?: string;
    /** It holds a list of rows. */
    list?: boolean;
}>();

defineSlots<{
    /** The content of the card. */
    default?: () => unknown;
    /** The links beside the title, on the right: `FmSectionLink`, one or more. */
    actions?: () => unknown;
}>();
</script>

<template>
    <section class="flex flex-col gap-2" :aria-label="title">
        <div
            v-if="title || $slots.actions"
            class="flex items-center justify-between gap-3"
        >
            <h2 v-if="title" class="m-0 text-[17px] font-bold">{{ title }}</h2>
            <div v-if="$slots.actions" class="flex items-center gap-4">
                <slot name="actions" />
            </div>
        </div>
        <div
            v-auto-animate
            data-card
            class="flex flex-col overflow-hidden bg-white"
            :class="
                list
                    ? 'divide-y divide-divider rounded-2xl'
                    : 'gap-3.5 rounded-[20px] p-5'
            "
        >
            <slot />
        </div>
    </section>
</template>
