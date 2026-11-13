<script setup lang="ts">
/**
 * The block *What I do too* of the Import page and of the proposal of a file,
 * as the board *Importa* draws it: a bold heading and, stacked under it, what
 * the reading creates besides the document (an expense, a payment, a link to
 * a card), each one an `FmAlsoDoItem` the person can untick or correct, with
 * an optional line under the heading. The
 * page decides which items there are; an item that comes or goes slides in
 * or out.
 *
 * @group sections
 */
const { title = undefined, hint = undefined } = defineProps<{
    /** The heading; without it, *What I do too*. */
    title?: string;
    /** A line under the heading, for example that the proposals follow the kind of the document. */
    hint?: string;
}>();

defineSlots<{
    /** The items: `FmAlsoDoItem`, one or more. */
    default?: () => unknown;
}>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <h3 class="m-0 mt-2 text-amount">
            {{ title ?? $t('What I do too') }}
        </h3>
        <p v-if="hint" class="m-0 -mt-1.5 text-meta text-ink-soft">
            {{ hint }}
        </p>
        <div v-auto-animate class="flex flex-col gap-3">
            <slot />
        </div>
    </div>
</template>
