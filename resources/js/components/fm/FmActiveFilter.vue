<script setup lang="ts">
/**
 * A chosen filter, above a list: it is removed with the ×.
 *
 * Rules: a pill of 28 px, `action-soft` with `action-dark` text, bold at 14 px;
 * the whole pill is the button. Its name for a screen reader is *Remove the
 * filter «label»*, unless `removeLabel` says something more exact. A long
 * label is written whole: the pill grows with it.
 *
 * @group fields
 */
import { X } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const { label, removeLabel } = defineProps<{
    /** The filter, for example «Laura» or «Work and income». */
    label: string;
    /** What the × says to a screen reader, when it does not remove a filter. */
    removeLabel?: string;
}>();

defineEmits<{
    /** The filter must be removed. */
    remove: [];
}>();
</script>

<template>
    <button
        type="button"
        :aria-label="removeLabel ?? $t('Remove the filter :label', { label })"
        class="inline-flex h-7 items-center gap-1.5 rounded-full bg-action-soft py-0 pr-2.5 pl-3.5 text-label text-action-dark"
        @click="$emit('remove')"
    >
        <span>{{ label }}</span>
        <FmIcon :icon="X" :size="16" />
    </button>
</template>
