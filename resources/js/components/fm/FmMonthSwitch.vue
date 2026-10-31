<script setup lang="ts">
/**
 * The month chosen, with ‹ and › to go to the one before and after. The month
 * is written in the language of the page.
 *
 * @group navigation
 */
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { monthName } from '@/lib/budget';
import FmIcon from './FmIcon.vue';

const { month } = defineProps<{
    /** The month, `yyyy-mm`. */
    month: string;
}>();

const emit = defineEmits<{
    /** The person chose another month: `yyyy-mm`. */
    change: [month: string];
}>();

/** The month written in words. */
const label = computed(() => monthName(month));

/**
 * Asks for the month a number of months away.
 *
 * @param step - -1 for the month before, 1 for the one after
 */
function go(step: number): void {
    const [year, number] = month.split('-').map(Number);
    const moved = new Date(year, number - 1 + step, 1);

    emit(
        'change',
        `${moved.getFullYear()}-${String(moved.getMonth() + 1).padStart(2, '0')}`,
    );
}
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            type="button"
            class="flex size-11 cursor-pointer items-center justify-center rounded-xl border-0 bg-transparent text-ink hover:bg-action-soft"
            :aria-label="$t('Previous month')"
            @click="go(-1)"
        >
            <FmIcon :icon="ChevronLeft" :size="22" />
        </button>
        <span class="min-w-[9.5rem] text-center text-lg font-extrabold">{{
            label
        }}</span>
        <button
            type="button"
            class="flex size-11 cursor-pointer items-center justify-center rounded-xl border-0 bg-transparent text-ink hover:bg-action-soft"
            :aria-label="$t('Next month')"
            @click="go(1)"
        >
            <FmIcon :icon="ChevronRight" :size="22" />
        </button>
    </div>
</template>
