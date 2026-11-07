<script setup lang="ts">
/**
 * The payments on the page of a card, as the boards of the cards draw them:
 * in the box at the top the oldest open due date (the renewal of a policy, the
 * next instalment of a loan, the salary coming in), which opens *A due date*
 * (with *It arrived* open on a salary);
 * the figures, beside the dates of the card (`tiles`); how far a loan is paid;
 * the open due dates; then the data of the card (the default slot). The
 * planned expenses and their payments (`FmCardPayments`) the page shows under
 * the documents; without a budget only the tiles and the data remain.
 *
 * @group sections
 */
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { deadlineStatus, deadlineUrls } from '@/lib/budget';
import { relativeDays } from '@/lib/format';
import { cn } from '@/lib/utils';
import FmAmount from './FmAmount.vue';
import FmCard from './FmCard.vue';
import FmDeadlineRow from './FmDeadlineRow.vue';
import FmNumberTile from './FmNumberTile.vue';

const { budget, card } = defineProps<{
    /** The payments of the card, null when none is sent. */
    budget: App.Data.CardBudgetData | null;
    /** The address of the card: its type and its number. */
    card: { type: string; id: number };
}>();

defineSlots<{
    /** The tiles of the dates of the card, in the grid of the figures. */
    tiles?: () => unknown;
    /** The data of the card, after the open due dates. */
    default?: () => unknown;
}>();

const { date, euro } = useFormat();

/** The due date of the box at the top. */
const next = computed(() => budget?.next ?? null);

/** Where the box leads: *A due date*, with *It arrived* open on an income for whoever can record it. */
const nextUrl = computed((): string => {
    if (next.value === null) {
        return '';
    }

    const url = deadlineUrls(next.value).show;

    return next.value.kind === 'income' && budget?.can_pay
        ? `${url}?pay=1`
        : url;
});

/** The title of the box: the number of the instalment of a loan, else the title of the due date. */
const nextTitle = computed((): string => {
    if (next.value === null) {
        return '';
    }

    return budget?.installment_number != null &&
        budget.installments_count !== null
        ? trans('Instalment :number of :count', {
              number: String(budget.installment_number),
              count: String(budget.installments_count),
          })
        : next.value.title;
});

/** The line of the box: the day, how far it is (or how late), and the other card it comes from, else the account. */
const nextLine = computed((): string => {
    const deadline = next.value;

    if (deadline === null) {
        return '';
    }

    const fromOther =
        deadline.card !== null &&
        (deadline.card.type !== card.type || deadline.card.id !== card.id);
    const when = deadline.late
        ? deadlineStatus(deadline)
        : relativeDays(deadline.date);

    return [
        date(deadline.date),
        when,
        fromOther ? deadline.card?.label : deadline.account,
    ]
        .filter((part) => part)
        .join(' · ');
});

/** How far a loan is paid, when it says. */
const progress = computed(() =>
    budget &&
    budget.installments_count !== null &&
    budget.installments_paid !== null
        ? { paid: budget.installments_paid, count: budget.installments_count }
        : null,
);

/** The width of the paid part of the bar. */
const progressWidth = computed((): string =>
    progress.value === null || progress.value.count === 0
        ? '0%'
        : `${Math.min(100, (progress.value.paid / progress.value.count) * 100)}%`,
);

/** The classes of the line of the box: red when late. */
const nextLineClass = computed((): string =>
    cn(
        'text-sm',
        next.value?.late ? 'font-semibold text-overdue' : 'text-ink-soft',
    ),
);
</script>

<template>
    <Link
        v-if="budget && next"
        :href="nextUrl"
        class="flex flex-col gap-1 rounded-card bg-card p-4 text-ink no-underline hover:bg-card/80"
    >
        <span class="text-[13px] font-semibold text-ink-soft">{{
            budget.next_label
        }}</span>
        <span class="flex items-baseline justify-between gap-3">
            <span class="min-w-0 text-lg font-bold">{{ nextTitle }}</span>
            <span class="shrink-0 text-lg font-extrabold">
                <FmAmount
                    :amount="next.amount"
                    :base="next.base_amount"
                    :estimated="next.estimated"
                    :income="next.kind === 'income'"
                />
            </span>
        </span>
        <span :class="nextLineClass">{{ nextLine }}</span>
    </Link>

    <div
        v-if="(budget && budget.numbers.length > 0) || $slots.tiles"
        class="grid grid-cols-2 gap-3"
    >
        <FmNumberTile
            v-for="number in budget?.numbers ?? []"
            :key="number.label"
            :number="number"
        />
        <slot name="tiles" />
    </div>

    <div
        v-if="budget && progress"
        class="flex flex-col gap-2 rounded-card bg-card p-4"
    >
        <span class="flex items-baseline justify-between gap-3">
            <span class="text-[13px] font-semibold text-ink-soft">{{
                $t('Instalments paid')
            }}</span>
            <span class="text-[15px] font-bold">{{
                $t(':paid of :count', {
                    paid: String(progress.paid),
                    count: String(progress.count),
                })
            }}</span>
        </span>
        <div
            role="progressbar"
            :aria-label="$t('Instalments paid')"
            :aria-valuenow="progress.paid"
            aria-valuemin="0"
            :aria-valuemax="progress.count"
            class="h-2 overflow-hidden rounded-full bg-canvas"
        >
            <div
                class="h-full rounded-full bg-paid"
                :style="{ width: progressWidth }"
            />
        </div>
        <span class="flex justify-between gap-3 text-[13px] text-ink-soft">
            <span v-if="budget.paid_amount !== null">{{
                $t('Paid :amount', { amount: euro(budget.paid_amount) })
            }}</span>
            <span v-if="budget.left_amount !== null">{{
                $t('Left :amount', { amount: euro(budget.left_amount) })
            }}</span>
        </span>
    </div>

    <FmCard v-if="budget?.due_label" :title="budget.due_label" list>
        <FmDeadlineRow
            v-for="deadline in budget.due"
            :key="`${deadline.kind}-${deadline.planned_id}-${deadline.original_date}`"
            :deadline="deadline"
            :href="deadlineUrls(deadline).show"
        />
        <p
            v-if="budget.due.length === 0"
            class="m-0 px-4 py-3 text-[15px] text-ink-soft"
        >
            {{ $t('Nothing to pay in the next 30 days.') }}
        </p>
    </FmCard>

    <slot />
</template>
