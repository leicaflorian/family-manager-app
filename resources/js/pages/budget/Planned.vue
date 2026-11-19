<script setup lang="ts">
/**
 * *Planned expenses*: the expenses and incomes that repeat, by next due date,
 * with what they come to in a month on average. The ones that ended stay
 * folded at the bottom. An `admin` adds a planned expense or income. The
 * search box looks for a part of the title, and the filters narrow the rows
 * by amount and by next due date: with a filter on, the averages give way to
 * the pills of the filters and a section left without rows is hidden. On the
 * computer the filters are a column on the left; on the phone they are in the
 * panel from below. A row always opens the page of its planned item.
 */
import { Head } from '@inertiajs/vue3';
import { Plus, Repeat, Search } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFilterAside from '@/components/fm/FmFilterAside.vue';
import FmFilterButton from '@/components/fm/FmFilterButton.vue';
import FmFilterPills from '@/components/fm/FmFilterPills.vue';
import FmFold from '@/components/fm/FmFold.vue';
import FmPlannedFilters from '@/components/fm/FmPlannedFilters.vue';
import FmPlannedRow from '@/components/fm/FmPlannedRow.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { useListFilters } from '@/composables/useListFilters';
import { plannedUrl } from '@/lib/budget';
import { budgetTabs } from '@/lib/budgetTabs';
import { anyFilterOn, rangePills } from '@/lib/filters';
import { create, show } from '@/routes/budget/planned';

const props = defineProps<{
    /** The active planned expenses that pass the filters, by next due date. */
    expenses: App.Data.PlannedRowData[];
    /** The active planned incomes that pass the filters, by next due date. */
    incomes: App.Data.PlannedRowData[];
    /** The ones whose last day is over, among those that pass the filters. */
    ended: App.Data.PlannedRowData[];
    /** What all the expenses come to in a month on average. */
    monthly_out: number;
    /** What all the incomes come to in a month on average. */
    monthly_in: number;
    /** Whoever is looking adds planned expenses and incomes (an `admin`). */
    canEdit: boolean;
    /** The search and the filters in use. */
    filters: App.Data.PlannedFiltersData;
}>();

const { euro } = useFormat();

/** The panel of the filters, on the phone. */
const filtersOpen = ref(false);

/** Some filter is on, the search included. */
const filtering = computed(() => anyFilterOn(props.filters));

/** How many rows pass the filters, ended ones apart. */
const shown = computed(() => props.expenses.length + props.incomes.length);

/** There is nothing to show and nothing is filtered: no planned expense or income at all. */
const empty = computed(
    () => !filtering.value && shown.value + props.ended.length === 0,
);

/** Nothing passes the filters. */
const noMatch = computed(
    () => filtering.value && shown.value + props.ended.length === 0,
);

/** Some amount is an estimate, so the tilde needs explaining. */
const hasEstimate = computed(() =>
    [...props.expenses, ...props.incomes].some((row) => row.estimated),
);

/** The filters on, each with its pill: the days are those of the next due date. */
const pills = computed(() => rangePills(props.filters, 'next'));

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
} = useListFilters({
    filters: () => props.filters,
    pills: () => pills.value,
    url: plannedUrl,
});

/** The line beside the pills: how many rows passed. */
const found = computed(() =>
    shown.value === 0
        ? ''
        : transChoice(
              '{1} :count planned expense, by due date|[2,*] :count planned expenses, by due date',
              shown.value,
          ),
);

/** The button that closes the panel of the filters, on the phone. */
const showLabel = computed(() =>
    shown.value === 0
        ? trans('Show the planned expenses')
        : transChoice(
              '{1} Show :count planned expense|[2,*] Show :count planned expenses',
              shown.value,
          ),
);
</script>

<template>
    <Head :title="$t('Planned expenses and incomes')" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle
            :title="$t('Planned expenses and incomes')"
            :subtitle="
                $t(
                    'The expenses and incomes that repeat, by due date. Home and the Agenda take their due dates from here.',
                )
            "
        >
            <template v-if="canEdit" #actions>
                <FmActions
                    :actions="[
                        {
                            label: $t('New planned expense'),
                            icon: Plus,
                            href: create({ kind: 'expense' }).url,
                        },
                        {
                            label: $t('New planned income'),
                            icon: Plus,
                            variant: 'soft',
                            href: create({ kind: 'income' }).url,
                        },
                    ]"
                />
            </template>
        </FmTitle>
        <FmTabs :tabs="budgetTabs('planned')" :label="$t('Budget')" />

        <FmEmpty
            v-if="empty"
            :icon="Repeat"
            :title="$t('No planned expenses yet')"
            :text="
                $t(
                    'Bills, rent, subscriptions and salaries that repeat: add them once and their due dates appear in Home and the Agenda.',
                )
            "
        >
            <FmButton
                v-if="canEdit"
                :icon="Plus"
                :href="create({ kind: 'expense' }).url"
                >{{ $t('New planned expense') }}</FmButton
            >
        </FmEmpty>

        <template v-else>
            <div class="flex w-full max-w-105 gap-2.5">
                <form
                    role="search"
                    class="min-w-0 grow"
                    @submit.prevent="submitSearch"
                >
                    <FmSearchField
                        v-model="searchText"
                        :label="$t('Search a planned expense')"
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
                            <FmPlannedFilters
                                :filters="filters"
                                :errors="filterErrors"
                                @change="go"
                            />
                        </div>
                    </template>
                </FmFilterAside>

                <div class="flex min-w-0 flex-col gap-5">
                    <FmFilterPills
                        v-if="filtering"
                        :pills="pills"
                        :found="found"
                        @remove="removePill"
                        @remove-all="removeAll"
                    />

                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <div
                            class="flex flex-col gap-1 rounded-card bg-card p-4 shadow-card"
                        >
                            <span class="text-body-sm text-ink-soft">{{
                                $t('Going out, on average')
                            }}</span>
                            <span class="text-title-mobile">{{
                                euro(monthly_out)
                            }}</span>
                            <span class="text-body-sm text-ink-soft">{{
                                $tChoice(
                                    '{1} per month, :count planned expense|[2,*] per month, :count planned expenses',
                                    expenses.length,
                                    { count: String(expenses.length) },
                                )
                            }}</span>
                        </div>
                        <div
                            class="flex flex-col gap-1 rounded-card bg-card p-4 shadow-card"
                        >
                            <span class="text-body-sm text-ink-soft">{{
                                $t('Coming in')
                            }}</span>
                            <span class="text-title-mobile text-paid">{{
                                euro(monthly_in)
                            }}</span>
                            <span class="text-body-sm text-ink-soft">{{
                                $tChoice(
                                    '{1} per month, :count planned income|[2,*] per month, :count planned incomes',
                                    incomes.length,
                                    { count: String(incomes.length) },
                                )
                            }}</span>
                        </div>
                    </div>

                    <FmEmpty
                        v-if="noMatch"
                        :icon="Search"
                        :title="$t('No planned expense matches the filters')"
                        :text="$t('Remove a filter or change the search.')"
                    />

                    <FmCard
                        v-if="expenses.length > 0"
                        :title="
                            $t('Going out · :count', {
                                count: String(expenses.length),
                            })
                        "
                        list
                    >
                        <FmPlannedRow
                            v-for="row in expenses"
                            :key="`expense-${row.id}`"
                            :row="row"
                            :href="show({ kind: row.kind, id: row.id }).url"
                        />
                    </FmCard>

                    <FmCard
                        v-if="incomes.length > 0"
                        :title="
                            $t('Coming in · :count', {
                                count: String(incomes.length),
                            })
                        "
                        list
                    >
                        <FmPlannedRow
                            v-for="row in incomes"
                            :key="`income-${row.id}`"
                            :row="row"
                            :href="show({ kind: row.kind, id: row.id }).url"
                        />
                    </FmCard>

                    <p v-if="hasEstimate" class="m-0 text-meta text-ink-soft">
                        {{
                            $t(
                                '~ means estimated: the amount is an average, because it changes with every bill.',
                            )
                        }}
                    </p>

                    <FmFold
                        v-if="ended.length > 0"
                        :label="
                            $t('Ended (:count)', {
                                count: String(ended.length),
                            })
                        "
                    >
                        <FmPlannedRow
                            v-for="row in ended"
                            :key="`ended-${row.kind}-${row.id}`"
                            :row="row"
                            :href="show({ kind: row.kind, id: row.id }).url"
                        />
                    </FmFold>
                </div>
            </div>
        </template>

        <FmBottomSheet
            v-if="!desktop"
            v-model:open="filtersOpen"
            :title="$t('Filters')"
        >
            <p class="m-0 text-body-sm text-ink-soft">
                {{ $t('You search the title in the box above the list.') }}
            </p>
            <FmPlannedFilters
                :filters="filters"
                :errors="filterErrors"
                @change="go"
            />
            <FmButton wide @click="filtersOpen = false">{{
                showLabel
            }}</FmButton>
        </FmBottomSheet>
    </div>
</template>
