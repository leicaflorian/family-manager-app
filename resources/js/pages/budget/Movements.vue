<script setup lang="ts">
/**
 * *Movements*, the first page of the budget: the search by title and the
 * filters (kind, account, amount, days), the month with what came in, what
 * went out and what is left, and the expenses and incomes of the month from
 * the most recent, with a button to add one. With a text, an amount or a day
 * the page searches every month: the list is grouped by month, with how many
 * were found and their total instead of the month and its figures. On the
 * computer the filters are a column on the left; on the phone they are in the
 * panel from below. A row, and *New expense or income*, always open the page of
 * the movement, which brings back to this list (`?from=`).
 */
import { Head, router } from '@inertiajs/vue3';
import { Plus, Receipt, Search, Wallet } from '@lucide/vue';
import { createReusableTemplate } from '@vueuse/core';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { useListFilters } from '@/composables/useListFilters';
import FmAmount from '@/components/fm/FmAmount.vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFilterAside from '@/components/fm/FmFilterAside.vue';
import FmFilterButton from '@/components/fm/FmFilterButton.vue';
import FmFilterPills from '@/components/fm/FmFilterPills.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmMonthSwitch from '@/components/fm/FmMonthSwitch.vue';
import FmMovementFilters from '@/components/fm/FmMovementFilters.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import type { MovementsQuery } from '@/lib/budget';
import {
    accountChoices,
    monthName,
    movementKinds,
    movementsUrl,
    weekdayShort,
} from '@/lib/budget';
import { useBankFeed } from '@/composables/useBankFeed';
import { budgetTabs } from '@/lib/budgetTabs';
import type { FilterPill } from '@/lib/filters';
import { rangePills } from '@/lib/filters';
import { expenseIcon } from '@/lib/icons';
import { create, show } from '@/routes/budget/movements';

const props = defineProps<{
    /** The figures of the month. */
    summary: App.Data.MonthSummaryData;
    /** The expenses and incomes of the month, the most recent first. */
    movements: App.Data.MovementData[];
    /** The kind shown, `expense` or `income`, or null for both. */
    kind: string | null;
    /** The id of the account shown, or null for all. */
    account: number | null;
    /** The filters in use, the search included. */
    filters: App.Data.MovementFiltersData;
    /** What a search found in every month, or null when the page shows the month. */
    search: App.Data.MovementSearchData | null;
    /** The accounts of the family, for the account filter. */
    accounts: App.Data.OptionData[];
}>();

/** One row of the list, the same in the month and in a search. */
const [DefineRow, ReuseRow] = createReusableTemplate<{
    movement: App.Data.MovementData;
}>();

const { euro } = useFormat();
const bankFeed = useBankFeed();

/** The search box, the visits with other filters and the column of the filters. */
const {
    desktop,
    searchText,
    submitSearch,
    go,
    removePill,
    removeAll,
    filterCount,
    filterErrors,
    layout,
} = useListFilters<App.Data.MovementFiltersData, MovementsQuery>({
    filters: () => props.filters,
    pills: () => pills.value,
    url: (query) => movementsUrl({ month: props.summary.month, ...query }),
});

/** The panel of the filters, on the phone. */
const filtersOpen = ref(false);

/** The account chosen in the menu of the phone, as text; empty is all of them. */
const accountChoice = ref(props.account === null ? '' : String(props.account));

watch(
    () => props.account,
    (account) =>
        (accountChoice.value = account === null ? '' : String(account)),
);

/** The kinds the list can be narrowed to, on the phone. */
const kinds = computed(() => movementKinds(null));

/** The accounts of the family, for the filters. */
const accountOptions = computed(() =>
    props.accounts.map(({ value, label }) => ({ value, label })),
);

/** The accounts of the menu of the phone, «All the accounts» first. */
const accounts = computed(() => accountChoices(accountOptions.value));

watch(accountChoice, (choice) => {
    const account = choice === '' ? null : Number(choice);

    if (account !== props.account) {
        go({ account });
    }
});

/** The filters on, each with its pill and what removing it leaves. */
const pills = computed(() => {
    const filters = props.filters;
    const list: FilterPill<App.Data.MovementFiltersData>[] = [];

    if (filters.kind !== null) {
        list.push({
            key: 'kind',
            label:
                filters.kind === 'income' ? trans('Income') : trans('Expenses'),
            without: { kind: null },
        });
    }

    if (filters.account !== null) {
        list.push({
            key: 'account',
            label:
                accountOptions.value.find(
                    (option) => option.value === String(filters.account),
                )?.label ?? String(filters.account),
            without: { account: null },
        });
    }

    return [...list, ...rangePills(filters)];
});

/** The line of a search: how many were found and their total; empty when nothing was found. */
const found = computed(() =>
    props.search === null || props.search.count === 0
        ? ''
        : transChoice(
              '{1} :count movement found, :total in all|[2,*] :count movements found, :total in all',
              props.search.count,
              { total: euro(props.search.total) },
          ),
);

/** How many movements of a search the list shows (the server sends the most recent only). */
const shownCount = computed(
    () =>
        props.search?.months.reduce(
            (sum, group) => sum + group.movements.length,
            0,
        ) ?? 0,
);

/** The line under a search that found more than the list shows; empty when it shows them all. */
const cutNote = computed(() =>
    props.search !== null && props.search.count > shownCount.value
        ? trans(
              'Only the :shown most recent are shown: narrow the search to see the others.',
              { shown: String(shownCount.value) },
          )
        : '',
);

/** The button that closes the panel of the filters, on the phone. */
const showLabel = computed(() =>
    props.search === null || props.search.count === 0
        ? trans('Show the movements')
        : transChoice(
              '{1} Show :count movement|[2,*] Show :count movements',
              props.search.count,
          ),
);

/** The text under the money that came in. */
const salaries = computed(() =>
    props.summary.salaries_expected === 0
        ? undefined
        : transChoice(
              '{0} no salary out of :expected|{1} :count salary out of :expected|[2,*] :count salaries out of :expected',
              props.summary.salaries_in,
              {
                  count: String(props.summary.salaries_in),
                  expected: String(props.summary.salaries_expected),
              },
          ),
);

/** The text under the money that went out. */
const payments = computed(() =>
    transChoice(
        '{0} no payments|{1} :count payment|[2,*] :count payments',
        props.summary.payments,
        { count: String(props.summary.payments) },
    ),
);

/** The list as it is now (month and filters): where a movement page leads back to. */
const listUrl = computed(() =>
    movementsUrl({ month: props.summary.month, ...props.filters }),
);

/** Adds an expense or an income: its own page, which leads back to this list and starts as the kind shown. */
function add(): void {
    router.get(
        create.url({
            query: {
                from: listUrl.value,
                ...(props.kind ? { kind: props.kind } : {}),
            },
        }),
    );
}

/**
 * Where a row leads: the page of the movement, which leads back to this list.
 *
 * @param movement - the movement of the row
 * @returns the address
 */
function rowHref(movement: App.Data.MovementData): string {
    return show.url(
        { kind: movement.kind, id: movement.id },
        { query: { from: listUrl.value } },
    );
}

/**
 * The second line of a row: who it is with and the account.
 *
 * @param movement - the movement of the row
 * @returns for example «Tper · Conto BPER»
 */
function subtitle(movement: App.Data.MovementData): string {
    return [movement.organization, movement.account]
        .filter((part) => part !== null)
        .join(' · ');
}
</script>

<template>
    <Head :title="$t('Budget')" />

    <DefineRow v-slot="{ movement }">
        <FmRow
            :title="movement.title"
            :subtitle="subtitle(movement)"
            :href="rowHref(movement)"
            :arrow="false"
        >
            <template #before>
                <span class="flex w-9 shrink-0 flex-col items-center">
                    <span class="text-amount">{{
                        Number(movement.date.slice(8, 10))
                    }}</span>
                    <span class="text-caption text-ink-soft">{{
                        weekdayShort(movement.date)
                    }}</span>
                </span>
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-ink-soft"
                    :style="
                        movement.color ? { color: movement.color } : undefined
                    "
                >
                    <FmIcon
                        :icon="
                            movement.kind === 'income'
                                ? Wallet
                                : expenseIcon(movement.icon)
                        "
                        :size="18"
                    />
                </span>
            </template>
            <template #after>
                <FmAmount
                    :amount="movement.amount"
                    :income="movement.kind === 'income'"
                    :fee="movement.fee_amount"
                />
            </template>
        </FmRow>
    </DefineRow>

    <div class="flex flex-col gap-4">
        <FmTitle :title="$t('Budget')">
            <template #actions>
                <FmButton :icon="Plus" @click="add">{{
                    $t('New expense or income')
                }}</FmButton>
            </template>
        </FmTitle>
        <FmTabs
            :tabs="budgetTabs('movements', bankFeed)"
            :label="$t('Budget')"
        />

        <div class="flex w-full max-w-105 gap-2.5">
            <form
                role="search"
                class="min-w-0 grow"
                @submit.prevent="submitSearch"
            >
                <FmSearchField
                    v-model="searchText"
                    :label="$t('Search a movement')"
                />
            </form>
            <FmFilterButton
                v-if="!desktop"
                :count="filterCount"
                :expanded="filtersOpen"
                @click="filtersOpen = true"
            />
        </div>

        <div :class="layout">
            <FmFilterAside v-if="desktop" :detail="false">
                <template #filters>
                    <div class="flex flex-col gap-4">
                        <h2 class="text-amount">{{ $t('Filter') }}</h2>
                        <FmMovementFilters
                            :filters="filters"
                            :accounts="accountOptions"
                            :errors="filterErrors"
                            @change="go"
                        />
                    </div>
                </template>
            </FmFilterAside>

            <div class="flex min-w-0 flex-col gap-4">
                <template v-if="search === null">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <FmMonthSwitch
                            :month="summary.month"
                            @change="(month) => go({ month })"
                        />
                    </div>

                    <div class="grid grid-cols-3 gap-2.5">
                        <div
                            class="flex flex-col gap-0.5 rounded-card bg-card p-3.5 shadow-card computer:p-4"
                        >
                            <span class="text-meta text-ink-soft">{{
                                $t('Money in')
                            }}</span>
                            <span class="text-subtitle computer:text-stat">{{
                                euro(summary.incomes)
                            }}</span>
                            <span
                                v-if="salaries"
                                class="text-meta text-ink-soft"
                                >{{ salaries }}</span
                            >
                        </div>
                        <div
                            class="flex flex-col gap-0.5 rounded-card bg-card p-3.5 shadow-card computer:p-4"
                        >
                            <span class="text-meta text-ink-soft">{{
                                $t('Money out')
                            }}</span>
                            <span class="text-subtitle computer:text-stat">{{
                                euro(summary.expenses)
                            }}</span>
                            <span class="text-meta text-ink-soft">{{
                                payments
                            }}</span>
                        </div>
                        <div
                            class="flex flex-col gap-0.5 rounded-card bg-card p-3.5 shadow-card computer:p-4"
                        >
                            <span class="text-meta text-ink-soft">{{
                                $t('So far')
                            }}</span>
                            <span class="text-subtitle computer:text-stat">
                                <FmAmount
                                    v-if="summary.balance >= 0"
                                    :amount="summary.balance"
                                    income
                                />
                                <template v-else>{{
                                    euro(summary.balance)
                                }}</template>
                            </span>
                            <span class="text-meta text-ink-soft">{{
                                $t('incomes minus expenses')
                            }}</span>
                        </div>
                    </div>
                </template>

                <FmFilterPills
                    v-if="pills.length > 0"
                    :pills="pills"
                    :found="found"
                    @remove="removePill"
                    @remove-all="removeAll"
                />

                <div
                    v-if="!desktop"
                    class="flex flex-wrap items-center gap-2.5"
                >
                    <div class="flex gap-1 rounded-md bg-segment p-1">
                        <button
                            v-for="choice in kinds"
                            :key="choice.value ?? 'all'"
                            type="button"
                            :aria-pressed="kind === choice.value"
                            class="h-tab cursor-pointer rounded-tag border-0 bg-transparent px-3.5 text-body-sm text-ink-soft aria-pressed:bg-card aria-pressed:text-ink aria-pressed:shadow-raised"
                            @click="go({ kind: choice.value })"
                        >
                            {{ choice.label }}
                        </button>
                    </div>
                    <div class="min-w-48">
                        <FormKit
                            v-model="accountChoice"
                            type="dropdown"
                            :label="$t('Bank account')"
                            name="account"
                            :options="accounts"
                            :classes="{ label: 'sr-only', outer: 'mb-0!' }"
                        />
                    </div>
                </div>

                <template v-if="search !== null">
                    <FmCard v-if="search.months.length > 0" list>
                        <template
                            v-for="group in search.months"
                            :key="group.month"
                        >
                            <div
                                class="flex justify-between gap-3 bg-card-sunken px-4 py-2.5 text-meta-strong text-ink-soft"
                            >
                                <span>{{ monthName(group.month) }}</span>
                                <span class="whitespace-nowrap">{{
                                    euro(group.total)
                                }}</span>
                            </div>
                            <ReuseRow
                                v-for="movement in group.movements"
                                :key="`${movement.kind}:${movement.id}`"
                                :movement="movement"
                            />
                        </template>
                    </FmCard>
                    <p
                        v-if="cutNote !== ''"
                        class="mx-1 my-0 text-body-sm text-ink-soft"
                    >
                        {{ cutNote }}
                    </p>
                    <FmEmpty
                        v-if="search.months.length === 0"
                        :icon="Search"
                        :title="$t('No movement matches the filters')"
                        :text="$t('Remove a filter or change the search.')"
                    />
                </template>
                <template v-else>
                    <FmCard v-if="movements.length > 0" list>
                        <ReuseRow
                            v-for="movement in movements"
                            :key="`${movement.kind}:${movement.id}`"
                            :movement="movement"
                        />
                    </FmCard>
                    <FmEmpty
                        v-else
                        :icon="Receipt"
                        :title="$t('No movements this month')"
                        :text="
                            $t(
                                'The expenses and incomes you add will appear here.',
                            )
                        "
                    />
                </template>
            </div>
        </div>

        <FmBottomSheet
            v-if="!desktop"
            v-model:open="filtersOpen"
            :title="$t('Filters')"
        >
            <p class="m-0 text-body-sm text-ink-soft">
                {{
                    $t(
                        'You search the title in the box above the list. You can change the type and the account on the page too.',
                    )
                }}
            </p>
            <FmMovementFilters
                :filters="filters"
                :accounts="accountOptions"
                :errors="filterErrors"
                @change="go"
            />
            <FmButton wide @click="filtersOpen = false">{{
                showLabel
            }}</FmButton>
        </FmBottomSheet>
    </div>
</template>
