<script setup lang="ts">
/**
 * The white card of the pages. With `list` it holds rows (`FmRow`) separated by
 * a line, without padding inside because the rows have their own. The buttons
 * of the section go beside the title, in `actions`.
 */
const { title, list = false } = defineProps<{
    /** The title of the section, above the content. */
    title?: string;
    /** It holds a list of rows. */
    list?: boolean;
}>();

defineSlots<{
    /** The content of the card. */
    default?: () => unknown;
    /** The buttons beside the title, on the right. */
    actions?: () => unknown;
}>();
</script>

<template>
    <section class="flex flex-col gap-2">
        <div
            v-if="title || $slots.actions"
            class="flex items-center justify-between gap-3"
        >
            <h2 v-if="title" class="m-0 text-[17px] font-bold">{{ title }}</h2>
            <slot name="actions" />
        </div>
        <div
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
