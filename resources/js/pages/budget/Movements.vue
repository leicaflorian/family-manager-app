<script setup lang="ts">
/**
 * *Movements*, the first page of the budget: the month with what came in, what
 * went out and what is left, the expenses and incomes of the month from the
 * most recent (choosing the kind and the account), and a button to add one. On
 * the computer a row opens its panel on the right, as *Documents*; on the
 * phone it opens its page.
 */
import { Head, router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { Plus, Receipt, Wallet } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmAmount from '@/components/fm/FmAmount.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmMonthSwitch from '@/components/fm/FmMonthSwitch.vue';
import FmMovementPanel from '@/components/fm/FmMovementPanel.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import type { MovementFormOptions } from '@/lib/budget';
import { movementsUrl, weekdayShort } from '@/lib/budget';
import { isoDate } from '@/lib/format';
import { expenseIcon } from '@/lib/icons';
import { budgetTabs } from '@/lib/budgetTabs';
import { cn } from '@/lib/utils';
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
    /** The movement open on the right on the computer. */
    selected: App.Data.MovementData | null;
    /** The due dates that the open movement can settle. */
    occurrences: App.Data.OptionData[];
    /** The choices of the forms. */
    options: MovementFormOptions;
}>();

const desktop = useMediaQuery('(min-width: 1024px)');
const { euro } = useFormat();

/** The panel of a new expense or income is open (on the computer). */
const creating = ref(false);

/** The account chosen in the menu, as text; empty is all of them. */
const accountChoice = ref(props.account === null ? '' : String(props.account));

watch(
    () => props.account,
    (account) =>
        (accountChoice.value = account === null ? '' : String(account)),
);

/** The kinds the list can be narrowed to. */
const kinds = computed(() => [
    { value: null, label: trans('Everything') },
    { value: 'expense', label: trans('Expenses') },
    { value: 'income', label: trans('Income') },
]);

/** The accounts of the menu, «All the accounts» first. */
const accounts = computed(() => [
    { value: '', label: trans('All the accounts') },
    ...props.options.accounts.map(({ value, label }) => ({ value, label })),
]);

/**
 * Opens the page with other choices, keeping the scroll.
 *
 * @param change - what changes from the page in use
 */
function go(change: {
    month?: string;
    kind?: string | null;
    account?: number | null;
}): void {
    router.get(
        movementsUrl({
            month: props.summary.month,
            kind: props.kind,
            account: props.account,
            ...change,
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(accountChoice, (choice) => {
    const account = choice === '' ? null : Number(choice);

    if (account !== props.account) {
        go({ account });
    }
});

/** Closes the panel: the movement goes away from the address. */
function closePanel(): void {
    creating.value = false;

    if (props.selected) {
        go({});
    }
}

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

/** Whether there is a panel on the right. */
const panelOpen = computed(() => props.selected !== null || creating.value);

/** What a new movement starts from. */
const start = computed(() =>
    props.selected
        ? {
              kind: props.selected.kind,
              planned_id:
                  props.selected.planned_id === null
                      ? null
                      : String(props.selected.planned_id),
              date: props.selected.date,
          }
        : { kind: props.kind ?? 'expense', planned_id: null, date: isoDate() },
);

/** Adds an expense or an income: the panel on the computer, its page on the phone. */
function add(): void {
    if (desktop.value) {
        creating.value = true;
    } else {
        router.get(create.url());
    }
}

/**
 * Where a row leads: on the computer the panel beside the list, on the phone
 * the page of the movement.
 *
 * @param movement - the movement of the row
 * @returns the address
 */
function rowHref(movement: App.Data.MovementData): string {
    return desktop.value
        ? movementsUrl({
              month: props.summary.month,
              kind: props.kind,
              account: props.account,
              movement: `${movement.kind}:${movement.id}`,
          })
        : show.url({ kind: movement.kind, id: movement.id });
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

    <div class="flex flex-col gap-4">
        <FmTitle :title="$t('Budget')">
            <template #actions>
                <FmButton :icon="Plus" @click="add">{{
                    $t('New expense or income')
                }}</FmButton>
            </template>
        </FmTitle>
        <FmTabs :tabs="budgetTabs('movements')" :label="$t('Budget')" />

        <div
            :class="
                cn(
                    'flex flex-col gap-6 lg:grid lg:items-start',
                    panelOpen
                        ? 'lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]'
                        : 'lg:grid-cols-1',
                )
            "
        >
            <div class="flex min-w-0 flex-col gap-4">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <FmMonthSwitch
                        :month="summary.month"
                        @change="(month) => go({ month })"
                    />
                </div>

                <div class="grid grid-cols-3 gap-2.5">
                    <div
                        class="flex flex-col gap-0.5 rounded-2xl bg-white p-3.5 lg:p-4"
                    >
                        <span class="text-[13px] text-ink-soft">{{
                            $t('Money in')
                        }}</span>
                        <span class="text-lg font-extrabold lg:text-xl">{{
                            euro(summary.incomes)
                        }}</span>
                        <span
                            v-if="salaries"
                            class="text-[13px] text-ink-soft"
                            >{{ salaries }}</span
                        >
                    </div>
                    <div
                        class="flex flex-col gap-0.5 rounded-2xl bg-white p-3.5 lg:p-4"
                    >
                        <span class="text-[13px] text-ink-soft">{{
                            $t('Money out')
                        }}</span>
                        <span class="text-lg font-extrabold lg:text-xl">{{
                            euro(summary.expenses)
                        }}</span>
                        <span class="text-[13px] text-ink-soft">{{
                            payments
                        }}</span>
                    </div>
                    <div
                        class="flex flex-col gap-0.5 rounded-2xl bg-white p-3.5 lg:p-4"
                    >
                        <span class="text-[13px] text-ink-soft">{{
                            $t('So far')
                        }}</span>
                        <span class="text-lg font-extrabold lg:text-xl">
                            <FmAmount
                                v-if="summary.balance >= 0"
                                :amount="summary.balance"
                                income
                            />
                            <template v-else>{{
                                euro(summary.balance)
                            }}</template>
                        </span>
                        <span class="text-[13px] text-ink-soft">{{
                            $t('incomes minus expenses')
                        }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="flex gap-1 rounded-[10px] bg-segment p-1">
                        <button
                            v-for="choice in kinds"
                            :key="choice.value ?? 'all'"
                            type="button"
                            :aria-pressed="kind === choice.value"
                            class="h-[38px] cursor-pointer rounded-[7px] border-0 bg-transparent px-3.5 text-[15px] text-ink-soft aria-pressed:bg-white aria-pressed:text-ink aria-pressed:shadow-[0_1px_3px_rgba(24,33,47,0.12)]"
                            @click="go({ kind: choice.value })"
                        >
                            {{ choice.label }}
                        </button>
                    </div>
                    <div class="min-w-48">
                        <FormKit
                            v-model="accountChoice"
                            type="dropdown"
                            :label="$t('Account')"
                            name="account"
                            :options="accounts"
                            :classes="{ label: 'sr-only', outer: 'mb-0!' }"
                        />
                    </div>
                </div>

                <FmCard v-if="movements.length > 0" list>
                    <FmRow
                        v-for="movement in movements"
                        :key="`${movement.kind}:${movement.id}`"
                        :title="movement.title"
                        :subtitle="subtitle(movement)"
                        :href="rowHref(movement)"
                        :current="
                            selected?.kind === movement.kind &&
                            selected?.id === movement.id
                        "
                        :arrow="false"
                    >
                        <template #before>
                            <span
                                class="flex w-9 shrink-0 flex-col items-center leading-tight"
                            >
                                <span class="text-base font-bold">{{
                                    Number(movement.date.slice(8, 10))
                                }}</span>
                                <span class="text-xs text-ink-soft">{{
                                    weekdayShort(movement.date)
                                }}</span>
                            </span>
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-ink-soft"
                                :style="
                                    movement.color
                                        ? { color: movement.color }
                                        : undefined
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
                            />
                        </template>
                    </FmRow>
                </FmCard>
                <FmEmpty
                    v-else
                    :icon="Receipt"
                    :title="$t('No movements this month')"
                    :text="
                        $t('The expenses and incomes you add will appear here.')
                    "
                />
            </div>

            <section v-if="panelOpen" class="hidden min-w-0 lg:block">
                <FmMovementPanel
                    :key="selected ? `${selected.kind}:${selected.id}` : 'new'"
                    :movement="selected"
                    :options="options"
                    :start="start"
                    :occurrences="occurrences"
                    compact
                    @close="closePanel"
                />
            </section>
        </div>
    </div>
</template>
