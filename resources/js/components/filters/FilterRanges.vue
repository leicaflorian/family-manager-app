<script setup lang="ts">
/**
 * The amount from / to and the days from / to of a list's filters, with what
 * happens without days and *Remove the filters* when a filter is on. Only a
 * value that really changed is sent (the amounts after a short pause in
 * typing). Shared by `FmMovementFilters` and `FmPlannedFilters`, which add
 * their own fields above it.
 */
import { errors as fieldErrors } from '@/lib/errors';
import { formatAmount, parseAmount, textOrNull } from '@/lib/forms';

/** The ranges this part of the filters holds. */
type Ranges = {
    /** The smallest amount. */
    min: number | null;
    /** The largest amount. */
    max: number | null;
    /** The first day, `yyyy-mm-dd`. */
    from: string | null;
    /** The last day, `yyyy-mm-dd`. */
    to: string | null;
};

const {
    filters,
    dateLabel,
    help,
    removable = false,
    errors = {},
} = defineProps<{
    /** The ranges in use (the other filters of the list are left alone). */
    filters: Ranges;
    /** The name of the days, for example «Date» or «Next due date». */
    dateLabel: string;
    /** What happens without days. */
    help: string;
    /** Some filter of the list is on, so they can be removed. */
    removable?: boolean;
    /** What the server found wrong, by filter (an amount *to* under the *from*). */
    errors?: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    /** A range changed: only that value. */
    change: [ranges: Partial<Ranges>];
    /** *Remove the filters* was pressed. */
    remove: [];
}>();

/**
 * Sends a value, unless it is the one already in use.
 *
 * @param key - the range that changed
 * @param value - its new value
 */
function change<K extends keyof Ranges>(key: K, value: Ranges[K]): void {
    if (value !== filters[key]) {
        emit('change', { [key]: value });
    }
}
</script>

<template>
    <fieldset class="m-0 flex min-w-0 flex-col gap-2.5 border-0 p-0">
        <legend class="mb-2 p-0 text-label text-ink-soft">
            {{ $t('Amount') }}
        </legend>
        <div class="grid gap-3 @xs:grid-cols-2">
            <FormKit
                type="currency"
                :label="$t('At least')"
                name="min"
                :delay="600"
                :model-value="formatAmount(filters.min)"
                :errors="fieldErrors(errors.min)"
                @update:model-value="
                    (value: unknown) => change('min', parseAmount(value))
                "
            />
            <FormKit
                type="currency"
                :label="$t('At most')"
                name="max"
                :delay="600"
                :model-value="formatAmount(filters.max)"
                :errors="fieldErrors(errors.max)"
                @update:model-value="
                    (value: unknown) => change('max', parseAmount(value))
                "
            />
        </div>
    </fieldset>
    <fieldset class="m-0 flex min-w-0 flex-col gap-2.5 border-0 p-0">
        <legend class="mb-2 p-0 text-label text-ink-soft">
            {{ dateLabel }}
        </legend>
        <!-- A datepicker does not empty itself when its value goes back to null: the key starts it again -->
        <div class="grid gap-3 @xs:grid-cols-2">
            <FormKit
                :key="`from:${filters.from}`"
                type="datepicker"
                :label="$t('From')"
                name="from"
                :model-value="filters.from"
                :errors="fieldErrors(errors.from)"
                @update:model-value="
                    (value: unknown) => change('from', textOrNull(value))
                "
            />
            <FormKit
                :key="`to:${filters.to}`"
                type="datepicker"
                :label="$t('To')"
                name="to"
                :model-value="filters.to"
                :errors="fieldErrors(errors.to)"
                @update:model-value="
                    (value: unknown) => change('to', textOrNull(value))
                "
            />
        </div>
        <p class="m-0 text-meta text-ink-soft">{{ help }}</p>
    </fieldset>
    <button
        v-if="removable"
        type="button"
        class="cursor-pointer self-start border-0 bg-transparent p-0 text-row text-action"
        @click="emit('remove')"
    >
        {{ $t('Remove the filters') }}
    </button>
</template>
