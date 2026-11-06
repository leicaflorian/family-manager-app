<script setup lang="ts">
/**
 * A folded part of a page, such as the closed cards or the planned expenses
 * that ended: a button with its title and, when opened, the content in a card
 * of rows.
 *
 * @group basics
 */
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import FmIcon from './FmIcon.vue';

const { label } = defineProps<{
    /** The text of the button, for example «Closed (2)». */
    label: string;
}>();

defineSlots<{
    /** The rows, shown once the part is open. */
    default?: () => unknown;
}>();

/** The content is shown. */
const open = ref(false);
</script>

<template>
    <div class="flex flex-col gap-2">
        <button
            type="button"
            :aria-expanded="open"
            class="flex items-center gap-1.5 self-start border-0 bg-transparent p-0 text-[15px] font-semibold text-ink-soft"
            @click="open = !open"
        >
            <FmIcon
                :icon="ChevronDown"
                :size="18"
                :class="open && 'rotate-180'"
            />
            {{ label }}
        </button>
        <div
            v-auto-animate
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-card bg-white"
        >
            <slot v-if="open" />
        </div>
    </div>
</template>
