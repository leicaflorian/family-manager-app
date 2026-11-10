<script setup lang="ts">
/**
 * *Planned expenses*: the expenses and incomes that repeat, by next due date,
 * with what they come to in a month on average. The ones that ended stay
 * folded at the bottom. An `admin` adds a planned expense or income.
 */
import { Head } from '@inertiajs/vue3';
import { Plus, Repeat } from '@lucide/vue';
import { computed } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFold from '@/components/fm/FmFold.vue';
import FmPlannedRow from '@/components/fm/FmPlannedRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { budgetTabs } from '@/lib/budgetTabs';
import { create, show } from '@/routes/budget/planned';

const { expenses, incomes, ended, monthly_out, monthly_in, canEdit } =
    defineProps<{
        /** The active planned expenses, by next due date. */
        expenses: App.Data.PlannedRowData[];
        /** The active planned incomes, by next due date. */
        incomes: App.Data.PlannedRowData[];
        /** The ones whose last day is over. */
        ended: App.Data.PlannedRowData[];
        /** What the expenses come to in a month on average. */
        monthly_out: number;
        /** What the incomes come to in a month on average. */
        monthly_in: number;
        /** Whoever is looking adds planned expenses and incomes (an `admin`). */
        canEdit: boolean;
    }>();

const { euro } = useFormat();

/** There is nothing to show: no planned expense or income, active or ended. */
const empty = computed(
    () => expenses.length + incomes.length + ended.length === 0,
);

/** Some amount is an estimate, so the tilde needs explaining. */
const hasEstimate = computed(() =>
    [...expenses, ...incomes].some((row) => row.estimated),
);
</script>

<template>
    <Head :title="$t('Planned expenses')" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle
            :title="$t('Planned expenses')"
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
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="flex flex-col gap-1 rounded-card bg-card p-4">
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
                <div class="flex flex-col gap-1 rounded-card bg-card p-4">
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

            <FmCard
                v-if="expenses.length > 0"
                :title="
                    $t('Going out · :count', { count: String(expenses.length) })
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
                    $t('Coming in · :count', { count: String(incomes.length) })
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
                :label="$t('Ended (:count)', { count: String(ended.length) })"
            >
                <FmPlannedRow
                    v-for="row in ended"
                    :key="`ended-${row.kind}-${row.id}`"
                    :row="row"
                    :href="show({ kind: row.kind, id: row.id }).url"
                />
            </FmFold>
        </template>
    </div>
</template>
