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
import { computed } from 'vue';
import FilterRanges from '@/components/filters/FilterRanges.vue';
import { accountChoices as withAllAccounts, movementKinds } from '@/lib/budget';
import { anyFilterOn, withoutFilters } from '@/lib/filters';
import { textOrNull } from '@/lib/forms';

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
const kinds = computed(() => movementKinds(ALL));

/** The accounts of the menu, «All the accounts» first. */
const accountChoices = computed(() => withAllAccounts(accounts));

/** Whether any filter is on, the search included. */
const anyOn = computed(() => anyFilterOn(filters));

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
        <FilterRanges
            :filters="filters"
            :date-label="$t('Date')"
            :help="$t('Without dates I look in every month.')"
            :removable="anyOn"
            :errors="errors"
            @change="change"
            @remove="emit('change', withoutFilters(filters))"
        />
    </div>
</template>
