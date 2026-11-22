<script setup lang="ts">
/**
 * The filters of *Planned expenses*: the amount from / to and the next due
 * date from / to, with *Remove the filters* when one is on. Every change is
 * sent at once (the amounts after a short pause in typing); the page reloads
 * the list. The text searched is not here: it is in the box above the list,
 * and is kept as it is when another filter changes. Used in the column on the
 * left on the computer and in the panel from below on the phone.
 *
 * @group forms
 */
import { computed } from 'vue';
import FilterRanges from '@/components/filters/FilterRanges.vue';
import { anyFilterOn, withoutFilters } from '@/lib/filters';
import { cn } from '@/lib/utils';

const {
    filters,
    errors = {},
    columns = false,
} = defineProps<{
    /** The filters in use. */
    filters: App.Data.PlannedFiltersData;
    /** What the server found wrong, by filter (a last day before the first). */
    errors?: Partial<Record<string, string>>;
    /** Lay the fields out as a grid, for the box under the search; without it they stack, for the panel from below. */
    columns?: boolean;
}>();

const emit = defineEmits<{
    /** The filters changed: the new ones, whole. */
    change: [filters: App.Data.PlannedFiltersData];
}>();

/** The classes of the form: a grid of cells in the box, a stack in the panel. */
const rootClass = computed(() =>
    columns
        ? cn('grid grid-cols-2 gap-x-3.5 gap-y-3 computer:grid-cols-4')
        : cn('@container flex flex-col gap-4'),
);

/** Whether any filter is on, the search included. */
const anyOn = computed(() => anyFilterOn(filters));
</script>

<template>
    <div :class="rootClass">
        <FilterRanges
            :filters="filters"
            :date-label="$t('Next due date')"
            :help="$t('Without dates you see them all.')"
            :removable="anyOn"
            :errors="errors"
            :columns="columns"
            @change="(ranges) => emit('change', { ...filters, ...ranges })"
            @remove="emit('change', withoutFilters(filters))"
        />
    </div>
</template>
