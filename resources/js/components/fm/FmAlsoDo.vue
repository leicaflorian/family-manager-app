<script setup lang="ts">
/**
 * The block *What I do too* of the Import page and of the proposal of a file,
 * as the board *Importa* draws it: a bold heading and, stacked under it, what
 * the reading creates besides the document (an expense, a payment, a link to
 * a card), each one an `FmAlsoDoItem` the person can untick or correct. The
 * page decides which items there are.
 *
 * @group sections
 */
const { title = undefined } = defineProps<{
    /** The heading; without it, *What I do too*. */
    title?: string;
}>();

defineSlots<{
    /** The items: `FmAlsoDoItem`, one or more. */
    default?: () => unknown;
}>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <h3 class="m-0 mt-2 text-base font-bold">
            {{ title ?? $t('What I do too') }}
        </h3>
        <slot />
    </div>
</template>
