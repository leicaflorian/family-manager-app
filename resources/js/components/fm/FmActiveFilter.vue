<script setup lang="ts">
/**
 * A chosen filter, above a list: it is removed with the ×.
 */
import { X } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const { label } = defineProps<{
    /** The filter, for example «Laura» or «Work and income». */
    label: string;
}>();

defineEmits<{
    /** The filter must be removed. */
    remove: [];
}>();
</script>

<template>
    <button
        type="button"
        :aria-label="$t('Remove the filter :label', { label })"
        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-action-soft py-0 pr-2.5 pl-3.5 text-sm font-semibold text-action-dark"
        @click="$emit('remove')"
    >
        <span>{{ label }}</span>
        <FmIcon :icon="X" :size="16" />
    </button>
</template>
