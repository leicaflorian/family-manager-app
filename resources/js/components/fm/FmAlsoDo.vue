<script setup lang="ts">
/**
 * The box *What I do too* of the Import page and of the proposal of a file:
 * what the reading creates besides the document (an expense, a payment, a
 * link to a card), each one an `FmAlsoDoItem` the person can untick or
 * correct. The page decides which items there are.
 *
 * @group sections
 */
import FmCard from './FmCard.vue';

const { title = undefined } = defineProps<{
    /** The title of the box; without it, *What I do too*. */
    title?: string;
}>();

defineSlots<{
    /** The items: `FmAlsoDoItem`, one or more. */
    default?: () => unknown;
}>();
</script>

<template>
    <FmCard :title="title ?? $t('What I do too')">
        <div class="flex flex-col divide-y divide-divider">
            <slot />
        </div>
    </FmCard>
</template>
