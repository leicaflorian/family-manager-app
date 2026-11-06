<script setup lang="ts">
/**
 * *Planned and spent*: a month of expenses against the plan. At the top what
 * was planned, what was spent so far and (on the computer) what came in; then,
 * by main category, the bar of the spending with a mark where the plan reaches;
 * the chart of the last six months; and, on the computer, the due dates of the
 * month that nobody has registered yet. On the phone the chart comes first and
 * only two numbers show.
 */
import { Head, router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ChartNoAxesColumn } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmBarChart from '@/components/fm/FmBarChart.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmCategoryBar from '@/components/fm/FmCategoryBar.vue';
import FmDeadlineRow from '@/components/fm/FmDeadlineRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmMonthSwitch from '@/components/fm/FmMonthSwitch.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { comparisonComment, deadlineUrls, longMonth } from '@/lib/budget';
import { budgetTabs } from '@/lib/budgetTabs';
import { cn } from '@/lib/utils';
import { comparison as comparisonRoute } from '@/routes/budget';

const props = defineProps<{
    /** The month against its plan. */
    comparison: App.Data.ComparisonData;
}>();

const desktop = useMediaQuery('(min-width: 1024px)');
const { euro } = useFormat();

/** What the spending is next to the plan, for example «185,10 € less». */
const difference = computed(() =>
    comparisonComment(
        props.comparison.spent,
        props.comparison.planned,
        false,
        null,
    ),
);

/** The line for the month in progress. */
const daysLeft = computed((): string | null =>
    props.comparison.days_left === null
        ? null
        : transChoice(
              '{0} The month is in progress: it ends today.|{1} The month is in progress: 1 day left.|[2,*] The month is in progress: :count days left.',
              props.comparison.days_left,
              { count: String(props.comparison.days_left) },
          ),
);

/** What the month's name says under *Planned*, for example «going out in ottobre». */
const outgoing = computed(() =>
    trans('going out in :month', { month: longMonth(props.comparison.month) }),
);

/**
 * Opens the page of another month, keeping the scroll.
 *
 * @param month - `yyyy-mm`
 */
function go(month: string): void {
    router.get(
        comparisonRoute.url({ query: { month } }),
        {},
        { preserveScroll: true, replace: true },
    );
}

/** The classes of a number at the top. */
const tile = cn('flex flex-col gap-0.5 rounded-card bg-white p-3.5 lg:p-4');
</script>

<template>
    <Head :title="$t('Planned and spent')" />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle :title="$t('Budget')" />
        <FmTabs :tabs="budgetTabs('comparison')" :label="$t('Budget')" />

        <FmMonthSwitch :month="comparison.month" @change="go" />
        <p v-if="daysLeft" class="m-0 text-[15px] text-ink-soft">
            {{ daysLeft }}
        </p>

        <div
            :class="cn('grid gap-2.5', desktop ? 'grid-cols-3' : 'grid-cols-2')"
        >
            <div :class="tile">
                <span class="text-[13px] text-ink-soft">{{
                    $t('Planned')
                }}</span>
                <span class="text-lg font-extrabold lg:text-xl">{{
                    euro(comparison.planned)
                }}</span>
                <span class="text-[13px] text-ink-soft">{{ outgoing }}</span>
            </div>
            <div :class="tile">
                <span class="text-[13px] text-ink-soft">{{
                    $t('Spent so far')
                }}</span>
                <span
                    :class="
                        cn(
                            'text-lg font-extrabold lg:text-xl',
                            comparison.spent > comparison.planned &&
                                'text-overdue',
                        )
                    "
                    >{{ euro(comparison.spent) }}</span
                >
                <span class="text-[13px] text-ink-soft">{{ difference }}</span>
            </div>
            <div v-if="desktop" :class="tile">
                <span class="text-[13px] text-ink-soft">{{
                    $t('Income')
                }}</span>
                <span class="text-lg font-extrabold text-paid lg:text-xl">{{
                    euro(comparison.incomes)
                }}</span>
                <span class="text-[13px] text-ink-soft">{{
                    $t('of :amount planned', {
                        amount: euro(comparison.incomes_planned),
                    })
                }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <FmCard :title="$t('By category')" list class="order-2 lg:order-1">
                <FmCategoryBar
                    v-for="category in comparison.categories"
                    :key="category.name"
                    :category="category.name"
                    :spent="category.spent"
                    :planned="category.planned"
                    :estimated="category.estimated"
                    :late-title="category.late_title"
                    :color="category.color"
                />
                <FmEmpty
                    v-if="comparison.categories.length === 0"
                    :icon="ChartNoAxesColumn"
                    :title="$t('Nothing planned or spent this month')"
                />
            </FmCard>

            <FmCard
                :title="$t('Expenses, the last 6 months')"
                class="order-1 lg:order-2"
            >
                <FmBarChart :months="comparison.months" />
            </FmCard>

            <FmCard
                v-if="desktop && comparison.unpaid.length > 0"
                :title="$t('Planned and not yet registered')"
                list
                class="order-3"
            >
                <FmDeadlineRow
                    v-for="deadline in comparison.unpaid"
                    :key="`${deadline.kind}-${deadline.planned_id}-${deadline.original_date}`"
                    :deadline="deadline"
                    :href="deadlineUrls(deadline).show"
                />
            </FmCard>

            <p class="order-4 m-0 text-[13px] text-ink-soft">
                {{
                    $t(
                        'The plan comes from the planned expenses, with their exceptions; what was spent is the expenses dated in the month, even the ones that were not planned.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
