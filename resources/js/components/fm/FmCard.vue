<script setup lang="ts">
/**
 * The white card of the pages. With `list` it holds rows (`FmRow`) separated by
 * a line, without padding inside because the rows have their own.
 */
const { title, list = false } = defineProps<{
    /** The title of the section, above the content. */
    title?: string;
    /** It holds a list of rows. */
    list?: boolean;
}>();
</script>

<template>
    <section class="flex flex-col gap-2">
        <h2 v-if="title" class="m-0 text-[17px] font-bold">{{ title }}</h2>
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
