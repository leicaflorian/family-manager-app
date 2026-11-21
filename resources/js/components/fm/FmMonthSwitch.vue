<script setup lang="ts">
/**
 * The month chosen, with ‹ and › to go to the one before and after. The month
 * is written in the language of the page. With a span of months (the twelve
 * months of *Consumption*) it writes the first and the last month, and the
 * arrows move by the whole span; `min` and `max` turn the arrows off at the
 * ends, and *Today* goes back to the last month.
 *
 * @group navigation
 */
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { monthName } from '@/lib/budget';
import { addMonths } from '@/lib/days';
import FmIcon from './FmIcon.vue';

const {
    month,
    span = 1,
    min,
    max,
    today = false,
} = defineProps<{
    /** The month, `yyyy-mm`: with a span, the last month of it. */
    month: string;
    /** How many months are shown together, ending with `month`. */
    span?: number;
    /** The earliest `month` (with a span, the earliest last month of the span) the arrows can reach, `yyyy-mm`. */
    min?: string;
    /** The latest `month` (with a span, the latest last month of the span) the arrows can reach, `yyyy-mm`; *Today* goes there. */
    max?: string;
    /** Shows *Today*, which goes back to `max`. */
    today?: boolean;
}>();

const emit = defineEmits<{
    /** The person chose another month: `yyyy-mm`. */
    change: [month: string];
}>();

/**
 * The month some months away.
 *
 * @param from - `yyyy-mm`
 * @param months - how many months, negative to go back
 * @returns `yyyy-mm`
 */
function shifted(from: string, months: number): string {
    return addMonths(`${from}-01`, months).slice(0, 7);
}

/** The month, or the first and last month of the span, written in words. */
const label = computed(() =>
    span === 1
        ? monthName(month)
        : `${monthName(shifted(month, 1 - span))} – ${monthName(month, false)}`,
);

/** The name of the arrow back. */
const previousLabel = computed(() =>
    span === 1
        ? trans('Previous month')
        : span === 12
          ? trans('A year earlier')
          : trans(':count months earlier', { count: String(span) }),
);

/** The name of the arrow forward. */
const nextLabel = computed(() =>
    span === 1
        ? trans('Next month')
        : span === 12
          ? trans('A year later')
          : trans(':count months later', { count: String(span) }),
);

/** Whether the month is already the first one: the arrow back is off. */
const atFirst = computed(() => min !== undefined && month <= min);

/** Whether the month is already the last one: the arrow forward is off. */
const atLast = computed(() => max !== undefined && month >= max);

/**
 * Asks for the month a span away, stopping at the first or the last month
 * instead of jumping past it.
 *
 * @param direction - -1 for the span before, 1 for the one after
 */
function go(direction: -1 | 1): void {
    let target = shifted(month, direction * span);

    if (min !== undefined && target < min) {
        target = min;
    }

    if (max !== undefined && target > max) {
        target = max;
    }

    emit('change', target);
}

/** Goes back to the last month. */
function goToday(): void {
    if (max !== undefined) {
        emit('change', max);
    }
}
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            type="button"
            class="flex size-11 cursor-pointer items-center justify-center rounded-lg border-0 bg-transparent text-ink hover:bg-action-soft disabled:cursor-default disabled:text-ink-faint disabled:hover:bg-transparent"
            :aria-label="previousLabel"
            :disabled="atFirst"
            @click="go(-1)"
        >
            <FmIcon :icon="ChevronLeft" :size="22" />
        </button>
        <span
            class="min-w-[9.5rem] text-center"
            :class="span === 1 ? 'text-subtitle' : 'grow text-amount'"
            >{{ label }}</span
        >
        <button
            type="button"
            class="flex size-11 cursor-pointer items-center justify-center rounded-lg border-0 bg-transparent text-ink hover:bg-action-soft disabled:cursor-default disabled:text-ink-faint disabled:hover:bg-transparent"
            :aria-label="nextLabel"
            :disabled="atLast"
            @click="go(1)"
        >
            <FmIcon :icon="ChevronRight" :size="22" />
        </button>
        <button
            v-if="today && max !== undefined && !atLast"
            type="button"
            class="ml-1 cursor-pointer border-0 bg-transparent p-0 text-row text-action hover:text-action-dark"
            @click="goToday"
        >
            {{ $t('Today') }}
        </button>
    </div>
</template>
