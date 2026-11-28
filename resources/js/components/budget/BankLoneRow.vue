<script setup lang="ts">
/**
 * A movement of the bank that is in no group, a row of *Lone movements* in the tab *To sort* of *From the bank*, as
 * the boards *Dalla banca* draw it: the text, the day and the account (the day in its own column when wide), the
 * amount, the commission that goes with a direct debit, a pending one marked, a commission left alone marked, and
 * the note when two filters take it and none of them sorts it. A booked movement in euros has *Create the expense*
 * (or the income), *It belongs to an expense* (or an income) and the menu with *It is a transfer* and *Ignore*; a
 * commission left alone has *It is the commission of…*; one put aside has *Put back among the movements*; a text
 * that came back three months in a row offers *It comes back every month*. The row only says what was tapped
 * (`assign`, `transfer`, `fee`, `ignore`): the page opens the sheets and writes. The slot `proposal` is where
 * «Maybe it is…» goes.
 *
 * @group rows
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ChevronRight,
    CornerDownRight,
    EyeOff,
    Link as LinkIcon,
    Plus,
    Repeat,
    RotateCcw,
    Search,
    TriangleAlert,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import { dayOf } from '@/lib/banks';
import { listNames } from '@/lib/names';
import { cn } from '@/lib/utils';
import { create } from '@/routes/budget/movements';
import { create as createPlanned } from '@/routes/budget/planned';

const { row, canSort, canPlan } = defineProps<{
    /** The movement, as the server sends it in `rows`. */
    row: App.Data.BankTransactionData;
    /** Whether who looks can sort the movements (every account of the family). */
    canSort: boolean;
    /** Whether who looks can create a planned expense or income (an administrator). */
    canPlan: boolean;
}>();

const emit = defineEmits<{
    /** *It belongs to an expense* (or an income) was tapped. */
    assign: [row: App.Data.BankTransactionData];
    /** *It is a transfer* was chosen in the menu. */
    transfer: [row: App.Data.BankTransactionData];
    /** *It is the commission of…* was tapped. */
    fee: [row: App.Data.BankTransactionData];
    /** *Ignore* was chosen, or *Put back among the movements* tapped on one put aside. */
    ignore: [row: App.Data.BankTransactionData];
}>();

defineSlots<{
    /** What the system proposes for the movement («Maybe it is…»), under the text. */
    proposal?: () => unknown;
}>();

const wide = useWideLayout();
const { money } = useFormat();

/** The row can be sorted here: booked, in euros, not put aside, and the person may sort. */
const sortable = computed(
    (): boolean =>
        canSort && !row.ignored && !row.pending && row.currency === 'EUR',
);

/** The row offers «it comes back every month»: its text does, it can be sorted and the person can plan. */
const recurs = computed(
    (): boolean => row.recurring && canPlan && sortable.value,
);

/** Money that came in. */
const income = computed((): boolean => row.amount > 0);

/** The form of a new expense or income that starts from the movement. */
const createUrl = computed((): string =>
    create.url({ query: { bank: row.id } }),
);

/** The form of a new planned expense (or income) that starts from the movement and the months before it. */
const recurUrl = computed((): string =>
    createPlanned.url(income.value ? 'income' : 'expense', {
        query: { bank: row.id },
    }),
);

/** The words of the offer for a payment or a credit. */
const recurLabel = computed((): string =>
    income.value
        ? trans('It comes back every month: create a planned income?')
        : trans('It comes back every month: create a planned expense?'),
);

/** The label of the button that opens the panel of the expenses or incomes it can belong to. */
const assignLabel = computed((): string =>
    income.value
        ? trans('It belongs to an income')
        : trans('It belongs to an expense'),
);

/** The note of a movement two filters or more take, null when they do not. */
const twoFilters = computed((): string | null =>
    row.two_filters && row.two_filters.length > 1
        ? trans(
              ':count filters take it: :names. None of them sorts it: change one of them.',
              {
                  count: String(row.two_filters.length),
                  names: listNames(row.two_filters),
              },
          )
        : null,
);

/** The commission with its day, under the text. */
const feeLine = computed((): string | null =>
    row.fee
        ? trans('+ :fee commission, :day', {
              fee: money(Math.abs(row.fee.amount), row.currency),
              day: dayOf({ booked_on: row.fee.booked_on, value_on: null }),
          })
        : null,
);

/** The look of the offer to create a planned expense, as the boards draw it. */
const recurClass = cn(
    'flex items-center gap-2 rounded-md bg-action-soft px-3 py-2 text-label text-action-dark',
);

/** The look of a small grey or yellow tag (*Commission*, *Pending*). */
const tagClass = cn(
    'inline-flex self-start rounded-tag px-2 py-[3px] text-meta-strong whitespace-nowrap',
);
</script>

<template>
    <li
        :data-wide="wide ? '' : undefined"
        :class="
            wide
                ? 'grid grid-cols-[56px_minmax(0,1fr)_150px_auto] items-start gap-4 px-5 py-3.5'
                : 'flex flex-col gap-2.5 px-4 py-3.5'
        "
    >
        <span v-if="wide" class="pt-[11px] text-label text-ink">{{
            dayOf(row)
        }}</span>
        <div :class="wide ? 'contents' : 'flex items-start gap-3'">
            <span
                :class="
                    cn('flex min-w-0 grow flex-col', wide ? 'gap-1' : 'gap-0.5')
                "
            >
                <span class="text-body-sm wrap-anywhere text-ink">{{
                    row.text
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    wide
                        ? row.account
                        : `${dayOf(row)} ${row.account ? `· ${row.account}` : ''}`
                }}</span>
                <span
                    v-if="feeLine"
                    class="flex items-center gap-1.5 text-meta text-ink-soft"
                    ><CornerDownRight
                        aria-hidden="true"
                        class="size-3.5 shrink-0"
                    />{{ feeLine }}</span
                >
                <template v-if="wide">
                    <slot name="proposal" />
                    <span
                        v-if="twoFilters"
                        role="note"
                        class="flex items-start gap-2 rounded-md bg-warning-soft px-2.5 py-2 text-meta text-ink"
                    >
                        <TriangleAlert
                            aria-hidden="true"
                            class="mt-px size-4 shrink-0 text-warning"
                        />{{ twoFilters }}
                    </span>
                    <Link
                        v-if="recurs"
                        :href="recurUrl"
                        :class="cn(recurClass, 'min-h-9 self-start')"
                    >
                        <Repeat aria-hidden="true" class="size-[18px]" />
                        {{ recurLabel }}
                        <ChevronRight aria-hidden="true" class="size-[18px]" />
                    </Link>
                    <span
                        v-if="row.orphan_fee"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span
                            :class="
                                cn(tagClass, 'bg-card-sunken text-ink-soft')
                            "
                            >{{ $t('Commission') }}</span
                        >
                        <span class="text-meta text-ink-soft">{{
                            $t('Left without its direct debit')
                        }}</span>
                    </span>
                    <span
                        v-if="row.pending"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span
                            :class="
                                cn(tagClass, 'bg-warning-soft text-warning')
                            "
                            >{{ $t('Pending') }}</span
                        >
                        <span class="text-meta text-ink-soft">{{
                            $t('It is sorted when the bank books it')
                        }}</span>
                    </span>
                </template>
            </span>
            <span
                :class="
                    cn(
                        'flex shrink-0 flex-col items-end',
                        wide && 'pt-2.5 text-right',
                    )
                "
            >
                <span
                    :class="
                        cn(
                            'shrink-0 text-amount whitespace-nowrap',
                            row.pending && 'text-ink-soft',
                            row.amount > 0 && 'text-paid',
                        )
                    "
                    >{{ money(row.amount, row.currency) }}</span
                >
                <span
                    v-if="row.fee"
                    class="text-meta whitespace-nowrap text-ink-soft"
                    >+ {{ money(Math.abs(row.fee.amount), row.currency) }}</span
                >
            </span>
        </div>
        <template v-if="!wide">
            <slot name="proposal" />
            <span
                v-if="twoFilters"
                role="note"
                class="flex items-start gap-2 rounded-md bg-warning-soft px-2.5 py-2 text-meta text-ink"
            >
                <TriangleAlert
                    aria-hidden="true"
                    class="mt-px size-4 shrink-0 text-warning"
                />{{ twoFilters }}
            </span>
            <Link
                v-if="recurs"
                :href="recurUrl"
                :class="cn(recurClass, 'min-h-11')"
            >
                <Repeat aria-hidden="true" class="size-[18px] shrink-0" />
                <span class="grow">{{ recurLabel }}</span>
                <ChevronRight aria-hidden="true" class="size-[18px] shrink-0" />
            </Link>
        </template>
        <div
            v-if="canSort && (row.ignored || !row.pending)"
            :class="
                wide
                    ? 'flex flex-nowrap items-center justify-end gap-2'
                    : 'flex flex-wrap items-center gap-2'
            "
        >
            <FmButton
                v-if="row.ignored"
                variant="neutral"
                :icon="RotateCcw"
                @click="emit('ignore', row)"
                >{{ $t('Put back among the movements') }}</FmButton
            >
            <template v-else>
                <FmButton
                    v-if="sortable && row.orphan_fee"
                    variant="neutral"
                    row-action
                    :icon="LinkIcon"
                    @click="emit('fee', row)"
                    >{{ $t('It is the commission of…') }}</FmButton
                >
                <template v-else-if="sortable">
                    <FmButton
                        variant="neutral"
                        row-action
                        :icon="Plus"
                        :href="createUrl"
                        >{{
                            income
                                ? $t('Create the income')
                                : $t('Create the expense')
                        }}</FmButton
                    >
                    <FmButton
                        variant="neutral"
                        row-action
                        :icon="Search"
                        @click="emit('assign', row)"
                        >{{ assignLabel }}</FmButton
                    >
                </template>
                <span
                    class="ml-auto flex text-ink-soft [&_button]:text-ink-soft"
                    ><FmMenu plain>
                        <FmMenuItem
                            v-if="sortable"
                            :icon="ArrowLeftRight"
                            @select="emit('transfer', row)"
                            >{{ $t('It is a transfer') }}</FmMenuItem
                        >
                        <FmMenuItem
                            :icon="EyeOff"
                            @select="emit('ignore', row)"
                            >{{ $t('Ignore') }}</FmMenuItem
                        >
                    </FmMenu></span
                >
            </template>
        </div>
        <template v-if="!wide">
            <div
                v-if="row.orphan_fee"
                class="flex flex-wrap items-center gap-2"
            >
                <span :class="cn(tagClass, 'bg-card-sunken text-ink-soft')">{{
                    $t('Commission')
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    $t('Left without its direct debit')
                }}</span>
            </div>
            <div v-if="row.pending" class="flex flex-wrap items-center gap-2">
                <span :class="cn(tagClass, 'bg-warning-soft text-warning')">{{
                    $t('Pending')
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    $t('It is sorted when the bank books it')
                }}</span>
            </div>
        </template>
    </li>
</template>
