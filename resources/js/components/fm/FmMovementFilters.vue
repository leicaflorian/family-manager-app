<script setup lang="ts">
/**
 * The filters of *Movements*: the kind, the account, the amount from / to and
 * the days from / to, with *Remove the filters* when one is on. Every change
 * is sent at once (the amounts after a short pause in typing); the page
 * reloads the list. The text searched is not here: it is in the box above
 * the list, and is kept as it is when another filter changes. Used in the
 * column on the left on the computer and in the panel from below on the phone.
 *
 * @group forms
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { errors as fieldErrors } from '@/lib/errors';
import { formatAmount, parseAmount } from '@/lib/forms';

const {
    filters,
    accounts,
    errors = {},
} = defineProps<{
    /** The filters in use. */
    filters: App.Data.MovementFiltersData;
    /** The accounts of the family. */
    accounts: { value: string; label: string }[];
    /** What the server found wrong, by filter (an amount *to* under the *from*). */
    errors?: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    /** The filters changed: the new ones, whole. */
    change: [filters: App.Data.MovementFiltersData];
}>();

/** The value of the kind buttons that filters nothing. */
const ALL = 'all';

/** The kinds the list can be narrowed to. */
const kinds = computed(() => [
    { value: ALL, label: trans('Everything') },
    { value: 'expense', label: trans('Expenses') },
    { value: 'income', label: trans('Income') },
]);

/** The accounts of the menu, «All the accounts» first. */
const accountChoices = computed(() => [
    { value: '', label: trans('All the accounts') },
    ...accounts,
]);

/** Whether any filter is on, the search included. */
const anyOn = computed(() =>
    Object.values(filters).some((value) => value !== null),
);

/**
 * Sends the filters with some of them changed, unless nothing changed.
 *
 * @param changed - the filters that changed, with their new values
 */
function change(changed: Partial<App.Data.MovementFiltersData>): void {
    const next = { ...filters, ...changed };

    if (
        (Object.keys(changed) as (keyof App.Data.MovementFiltersData)[]).some(
            (key) => next[key] !== filters[key],
        )
    ) {
        emit('change', next);
    }
}

/**
 * Text from a field, or null when it is empty.
 *
 * @param value - what the field holds
 * @returns the text, or null
 */
function textOrNull(value: unknown): string | null {
    return value === '' || value === null || value === undefined
        ? null
        : String(value);
}

/** Takes every filter away, the search too. */
function removeAll(): void {
    emit('change', {
        search: null,
        kind: null,
        account: null,
        min: null,
        max: null,
        from: null,
        to: null,
    });
}
</script>

<template>
    <div class="@container flex flex-col gap-4">
        <FormKit
            type="togglebuttons"
            enforced
            :label="$t('Type')"
            name="kind"
            :options="kinds"
            :model-value="filters.kind ?? ALL"
            @update:model-value="
                (value: unknown) =>
                    change({ kind: value === ALL ? null : textOrNull(value) })
            "
        />
        <FormKit
            type="dropdown"
            :label="$t('Bank account')"
            name="account"
            :options="accountChoices"
            :model-value="
                filters.account === null ? '' : String(filters.account)
            "
            @update:model-value="
                (value: unknown) => {
                    const account = textOrNull(value);
                    change({
                        account: account === null ? null : Number(account),
                    });
                }
            "
        />
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
                        (value: unknown) => change({ min: parseAmount(value) })
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
                        (value: unknown) => change({ max: parseAmount(value) })
                    "
                />
            </div>
        </fieldset>
        <fieldset class="m-0 flex min-w-0 flex-col gap-2.5 border-0 p-0">
            <legend class="mb-2 p-0 text-label text-ink-soft">
                {{ $t('Date') }}
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
                        (value: unknown) => change({ from: textOrNull(value) })
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
                        (value: unknown) => change({ to: textOrNull(value) })
                    "
                />
            </div>
            <p class="m-0 text-meta text-ink-soft">
                {{ $t('Without dates I look in every month.') }}
            </p>
        </fieldset>
        <button
            v-if="anyOn"
            type="button"
            class="cursor-pointer self-start border-0 bg-transparent p-0 text-row text-action"
            @click="removeAll"
        >
            {{ $t('Remove the filters') }}
        </button>
    </div>
</template>
