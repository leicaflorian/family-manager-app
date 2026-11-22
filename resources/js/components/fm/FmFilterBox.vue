<script setup lang="ts">
/**
 * The box of the filters of a list on the wide layout: a card under the search
 * bar, a named region that the *Filters* button opens and closes. The fields
 * go in the slot.
 *
 * @group panels
 */
import { trans } from 'laravel-vue-i18n';

const { label = trans('Filters') } = defineProps<{
    /** The id the button points at with `aria-controls`. */
    id: string;
    /** The name of the region; *Filters* when not given. */
    label?: string;
}>();

defineSlots<{
    /** The fields of the filters. */
    default(): unknown;
}>();
</script>

<template>
    <section
        :id="id"
        :aria-label="label"
        class="rounded-card bg-card px-4.5 py-4 shadow-card"
    >
        <slot />
    </section>
</template>
