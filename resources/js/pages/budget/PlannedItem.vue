<script setup lang="ts">
/**
 * *A planned expense* (or income): the head with the card, the amount, how it
 * repeats and what it comes to in a year; the next due dates, where an open
 * one opens *Change this due date* for an `admin` (with *I paid it* inside) and
 * *I paid it* for who can only pay, and a paid one leads to its movement; the
 * exceptions, with the pencil and *Add*; the data; and the payments by year,
 * which on the phone come last. A due date has no page of its own: its address
 * leads here with `opening` (and `pay`), whose panel opens at once, and saving
 * that panel goes back to the page it came from (`back`).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarX2,
    CircleDollarSign,
    Pencil,
    Plus,
    Repeat,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import FmAmount from '@/components/fm/FmAmount.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmChangeDeadlineSheet from '@/components/fm/FmChangeDeadlineSheet.vue';
import FmPaySheet from '@/components/fm/FmPaySheet.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmPaymentYears from '@/components/fm/FmPaymentYears.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmValueList from '@/components/fm/FmValueList.vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { useFormat } from '@/composables/useFormat';
import {
    capitalized,
    deadlineStanding,
    deadlineUrls,
    recurrenceText,
    skippedMonthsText,
} from '@/lib/budget';
import type { RecurrenceUnit } from '@/lib/budget';
import { show as showMovement } from '@/routes/budget/movements';
import { index, edit } from '@/routes/budget/planned';
import { show as showCard } from '@/routes/cards';

const {
    detail,
    opening,
    pay: payOnArrival,
    back,
} = defineProps<{
    /** `expense` or `income`. */
    kind: string;
    /** The planned item with its year, due dates, exceptions, data and payments. */
    detail: App.Data.PlannedDetailData;
    /** The accounts of the family, for the panels that take a payment. */
    accounts: App.Data.OptionData[];
    /** The due date whose panel opens on arrival, from its address. */
    opening: App.Data.DeadlineData | null;
    /** The panel to open on arrival is *I paid it*. */
    pay: boolean;
    /** The page the due date was opened from, where its panel goes back after saving. */
    back: string | null;
}>();

const { euro, date } = useFormat();

/** The planned item. */
const row = computed(() => detail.row);

/** Whether it is an income. */
const income = computed(() => row.value.kind === 'income');

const { title, head } = usePageMeta({
    info: 'planned-item',
    title: () => row.value.title,
    parent: ['budget', { label: trans('Recurring'), href: index().url }],
});

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

/** The due date whose payment panel is open, if there is one. */
const paying = ref<App.Data.DeadlineData | null>(null);

/** The payment panel is open. */
const payingOpen = ref(false);

/** The due date opened on arrival and where its panel goes back, kept once the address loses them. */
const arrival = { date: opening?.original_date ?? null, back };

// Going back elsewhere belongs to the panel opened on arrival: once it closes,
// a panel opened from the page stays on the page (from Change this due date
// to I paid it both are briefly true or false in the same tick, so it carries on)
watch(
    () => changingOpen.value || payingOpen.value,
    (open) => {
        if (!open) {
            arrival.back = null;
        }
    },
);

/**
 * Opens the payment panel of a due date.
 *
 * @param deadline - the due date to pay
 */
function startPaying(deadline: App.Data.DeadlineData): void {
    paying.value = deadline;
    payingOpen.value = true;
}

/**
 * Whether tapping an open due date does something: it can be changed or paid.
 *
 * @param deadline - the due date of the row
 * @returns true for an open due date and who can change or pay it
 */
function opens(deadline: App.Data.DeadlineData): boolean {
    return deadline.payment === null && (detail.can_edit || detail.can_pay);
}

/**
 * Opens the panel of an open due date: *Change this due date* for an `admin`,
 * *I paid it* for who can only pay.
 *
 * @param deadline - the due date of the row
 */
function openRow(deadline: App.Data.DeadlineData): void {
    if (detail.can_edit) {
        change(deadline);
    } else {
        startPaying(deadline);
    }
}

/**
 * The movement that paid a due date.
 *
 * @param deadline - the due date
 * @returns its address, none for a due date not paid
 */
function movementUrl(deadline: App.Data.DeadlineData): string | undefined {
    return deadline.payment === null
        ? undefined
        : showMovement.url({
              kind: deadline.payment.kind,
              id: deadline.payment.id,
          });
}

/**
 * Where a panel sends its form: for the due date opened on arrival, with the
 * page to go back to.
 *
 * @param url - the address of the form
 * @param deadline - the due date of the panel
 * @returns the address, with `?back=` when it goes back elsewhere
 */
function withBack(url: string, deadline: App.Data.DeadlineData): string {
    return arrival.back !== null && deadline.original_date === arrival.date
        ? `${url}?back=${encodeURIComponent(arrival.back)}`
        : url;
}

// The address of a due date leads here: its panel opens once, and the request
// leaves the address so that Back does not open it again
onMounted(() => {
    if (opening === null) {
        return;
    }

    const url = new URL(window.location.href);
    for (const name of ['deadline', 'pay', 'from']) {
        url.searchParams.delete(name);
    }
    router.replace({
        url: `${url.pathname}${url.search}${url.hash}`,
        props: (current) => ({
            ...current,
            opening: null,
            pay: false,
            back: null,
        }),
        preserveState: true,
        preserveScroll: true,
    });

    const payable = opening.payment === null && !opening.skipped;

    if (payOnArrival && detail.can_pay && payable) {
        startPaying(opening);
    } else if (detail.can_edit && opening.payment === null) {
        change(opening);
    } else if (detail.can_pay && payable) {
        startPaying(opening);
    } else if (opening.skipped) {
        // Nothing opens for who cannot change it: the link still says why
        toast.info(trans('This due date is skipped: it is not paid.'));
    }
});

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
    <Head :title="head" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle :title="title" :subtitle="summary">
            <template #overline>
                <span class="flex items-center gap-1.5 text-label text-warning">
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
            </template>
            <template v-if="detail.can_edit" #actions>
                <FmButton
                    variant="outline"
                    :icon="Pencil"
                    :href="edit({ kind: row.kind, id: row.id }).url"
                    >{{ $t('Edit') }}</FmButton
                >
            </template>
        </FmTitle>

        <div
            class="flex flex-col gap-5 wide:grid wide:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] wide:items-start wide:gap-6"
        >
            <div class="flex flex-col gap-5">
                <FmCard :title="$t('Next due dates')" list>
                    <template
                        v-for="deadline in detail.next"
                        :key="deadline.original_date"
                    >
                        <FmRow
                            :title="date(deadline.date)"
                            :subtitle="
                                deadlineStanding(deadline).text ?? undefined
                            "
                            :tone="deadlineStanding(deadline).tone"
                            :button="opens(deadline)"
                            :href="movementUrl(deadline)"
                            :arrow="false"
                            @click="openRow(deadline)"
                        >
                            <template #after>
                                <FmAmount
                                    :amount="deadline.amount"
                                    :base="deadline.base_amount"
                                    :estimated="deadline.estimated"
                                    :income="income"
                                    :fee="deadline.fee"
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
                            <span class="text-row">{{
                                exceptionText(exception)
                            }}</span>
                            <span
                                v-if="exception.notes"
                                class="text-meta text-ink-soft"
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
                        class="m-0 px-4 py-3 text-body-sm text-ink-soft"
                    >
                        {{
                            $t(
                                'No exceptions: every due date is as the rules say.',
                            )
                        }}
                    </p>
                </FmCard>
                <p class="m-0 text-meta text-ink-soft">
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
                            @click="startPaying(firstOpen)"
                            >{{ $t('Record payment') }}</FmSectionLink
                        >
                    </template>
                    <FmPaymentYears :years="detail.payments" linked />
                    <p
                        v-if="detail.payments.length === 0"
                        class="m-0 px-4 py-3 text-body-sm text-ink-soft"
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
            :action="withBack(deadlineUrls(changing).exceptions, changing)"
            :can-pay="detail.can_pay"
            @pay="startPaying(changing)"
            @close="changing = null"
        />
        <FmPaySheet
            v-if="detail.can_pay && paying"
            :key="`pay-${paying.original_date}`"
            v-model:open="payingOpen"
            :deadline="paying"
            :accounts="accounts"
            :action="withBack(deadlineUrls(paying).payment, paying)"
            @close="paying = null"
        />
    </div>
</template>
