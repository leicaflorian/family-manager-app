<script setup lang="ts">
/**
 * The footer of a box that shows a list a few rows at a time: a full-width
 * outline button, «Show 5 more · 19 left», under the last row (the boards
 * *Persona-tanti* and *Persona-tanti-pc*). The caller writes the text
 * (`showMoreLabel` of `lib/showMore.ts`) and shows the rows; while they load
 * the button cannot be tapped.
 *
 * @group basics
 */
import { ChevronDown } from '@lucide/vue';
import FmButton from './FmButton.vue';

const { label, loading = false } = defineProps<{
    /** The text of the button: how many rows come and how many are left. */
    label: string;
    /** The next rows are loading. */
    loading?: boolean;
}>();

defineEmits<{
    /** Show the next rows. */
    more: [];
}>();
</script>

<template>
    <div class="border-t border-divider px-4 py-3">
        <FmButton
            variant="outline"
            :icon="ChevronDown"
            wide
            :disabled="loading"
            @click="$emit('more')"
            >{{ label }}</FmButton
        >
    </div>
</template>
