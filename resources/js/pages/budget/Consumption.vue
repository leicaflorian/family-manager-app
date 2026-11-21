<script setup lang="ts">
/**
 * *Usage*, a page of the Budget: the consumption of one electricity, gas or water utility, 12 months at a time.
 * At the top the types the family has (each opens its first utility) and the name of the utility shown, a menu
 * when the type has more than one; the arrows go a year back or forward and *Today* back to the latest months.
 * The chart shows the consumption of each month against the same month of the year before, or the euros with
 * *Spending*; a bar opens the month: what it used, what it cost, the price, the year before and its bills. Under
 * it the four figures, the sentence of the page, the bills one by one (a table on a wide screen) and the bills
 * read whose consumption could not be saved. On a wide screen the figures come first and the month is a column
 * on the right that stays in sight. Without consumption the page offers to read the bills already kept (when
 * there are some) or to upload one. ✎, *Add a consumption* and *Write it* open the panel of a consumption written by
 * hand (`ConsumptionSheet`); *Read the bills* is off: its reading arrives with the next step.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    ArrowDown,
    ArrowUp,
    Check,
    CircleAlert,
    Droplet,
    FileText,
    Flame,
    Pencil,
    Plus,
    ScanText,
    Sparkles,
    TriangleAlert,
    Upload,
    X,
    Zap,
} from '@lucide/vue';
import { createReusableTemplate } from '@vueuse/core';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import ConsumptionSheet from '@/components/consumption/ConsumptionSheet.vue';
import FmBarChart from '@/components/fm/FmBarChart.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import FmMonthSwitch from '@/components/fm/FmMonthSwitch.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { usePageTrail } from '@/composables/usePageTrail';
import { useWideLayout } from '@/composables/useWideLayout';
import { capitalized, longMonth, monthName } from '@/lib/budget';
import type { ConsumptionView } from '@/lib/consumption';
import {
    billDates,
    billTitle,
    chartMonths,
    chartNote,
    formatDollars,
} from '@/lib/consumption';
import { formatNumber, formatQuantity, unitLabel } from '@/lib/format';
import { rowLink } from '@/lib/rows';
import { cn } from '@/lib/utils';
import { budget, upload } from '@/routes';
import { consumption as consumptionRoute } from '@/routes/budget';
import { show as documentPage } from '@/routes/documents';

const props = defineProps<{
    /** The utility shown, its 12 months, figures and bills, as `ConsumptionController` sends them. */
    consumption: App.Data.Consumption.ConsumptionPageData;
}>();

const wide = useWideLayout();
const { euro, date } = useFormat();
const [DefineFigures, ReuseFigures] = createReusableTemplate();
const [DefineDetail, ReuseDetail] = createReusableTemplate();
const [DefineToCheck, ReuseToCheck] = createReusableTemplate();
const [DefineBadges, ReuseBadges] = createReusableTemplate<{
    /** The bill whose labels are drawn. */
    bill: App.Data.Consumption.ConsumptionBillData;
    /** Whether a paid bill says so, as in the month open; the list says only what is still to pay. */
    paid?: boolean;
}>();

usePageTrail(() => [
    { label: trans('Budget'), href: budget().url },
    { label: trans('Usage') },
]);

/** The panel of a consumption is open. */
const sheetOpen = ref(false);

/** What the panel shows: the bill to correct, or the bill to check whose consumption is written; neither to add one. */
const sheetFor = ref<{
    bill: App.Data.Consumption.ConsumptionBillData | null;
    document: { id: number; title: string } | null;
}>({ bill: null, document: null });

/**
 * Opens the panel of a consumption.
 *
 * @param bill - the bill to correct; null to add one
 * @param document - the bill to check whose consumption is written, for a new one
 */
function openSheet(
    bill: App.Data.Consumption.ConsumptionBillData | null,
    document: { id: number; title: string } | null = null,
): void {
    sheetFor.value = { bill, document };
    sheetOpen.value = true;
}

/** What the bars of the chart are: the consumption (the default) or the euros. */
const view = ref<ConsumptionView>('quantity');

/** The unit of the utility as people write it, `kWh`. */
const unit = computed((): string => unitLabel(props.consumption.utility.unit));

/** Whether the utility has bills in the 12 months: the chart and the figures; otherwise the boxes to start. */
const hasBills = computed((): boolean => props.consumption.bills.length > 0);

/** The icon of the utility shown, for the page without bills. */
const typeIcon = computed((): LucideIcon =>
    iconOf(props.consumption.utility.type),
);

/**
 * The icon of a kind of utility: a flame for gas, a drop for water, a bolt for electricity.
 *
 * @param type - the kind of utility
 * @returns its icon
 */
function iconOf(type: string): LucideIcon {
    return (
        ({ gas: Flame, water: Droplet } as Record<string, LucideIcon>)[type] ??
        Zap
    );
}

/**
 * The address of the page with other choices; the ones left out are not in it.
 *
 * @param query - the utility (the one shown when missing), the last month of the 12 and the month open
 * @returns the address
 */
function pageUrl(query: {
    utility?: number;
    to?: string | null;
    month?: string | null;
}): string {
    return consumptionRoute.url({
        query: {
            utility: query.utility ?? props.consumption.utility.id,
            to: query.to ?? null,
            month: query.month ?? null,
        },
    });
}

/** The tabs of the types: each opens its first utility. */
const typeTabs = computed(() =>
    props.consumption.types.map((type) => ({
        label: type.label,
        href: pageUrl({ utility: type.utility }),
        lead: iconOf(type.type),
        current: type.type === props.consumption.utility.type,
    })),
);

/**
 * When a utility of the menu started or ended.
 *
 * @param utility - a utility of the type shown
 * @returns «Until 31/01/2026», «From 01/02/2026», or an empty text
 */
function utilityDates(
    utility: App.Data.Consumption.ConsumptionPageData['utilities'][number],
): string {
    if (utility.end !== null) {
        return trans('Until :date', { date: date(utility.end) });
    }

    return utility.start === null
        ? ''
        : trans('From :date', { date: date(utility.start) });
}

/**
 * Opens other 12 months: the month open in the detail is not among them, so it closes.
 *
 * @param to - the last month of the 12, `yyyy-mm`; null for the latest ones
 */
function goTo(to: string | null): void {
    router.get(pageUrl({ to }), {}, { preserveScroll: true });
}

/**
 * Opens a month in the detail, or closes it, keeping what the page shows and where it is.
 *
 * @param month - `yyyy-mm`, or null to close it
 */
function openMonth(month: string | null): void {
    router.get(
        pageUrl({ to: props.consumption.to, month }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

/** The bars of the chart, in consumption or in euros. */
const bars = computed(() => chartMonths(props.consumption.months, view.value));

/** The line under the chart on the months covered in part or not at all. */
const note = computed((): string => chartNote(props.consumption.months));

/** The choices of the switch above the chart. */
const viewChoices = computed(() => [
    { label: trans('Consumption'), value: 'quantity' },
    { label: trans('Spending'), value: 'amount' },
]);

/**
 * Writes a consumption of the utility.
 *
 * @param value - the quantity
 * @returns for example `290 kWh`
 */
function quantity(value: number): string {
    return formatQuantity(value, props.consumption.utility.unit);
}

/** The month open in the detail, with its bills, or null. */
const detail = computed(() => {
    const month = props.consumption.months.find(
        (item) => item.month === props.consumption.month,
    );

    if (month === undefined) {
        return null;
    }

    return {
        month,
        bills: props.consumption.bills.filter((bill) =>
            month.billIds.includes(bill.id),
        ),
    };
});

/** The open month against the same month of the year before: more, less or the same, with the words. */
const comparison = computed(() => {
    const month = detail.value?.month;

    if (
        month === undefined ||
        month.quantity === null ||
        month.previousQuantity === null
    ) {
        return null;
    }

    const difference = month.quantity - month.previousQuantity;
    const before = monthName(
        `${Number(month.month.slice(0, 4)) - 1}${month.month.slice(4)}`,
        false,
    );
    const previous =
        month.previousAmount === null || month.previousQuantity <= 0
            ? trans('than :month (:quantity)', {
                  month: before,
                  quantity: quantity(month.previousQuantity),
              })
            : trans('than :month (:quantity, :price per :unit)', {
                  month: before,
                  quantity: quantity(month.previousQuantity),
                  price: euro(month.previousAmount / month.previousQuantity),
                  unit: unit.value,
              });

    if (difference === 0) {
        return {
            icon: null,
            tone: '',
            strong: '',
            rest: trans('The same as :month (:quantity)', {
                month: before,
                quantity: quantity(month.previousQuantity),
            }),
        };
    }

    return {
        icon: difference < 0 ? ArrowDown : ArrowUp,
        tone: difference < 0 ? 'text-paid' : 'text-warning',
        strong: trans(difference > 0 ? ':quantity more' : ':quantity less', {
            quantity: quantity(Math.abs(difference)),
        }),
        rest: previous,
    };
});

/** The second line of *Last 12 months*: against the year before, or how many months have data. */
const yearLine = computed((): string => {
    const {
        quantity: total,
        previousQuantity,
        coveredMonths,
    } = props.consumption.figures;

    if (previousQuantity === null || previousQuantity <= 0) {
        return transChoice(
            '{1} :count month with data|[2,*] :count months with data',
            coveredMonths,
            { count: String(coveredMonths) },
        );
    }

    const percent = Math.round(
        (Math.abs(total - previousQuantity) / previousQuantity) * 100,
    );

    if (percent === 0) {
        return trans('The same as the year before');
    }

    return trans(
        total > previousQuantity
            ? ':percent% more than the year before'
            : ':percent% less than the year before',
        { percent: String(percent) },
    );
});

/** The second line of *The bills*: how many, and how many are still to pay. */
const billsLine = computed((): string => {
    const { bills, toPay } = props.consumption.figures;
    const count = String(bills);

    if (toPay === 0) {
        return transChoice('{1} :count, paid|[2,*] :count, all paid', bills, {
            count,
        });
    }

    return toPay === 1
        ? trans(':count, one still to pay', { count })
        : trans(':count, :to_pay still to pay', {
              count,
              to_pay: String(toPay),
          });
});

/** The sentence of the page, its first part (up to the colon) in bold, as the board writes it. */
const sentence = computed((): { strong: string; rest: string } | null => {
    const text = props.consumption.sentence;

    if (text === null) {
        return null;
    }

    const colon = text.indexOf(': ');

    return colon === -1
        ? { strong: '', rest: text }
        : { strong: text.slice(0, colon), rest: text.slice(colon) };
});

/**
 * The address of the document a bill was read from.
 *
 * @param documentId - the id of the document, null for a consumption written by hand
 * @returns the address, or null
 */
function documentUrl(documentId: number | null): string | null {
    return documentId === null ? null : documentPage(documentId).url;
}

/**
 * Writes the price of a bill.
 *
 * @param price - euros per unit, null when it cannot be worked out
 * @returns for example `0,30 €`, or a dash
 */
function price(price: number | null): string {
    return price === null ? '–' : euro(price);
}

/** The classes of a small coloured label of a bill: *Paid*, *To pay*, *Estimated*, *Check this consumption*. */
const badge = cn(
    'inline-flex h-[22px] items-center gap-1 rounded-tag px-2 text-meta-strong whitespace-nowrap',
);

/** The text of the empty page, by type of utility. */
const emptyText = computed((): string =>
    props.consumption.utility.type === 'gas'
        ? trans(
              'When the gas bill arrives I read consumption and period, together with the amount, and the chart starts from there.',
          )
        : props.consumption.utility.type === 'water'
          ? trans(
                'When the water bill arrives I read consumption and period, together with the amount, and the chart starts from there.',
            )
          : trans(
                'When the electricity bill arrives I read consumption and period, together with the amount, and the chart starts from there.',
            ),
);
</script>

<template>
    <Head :title="$t('Usage')" />

    <DefineBadges v-slot="{ bill, paid }">
        <span
            v-if="
                (paid && bill.paid) ||
                !bill.paid ||
                bill.is_estimated ||
                bill.check
            "
            class="flex flex-wrap items-center gap-1.5"
        >
            <span
                v-if="!bill.paid"
                :class="cn(badge, 'bg-warning-soft text-warning')"
                >{{ $t('To pay') }}</span
            >
            <span
                v-else-if="paid"
                :class="cn(badge, 'bg-paid-soft text-paid')"
                >{{ $t('Bill paid') }}</span
            >
            <span
                v-if="bill.is_estimated"
                :class="cn(badge, 'bg-canvas text-ink-soft')"
                >{{ $t('Estimated bill') }}</span
            >
            <span
                v-if="bill.check"
                class="flex items-center gap-1.5 text-meta-strong text-warning"
            >
                <FmIcon :icon="TriangleAlert" :size="16" />{{
                    $t('Check this consumption')
                }}
            </span>
        </span>
    </DefineBadges>

    <DefineFigures>
        <div
            :class="
                cn(
                    'grid gap-x-3 gap-y-3.5 rounded-card bg-card p-4 shadow-card',
                    wide ? 'grid-cols-4' : 'grid-cols-2',
                )
            "
        >
            <span class="flex min-w-0 flex-col gap-1">
                <span class="text-meta-strong text-ink-soft">{{
                    $t('Last 12 months')
                }}</span>
                <span class="text-stat whitespace-nowrap">{{
                    quantity(consumption.figures.quantity)
                }}</span>
                <span class="text-meta text-ink-soft">{{ yearLine }}</span>
            </span>
            <span class="flex min-w-0 flex-col gap-1">
                <span class="text-meta-strong text-ink-soft">{{
                    $t('The bills')
                }}</span>
                <span class="text-stat whitespace-nowrap">{{
                    euro(consumption.figures.amount)
                }}</span>
                <span class="text-meta text-ink-soft">{{ billsLine }}</span>
            </span>
            <span class="flex min-w-0 flex-col gap-1">
                <span class="text-meta-strong text-ink-soft">{{
                    $t('On average')
                }}</span>
                <span class="text-stat whitespace-nowrap">{{
                    price(consumption.figures.averagePrice)
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    $t('per :unit, everything included', { unit })
                }}</span>
            </span>
            <span
                v-if="consumption.figures.dearestMonth !== null"
                class="flex min-w-0 flex-col gap-1"
            >
                <span class="text-meta-strong text-ink-soft">{{
                    capitalized(longMonth(consumption.figures.dearestMonth))
                }}</span>
                <span class="text-stat whitespace-nowrap text-warning">{{
                    price(consumption.figures.dearestPrice)
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    $t('per :unit, the dearest', { unit })
                }}</span>
            </span>
        </div>
    </DefineFigures>

    <DefineToCheck>
        <FmCard
            v-if="consumption.toCheck.length > 0"
            :title="
                $t('To check · :count', {
                    count: String(consumption.toCheck.length),
                })
            "
            list
        >
            <div
                v-for="item in consumption.toCheck"
                :key="item.document_id"
                class="flex items-center gap-3 px-4 py-3"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-warning-soft text-warning"
                >
                    <FmIcon :icon="CircleAlert" :size="20" />
                </span>
                <span class="flex min-w-0 grow flex-col gap-0.5">
                    <Link
                        :href="documentPage(item.document_id).url"
                        class="truncate text-row text-ink no-underline hover:text-action-dark"
                        >{{ item.title }}</Link
                    >
                    <span class="text-meta text-ink-soft">{{
                        item.reason
                    }}</span>
                </span>
                <FmButton
                    v-if="consumption.canCreate"
                    variant="neutral"
                    compact
                    @click="
                        openSheet(null, {
                            id: item.document_id,
                            title: item.title,
                        })
                    "
                    >{{ $t('Write it') }}</FmButton
                >
            </div>
        </FmCard>
    </DefineToCheck>

    <DefineDetail>
        <section
            v-if="detail"
            :aria-label="monthName(detail.month.month)"
            class="flex flex-col gap-3.5 rounded-card bg-card p-4 shadow-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="m-0 text-section">
                    {{ monthName(detail.month.month) }}
                </h2>
                <FmButton
                    variant="text"
                    :icon="X"
                    :label="$t('Close the month')"
                    small
                    plain
                    muted
                    @click="openMonth(null)"
                />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-meta-strong text-ink-soft">{{
                        $t('Consumption')
                    }}</span>
                    <span class="text-stat whitespace-nowrap">{{
                        detail.month.quantity === null
                            ? '–'
                            : quantity(detail.month.quantity)
                    }}</span>
                </span>
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-meta-strong text-ink-soft">{{
                        $t('Spending')
                    }}</span>
                    <span class="text-stat whitespace-nowrap">{{
                        detail.month.amount === null
                            ? '–'
                            : euro(detail.month.amount)
                    }}</span>
                </span>
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-meta-strong text-ink-soft">{{
                        $t('Price')
                    }}</span>
                    <span class="text-stat whitespace-nowrap">{{
                        price(detail.month.price)
                    }}</span>
                    <span class="text-meta text-ink-soft">{{
                        $t('per :unit', { unit })
                    }}</span>
                </span>
            </div>
            <p
                v-if="comparison"
                class="m-0 flex items-center gap-2 text-body-sm text-ink"
            >
                <FmIcon
                    v-if="comparison.icon"
                    :icon="comparison.icon"
                    :size="18"
                    :class="cn('shrink-0', comparison.tone)"
                />
                <span
                    ><strong v-if="comparison.strong">{{
                        comparison.strong
                    }}</strong
                    >{{ comparison.strong ? ' ' : ''
                    }}{{ comparison.rest }}</span
                >
            </p>
            <template v-if="detail.bills.length > 0">
                <span class="-mb-1.5 text-meta-strong text-ink-soft">{{
                    $tChoice(
                        '{1} The bill of this month|[2,*] The bills of this month',
                        detail.bills.length,
                    )
                }}</span>
                <div
                    v-for="bill in detail.bills"
                    :key="bill.id"
                    class="flex items-center gap-3 border-t border-divider pt-3"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-home-soft text-home-text"
                    >
                        <FmIcon :icon="FileText" :size="20" />
                    </span>
                    <span class="flex min-w-0 grow flex-col gap-1">
                        <Link
                            v-if="documentUrl(bill.document_id)"
                            :href="documentUrl(bill.document_id)!"
                            class="text-row text-ink no-underline hover:text-action-dark"
                            >{{ billTitle(bill) }}</Link
                        >
                        <span v-else class="text-row">{{
                            billTitle(bill)
                        }}</span>
                        <span class="text-meta text-ink-soft"
                            >{{
                                billDates(bill.period_start, bill.period_end)
                            }}
                            · {{ quantity(bill.quantity)
                            }}<template v-if="bill.amount !== null">
                                · {{ euro(bill.amount) }}</template
                            ></span
                        >
                        <ReuseBadges :bill="bill" :paid="true" />
                    </span>
                    <FmButton
                        v-if="bill.can_update"
                        variant="text"
                        :icon="Pencil"
                        :label="
                            $t('Correct the consumption of :bill', {
                                bill: billTitle(bill),
                            })
                        "
                        plain
                        muted
                        @click="openSheet(bill)"
                    />
                </div>
            </template>
        </section>
    </DefineDetail>

    <div class="flex max-w-[1120px] flex-col gap-3.5 wide:gap-5">
        <FmTitle :title="$t('Usage')">
            <template v-if="wide && hasBills && consumption.canCreate" #actions>
                <FmButton
                    variant="neutral"
                    :icon="Plus"
                    @click="openSheet(null)"
                    >{{ $t('Add a consumption') }}</FmButton
                >
            </template>
        </FmTitle>

        <div
            class="flex flex-col gap-3.5 wide:flex-row wide:flex-wrap wide:items-center wide:justify-between wide:gap-x-6 wide:gap-y-3"
        >
            <div
                class="flex flex-col gap-3.5 wide:min-w-0 wide:flex-row wide:items-center wide:gap-4"
            >
                <FmTabs
                    variant="pill"
                    :tabs="typeTabs"
                    :label="$t('Utility')"
                    class="wide:w-[360px] wide:shrink"
                />
                <div
                    v-if="consumption.utilities.length > 1"
                    class="min-w-0 self-start"
                >
                    <FmMenu choice :label="consumption.utility.title">
                        <FmMenuItem
                            v-for="utility in consumption.utilities"
                            :key="utility.id"
                            :href="pageUrl({ utility: utility.id })"
                            :icon="
                                utility.id === consumption.utility.id
                                    ? Check
                                    : undefined
                            "
                        >
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span class="text-label">{{
                                    utility.title
                                }}</span>
                                <span
                                    v-if="utilityDates(utility)"
                                    class="text-meta text-ink-soft"
                                    >{{ utilityDates(utility) }}</span
                                >
                            </span>
                        </FmMenuItem>
                    </FmMenu>
                </div>
                <span v-else class="px-1 text-body-sm text-ink-soft">{{
                    consumption.utility.title
                }}</span>
            </div>
            <div
                v-if="hasBills"
                class="flex items-center gap-2 wide:min-w-[360px]"
            >
                <FmMonthSwitch
                    class="grow"
                    :month="consumption.to"
                    :span="12"
                    :min="consumption.previous ?? consumption.to"
                    :max="consumption.next ?? consumption.to"
                    @change="goTo"
                />
                <FmSectionLink
                    v-if="consumption.next !== null"
                    @click="goTo(null)"
                    >{{ $t('Today') }}</FmSectionLink
                >
            </div>
        </div>

        <template v-if="hasBills">
            <ReuseFigures v-if="wide" />

            <div
                :class="
                    cn(
                        'flex flex-col gap-3.5',
                        wide && 'flex-row items-start gap-6',
                    )
                "
            >
                <div
                    class="flex min-w-0 flex-col gap-3.5 wide:flex-[3_1_0] wide:gap-4"
                >
                    <FormKit
                        v-model="view"
                        type="togglebuttons"
                        enforced
                        name="view"
                        outer-class="condensed"
                        :label="$t('What the chart shows')"
                        :options="viewChoices"
                        :classes="{
                            label: 'sr-only',
                            outer: 'mb-0! self-start',
                        }"
                    />
                    <div class="flex flex-col gap-1.5">
                        <FmBarChart
                            :months="bars"
                            marks="previous"
                            :selected="consumption.month"
                            :title="
                                view === 'quantity'
                                    ? $t(':unit per month', { unit })
                                    : $t('Spending per month')
                            "
                            :value-label="
                                view === 'quantity' ? unit : $t('Spending')
                            "
                            :format="view === 'quantity' ? quantity : euro"
                            :short="
                                view === 'quantity' ? formatNumber : undefined
                            "
                            @select="openMonth"
                        />
                        <p v-if="note" class="m-0 px-1 text-meta text-ink-soft">
                            {{ note }}
                        </p>
                    </div>

                    <template v-if="!wide">
                        <ReuseDetail />
                        <ReuseFigures />
                    </template>

                    <FmNotice v-if="sentence" tone="warning">
                        <strong v-if="sentence.strong">{{
                            sentence.strong
                        }}</strong
                        >{{ sentence.rest }}
                    </FmNotice>

                    <FmCard :title="$t('Bill by bill')" list>
                        <template
                            v-if="!wide && consumption.canCreate"
                            #actions
                        >
                            <FmSectionLink
                                :icon="Plus"
                                @click="openSheet(null)"
                                >{{ $t('Add a consumption') }}</FmSectionLink
                            >
                        </template>
                        <table
                            v-if="wide"
                            :aria-label="$t('Bill by bill')"
                            class="w-full border-collapse text-body-sm"
                        >
                            <thead
                                class="border-b border-divider bg-card-sunken"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left text-meta-strong text-ink-soft"
                                    >
                                        {{ $t('Bill') }}
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-meta-strong text-ink-soft"
                                    >
                                        {{ $t('Consumption') }}
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-meta-strong text-ink-soft"
                                    >
                                        {{ $t('Amount') }}
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-meta-strong text-ink-soft"
                                    >
                                        {{ $t('Per :unit', { unit }) }}
                                    </th>
                                    <th class="w-11 px-2 py-2.5">
                                        <span class="sr-only">{{
                                            $t('Correct')
                                        }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="bill in consumption.bills"
                                    :key="bill.id"
                                    class="border-b border-divider last:border-0"
                                >
                                    <td class="px-4 py-2.5">
                                        <Link
                                            v-if="documentUrl(bill.document_id)"
                                            :href="
                                                documentUrl(bill.document_id)!
                                            "
                                            class="text-row text-ink no-underline hover:text-action-dark"
                                            >{{ billTitle(bill) }}</Link
                                        >
                                        <span v-else class="text-row">{{
                                            billTitle(bill)
                                        }}</span>
                                        <span
                                            class="mt-0.5 flex flex-wrap items-center gap-1.5 text-meta text-ink-soft"
                                        >
                                            {{
                                                billDates(
                                                    bill.period_start,
                                                    bill.period_end,
                                                )
                                            }}
                                            <ReuseBadges :bill="bill" />
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right whitespace-nowrap"
                                    >
                                        {{ quantity(bill.quantity) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right whitespace-nowrap"
                                    >
                                        {{
                                            bill.amount === null
                                                ? '–'
                                                : euro(bill.amount)
                                        }}
                                    </td>
                                    <td
                                        :class="
                                            cn(
                                                'px-4 py-2.5 text-right whitespace-nowrap',
                                                bill.above_average &&
                                                    'text-label text-warning',
                                            )
                                        "
                                    >
                                        {{ price(bill.price)
                                        }}<template v-if="bill.above_average">
                                            {{ ' '
                                            }}<span aria-hidden="true">↑</span
                                            ><span class="sr-only">{{
                                                $t(', above the average')
                                            }}</span></template
                                        >
                                    </td>
                                    <td class="w-11 px-2 py-1 text-right">
                                        <FmButton
                                            v-if="bill.can_update"
                                            variant="text"
                                            :icon="Pencil"
                                            :label="
                                                $t(
                                                    'Correct the consumption of :bill',
                                                    { bill: billTitle(bill) },
                                                )
                                            "
                                            small
                                            plain
                                            muted
                                            @click="openSheet(bill)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <ul
                            v-else
                            :aria-label="$t('Bill by bill')"
                            class="m-0 list-none divide-y divide-divider p-0"
                        >
                            <li
                                v-for="bill in consumption.bills"
                                :key="bill.id"
                                :class="
                                    cn(
                                        'relative flex items-center gap-3 px-4 py-3',
                                        bill.document_id !== null && rowLink,
                                    )
                                "
                            >
                                <span
                                    class="flex min-w-0 grow flex-col gap-[3px]"
                                >
                                    <!-- The link covers the whole row -->
                                    <Link
                                        v-if="documentUrl(bill.document_id)"
                                        :href="documentUrl(bill.document_id)!"
                                        class="text-row text-ink no-underline after:absolute after:inset-0"
                                        >{{ billTitle(bill) }}</Link
                                    >
                                    <span v-else class="text-row">{{
                                        billTitle(bill)
                                    }}</span>
                                    <span class="text-meta text-ink-soft"
                                        >{{
                                            billDates(
                                                bill.period_start,
                                                bill.period_end,
                                            )
                                        }}<template v-if="bill.amount !== null">
                                            · {{ euro(bill.amount) }}</template
                                        ></span
                                    >
                                    <ReuseBadges :bill="bill" />
                                </span>
                                <span
                                    class="flex shrink-0 flex-col items-end gap-[3px]"
                                >
                                    <span
                                        class="text-row-amount whitespace-nowrap"
                                        >{{ quantity(bill.quantity) }}</span
                                    >
                                    <span
                                        :class="
                                            cn(
                                                'whitespace-nowrap',
                                                bill.above_average
                                                    ? 'text-meta-strong text-warning'
                                                    : 'text-meta text-ink-soft',
                                            )
                                        "
                                        >{{
                                            $t(':price per :unit', {
                                                price: price(bill.price),
                                                unit,
                                            })
                                        }}<template v-if="bill.above_average">
                                            {{ ' '
                                            }}<span aria-hidden="true">↑</span
                                            ><span class="sr-only">{{
                                                $t(', above the average')
                                            }}</span></template
                                        ></span
                                    >
                                </span>
                            </li>
                        </ul>
                    </FmCard>

                    <ReuseToCheck />

                    <p class="m-0 px-1 text-meta text-ink-soft">
                        {{
                            $t(
                                'We read consumption and period from the bills, together with the amount. The amount is the total of the bill, with transport, charges and VAT.',
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="wide && detail"
                    class="sticky top-[calc(var(--app-header)+24px)] flex min-w-0 flex-[2_1_0] flex-col gap-4"
                >
                    <ReuseDetail />
                </div>
            </div>
        </template>

        <template v-else>
            <section
                v-if="consumption.archive !== null"
                :aria-label="$t('The bills without consumption')"
                class="flex flex-col items-start gap-3.5 rounded-card bg-card p-5 shadow-card"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-xl bg-home-soft text-home-text"
                >
                    <FmIcon :icon="ScanText" :size="24" />
                </span>
                <h2 class="m-0 text-section">
                    {{
                        $tChoice(
                            '{1} :count bill without consumption|[2,*] :count bills without consumption',
                            consumption.archive.count,
                            { count: String(consumption.archive.count) },
                        )
                    }}
                </h2>
                <p class="m-0 text-body-sm text-ink-soft">
                    {{
                        $t(
                            'I read them one by one and take consumption and period. Reading them costs about :cost (:count readings, you have :left left this month).',
                            {
                                cost: formatDollars(consumption.archive.cost),
                                count: String(consumption.archive.count),
                                left: String(consumption.archive.left),
                            },
                        )
                    }}
                </p>
                <!-- Reading them arrives with the next step -->
                <FmButton :icon="Sparkles" wide disabled>{{
                    $t('Read the bills')
                }}</FmButton>
                <FmSectionLink
                    v-if="consumption.canCreate"
                    :icon="Plus"
                    class="self-center"
                    @click="openSheet(null)"
                    >{{ $t('Add a consumption by hand') }}</FmSectionLink
                >
            </section>

            <FmEmpty
                v-else
                :icon="typeIcon"
                :title="$t('Upload a bill: I\'ll read the consumption')"
                :text="emptyText"
            >
                <FmButton :icon="Upload" :href="upload().url">{{
                    $t('Upload')
                }}</FmButton>
                <FmSectionLink
                    v-if="consumption.canCreate"
                    :icon="Plus"
                    @click="openSheet(null)"
                    >{{ $t('Add a consumption by hand') }}</FmSectionLink
                >
            </FmEmpty>

            <ReuseToCheck />
        </template>
    </div>

    <ConsumptionSheet
        v-model:open="sheetOpen"
        :utility="consumption.utility"
        :bill="sheetFor.bill"
        :document="sheetFor.document"
    />
</template>
