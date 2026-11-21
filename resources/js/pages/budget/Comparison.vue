<script setup lang="ts">
/**
 * *Planned and spent*, a page of the Budget (reached from the sidebar and from the box in *Movements*): a month
 * of expenses against the plan. At the top what was planned, what was spent so far and (on the wide layout, from
 * 900 px) what came in; then, by main category, the bar of the spending with a mark where the plan reaches; the chart
 * of the last six months; and, on the wide layout, the due dates of the month that nobody has registered yet, with
 * the categories on the left and the rest on the right. On the phone it is one column, the chart comes first and
 * only two numbers show.
 */
import { Head, router } from '@inertiajs/vue3';
import { ChartNoAxesColumn } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { createReusableTemplate } from '@vueuse/core';
import { useWideLayout } from '@/composables/useWideLayout';
import FmBarChart from '@/components/fm/FmBarChart.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmCategoryBar from '@/components/fm/FmCategoryBar.vue';
import FmDeadlineRow from '@/components/fm/FmDeadlineRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmMonthSwitch from '@/components/fm/FmMonthSwitch.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { comparisonComment, deadlineUrls, longMonth } from '@/lib/budget';
import { usePageTrail } from '@/composables/usePageTrail';
import { cn } from '@/lib/utils';
import { budget } from '@/routes';
import { comparison as comparisonRoute } from '@/routes/budget';

const props = defineProps<{
    /** The month against its plan. */
    comparison: App.Data.ComparisonData;
}>();

const wide = useWideLayout();
const [DefineCategories, ReuseCategories] = createReusableTemplate();
const [DefineChart, ReuseChart] = createReusableTemplate();
const [DefineUnpaid, ReuseUnpaid] = createReusableTemplate();
const [DefineNote, ReuseNote] = createReusableTemplate();
const { euro } = useFormat();

usePageTrail(() => [
    { label: trans('Budget'), href: budget().url },
    { label: trans('Planned and spent') },
]);

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
const tile = cn(
    'flex flex-col gap-0.5 rounded-card bg-card p-3.5 shadow-card wide:p-4',
);
</script>

<template>
    <Head :title="$t('Planned and spent')" />

    <div class="flex max-w-[1120px] flex-col gap-4 wide:gap-6">
        <FmTitle :title="$t('Planned and spent')" />

        <FmMonthSwitch :month="comparison.month" @change="go" />
        <p v-if="daysLeft" class="m-0 text-body-sm text-ink-soft">
            {{ daysLeft }}
        </p>

        <div :class="cn('grid gap-2.5', wide ? 'grid-cols-3' : 'grid-cols-2')">
            <div :class="tile">
                <span class="text-meta text-ink-soft">{{ $t('Planned') }}</span>
                <span class="text-subtitle wide:text-stat">{{
                    euro(comparison.planned)
                }}</span>
                <span class="text-meta text-ink-soft">{{ outgoing }}</span>
            </div>
            <div :class="tile">
                <span class="text-meta text-ink-soft">{{
                    $t('Spent so far')
                }}</span>
                <span
                    :class="
                        cn(
                            'text-subtitle wide:text-stat',
                            comparison.spent > comparison.planned &&
                                'text-overdue',
                        )
                    "
                    >{{ euro(comparison.spent) }}</span
                >
                <span class="text-meta text-ink-soft">{{ difference }}</span>
            </div>
            <div v-if="wide" :class="tile">
                <span class="text-meta text-ink-soft">{{ $t('Income') }}</span>
                <span class="text-subtitle text-paid wide:text-stat">{{
                    euro(comparison.incomes)
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    $t('of :amount planned', {
                        amount: euro(comparison.incomes_planned),
                    })
                }}</span>
            </div>
        </div>

        <DefineCategories>
            <FmCard :title="$t('By category')" list>
                <FmCategoryBar
                    v-for="category in comparison.categories"
                    :key="category.name"
                    :category="category.name"
                    :spent="category.spent"
                    :planned="category.planned"
                    :estimated="category.estimated"
                    :late-title="category.late_title"
                    :color="category.color"
                    :wide="wide"
                />
                <FmEmpty
                    v-if="comparison.categories.length === 0"
                    :icon="ChartNoAxesColumn"
                    :title="$t('Nothing planned or spent this month')"
                />
            </FmCard>
        </DefineCategories>
        <DefineChart>
            <FmCard :title="$t('Expenses, the last 6 months')">
                <FmBarChart :months="comparison.months" />
            </FmCard>
        </DefineChart>
        <DefineUnpaid>
            <FmCard
                v-if="comparison.unpaid.length > 0"
                :title="$t('Planned and not yet registered')"
                list
            >
                <FmDeadlineRow
                    v-for="deadline in comparison.unpaid"
                    :key="`${deadline.kind}-${deadline.planned_id}-${deadline.original_date}`"
                    :deadline="deadline"
                    :href="deadlineUrls(deadline).show"
                />
            </FmCard>
        </DefineUnpaid>
        <DefineNote>
            <p class="m-0 text-meta text-ink-soft">
                {{
                    $t(
                        'The plan comes from the planned expenses, with their exceptions; what was spent is the expenses dated in the month, even the ones that were not planned.',
                    )
                }}
            </p>
        </DefineNote>

        <!-- Wide: the categories on the left (3/5), the chart, what is not registered and the note on the right (2/5) -->
        <div
            v-if="wide"
            class="grid grid-cols-[minmax(0,3fr)_minmax(0,2fr)] items-start gap-6"
        >
            <div class="flex min-w-0 flex-col gap-4"><ReuseCategories /></div>
            <div class="flex min-w-0 flex-col gap-4">
                <ReuseChart />
                <ReuseUnpaid />
                <ReuseNote />
            </div>
        </div>
        <div v-else class="flex flex-col gap-4">
            <ReuseChart />
            <ReuseCategories />
            <ReuseNote />
        </div>
    </div>
</template>
