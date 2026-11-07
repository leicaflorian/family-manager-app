<script setup lang="ts">
/**
 * *A planned expense* (or income): the head with the card, the amount, how it
 * repeats and what it comes to in a year; the next due dates, which open
 * *Change this due date* for an `admin` and the due date's page for everyone
 * else; the exceptions, with the pencil and *Add*; the data; and the payments by
 * year, which on the phone come last.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarX2,
    CircleDollarSign,
    Pencil,
    Plus,
    Repeat,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmAmount from '@/components/fm/FmAmount.vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmChangeDeadlineSheet from '@/components/fm/FmChangeDeadlineSheet.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmPaymentYears from '@/components/fm/FmPaymentYears.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import type { RowTone } from '@/components/fm/FmRow.vue';
import FmValueList from '@/components/fm/FmValueList.vue';
import { useFormat } from '@/composables/useFormat';
import {
    capitalized,
    deadlineStatus,
    deadlineUrls,
    recurrenceText,
    skippedMonthsText,
} from '@/lib/budget';
import type { RecurrenceUnit } from '@/lib/budget';
import { index, edit } from '@/routes/budget/planned';
import { show as showCard } from '@/routes/cards';

const { detail } = defineProps<{
    /** `expense` or `income`. */
    kind: string;
    /** The planned item with its year, due dates, exceptions, data and payments. */
    detail: App.Data.PlannedDetailData;
    /** The accounts of the family, for the panels that take a payment. */
    accounts: App.Data.OptionData[];
}>();

const { euro, date } = useFormat();

/** The planned item. */
const row = computed(() => detail.row);

/** Whether it is an income. */
const income = computed(() => row.value.kind === 'income');

/** How it repeats, as `recurrenceText` writes it. */
function repeats(withEnd: boolean): string {
    return recurrenceText(
        row.value.unit as RecurrenceUnit,
        row.value.interval,
        row.value.start_date,
        row.value.recurring,
        withEnd ? row.value.end_date : null,
    );
}

/** The amount, with the tilde of an estimate. */
const amountText = computed(
    () => `${row.value.estimated ? '~' : ''}${euro(row.value.amount)}`,
);

/** The line under the title: the amount and how it repeats, then the year. */
const summary = computed(() => {
    const skipped = skippedMonthsText(detail.skipped_months);
    const year = trans(':amount in a year', {
        amount: euro(detail.year_total),
    });

    return [
        `${amountText.value} ${repeats(true)}`,
        skipped === '' ? year : `${year}, ${skipped}`,
    ].join(' · ');
});

/** The lines of the box *Data* that the head does not say, then the ones of the server. */
const values = computed((): App.Data.CardValueData[] => [
    { label: trans('Amount'), text: amountText.value, href: null },
    {
        label: trans('How often'),
        text: capitalized(repeats(false)),
        href: null,
    },
    { label: trans('From'), text: date(row.value.start_date), href: null },
    {
        label: trans('Until'),
        text: row.value.end_date === null ? '—' : date(row.value.end_date),
        href: null,
    },
    ...detail.values,
]);

/** The first due date among the next ones that nothing settles. */
const firstOpen = computed(() =>
    detail.next.find((deadline) => deadline.payment === null),
);

/** The due date whose panel *Change this due date* is open, if there is one. */
const changing = ref<App.Data.DeadlineData | null>(null);

/** The panel is open. */
const changingOpen = ref(false);

/**
 * Opens the panel *Change this due date* for a due date.
 *
 * @param deadline - the due date to change
 */
function change(deadline: App.Data.DeadlineData): void {
    changing.value = deadline;
    changingOpen.value = true;
}

/**
 * Opens the panel for the due date an exception changes, as the server sends it.
 *
 * @param exception - the exception, with its due date
 */
function changeException(exception: App.Data.ExceptionData): void {
    if (exception.deadline !== null) {
        change(exception.deadline);
    }
}

/**
 * Says where a due date stands in the list: paid, late, or changed by an exception.
 *
 * @param deadline - the due date
 * @returns the text, or null if nothing is worth saying, and the colour it takes
 */
function standing(deadline: App.Data.DeadlineData): {
    text: string | null;
    tone: RowTone;
} {
    if (deadline.payment !== null || deadline.late) {
        return {
            text: deadlineStatus(deadline),
            tone: deadline.late ? 'overdue' : 'paid',
        };
    }

    const notes = deadline.notes;
    const text =
        deadline.base_amount !== null
            ? notes
                ? trans('Different amount: :notes', { notes })
                : trans('Different amount')
            : deadline.date !== deadline.original_date
              ? notes
                  ? trans('Different date: :notes', { notes })
                  : trans('Different date')
              : null;

    return { text, tone: text === null ? 'normal' : 'warning' };
}

/**
 * Says what an exception changes.
 *
 * @param exception - the exception
 * @returns for example «05/12/2026 · Different amount, 60,00 €»
 */
function exceptionText(exception: App.Data.ExceptionData): string {
    const what =
        exception.type === 'skip'
            ? trans('Skipped')
            : exception.type === 'amount_override'
              ? trans('Different amount, :amount', {
                    amount: euro(exception.amount_override),
                })
              : trans('Moved to :date', {
                    date: date(exception.date_override),
                });

    return `${date(exception.occurrence_date)} · ${what}`;
}

/** The icon of each kind of exception. */
const exceptionIcons = {
    skip: CalendarX2,
    amount_override: CircleDollarSign,
    date_override: CalendarClock,
};
</script>

<template>
    <Head :title="row.title" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmBackLink :href="index().url" :label="$t('Planned expenses')" />

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1.5">
                <span
                    class="flex items-center gap-1.5 text-sm font-semibold text-warning"
                >
                    <FmIcon :icon="Repeat" :size="16" />
                    <span>{{
                        income ? $t('Planned income') : $t('Planned expense')
                    }}</span>
                    <template v-if="row.card">
                        <span aria-hidden="true">·</span>
                        <Link
                            :href="
                                showCard.url({
                                    type: row.card.type,
                                    id: row.card.id,
                                })
                            "
                            class="no-underline"
                            >{{ row.card.label }}</Link
                        >
                    </template>
                </span>
                <h1 class="m-0 text-[28px] leading-tight font-extrabold">
                    {{ row.title }}
                </h1>
                <span class="text-[15px] text-ink-soft">{{ summary }}</span>
            </div>
            <FmButton
                v-if="detail.can_edit"
                variant="outline"
                :icon="Pencil"
                :href="edit({ kind: row.kind, id: row.id }).url"
                >{{ $t('Edit') }}</FmButton
            >
        </div>

        <div
            class="flex flex-col gap-5 lg:grid lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start lg:gap-6"
        >
            <div class="flex flex-col gap-5">
                <FmCard :title="$t('Next due dates')" list>
                    <template
                        v-for="deadline in detail.next"
                        :key="deadline.original_date"
                    >
                        <FmRow
                            :title="date(deadline.date)"
                            :subtitle="standing(deadline).text ?? undefined"
                            :tone="standing(deadline).tone"
                            :button="
                                detail.can_edit && deadline.payment === null
                            "
                            :href="
                                detail.can_edit && deadline.payment === null
                                    ? undefined
                                    : deadlineUrls(deadline).show
                            "
                            :arrow="false"
                            @click="change(deadline)"
                        >
                            <template #after>
                                <FmAmount
                                    :amount="deadline.amount"
                                    :base="deadline.base_amount"
                                    :estimated="deadline.estimated"
                                    :income="income"
                                />
                            </template>
                        </FmRow>
                    </template>
                </FmCard>

                <FmCard :title="$t('Exceptions')" list>
                    <template v-if="detail.can_edit && firstOpen" #actions>
                        <FmSectionLink
                            :icon="Plus"
                            @click="change(firstOpen)"
                            >{{ $t('Add') }}</FmSectionLink
                        >
                    </template>
                    <div
                        v-for="exception in detail.exceptions"
                        :key="exception.id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <FmIcon
                            :icon="
                                exceptionIcons[
                                    exception.type as keyof typeof exceptionIcons
                                ]
                            "
                            :size="20"
                            class="text-ink-soft"
                        />
                        <span class="flex min-w-0 grow flex-col gap-0.5">
                            <span class="text-[15px] font-semibold">{{
                                exceptionText(exception)
                            }}</span>
                            <span
                                v-if="exception.notes"
                                class="text-[13px] text-ink-soft"
                                >{{ exception.notes }}</span
                            >
                        </span>
                        <button
                            v-if="detail.can_edit && exception.deadline"
                            type="button"
                            :aria-label="$t('Edit')"
                            class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-tag border-0 bg-transparent text-ink-soft hover:bg-action-soft"
                            @click="changeException(exception)"
                        >
                            <FmIcon :icon="Pencil" :size="18" />
                        </button>
                    </div>
                    <p
                        v-if="detail.exceptions.length === 0"
                        class="m-0 px-4 py-3 text-[15px] text-ink-soft"
                    >
                        {{
                            $t(
                                'No exceptions: every due date is as the rules say.',
                            )
                        }}
                    </p>
                </FmCard>
                <p class="m-0 text-[13px] text-ink-soft">
                    {{
                        $t(
                            'A due date comes from how it repeats (:rule). An exception changes only that due date; the others stay as the planned item says.',
                            { rule: repeats(true) },
                        )
                    }}
                </p>
            </div>

            <div class="flex flex-col gap-5">
                <FmCard :title="$t('Data')">
                    <FmValueList :values="values" />
                </FmCard>

                <FmCard :title="$t('Payments')" list>
                    <template v-if="detail.can_pay && firstOpen" #actions>
                        <FmSectionLink
                            :icon="Plus"
                            :href="`${deadlineUrls(firstOpen).show}?pay=1`"
                            >{{ $t('Record payment') }}</FmSectionLink
                        >
                    </template>
                    <FmPaymentYears :years="detail.payments" />
                    <p
                        v-if="detail.payments.length === 0"
                        class="m-0 px-4 py-3 text-[15px] text-ink-soft"
                    >
                        {{ $t('No payments yet.') }}
                    </p>
                </FmCard>
            </div>
        </div>

        <FmChangeDeadlineSheet
            v-if="detail.can_edit && changing"
            :key="changing.original_date"
            v-model:open="changingOpen"
            :deadline="changing"
            :action="deadlineUrls(changing).exceptions"
            @close="changing = null"
        />
    </div>
</template>
