<script setup lang="ts">
/**
 * *Budget → From the bank*, a page of its own under the Budget (trail *Budget › From the bank*, no tabs of the
 * Budget), as the boards *Dalla banca* and *Dalla banca-pc* draw it: the transactions the bank gave that no fixed
 * expense recognised, newest first, 50 at a time and the next ones while scrolling. A pending transaction says so
 * and waits for the bank to book it. *To sort*, *All* and *Filters* are the three tabs inside the page, in the grey
 * bar of the boards (*Filters* is empty for now). *All* lists every transaction the bank gave with its tag and what it
 * is linked to, under the filters (text, account, status, month, direction); the text of a row opens the transaction as
 * the bank gave it (a bottom sheet on the phone, a panel beside the list when wide), *It was not this one* takes a link
 * back after asking and *Put back among the movements* brings a transaction set aside back;
 * *Update* reads the banks now (the server says how many movements are new) and under it comes the last read and
 * the hours of the automatic ones. A bank that has to be connected again has a strip with *Reconnect* for who can
 * change the banks. *Ignore* hides a transaction nobody has to deal with, and under *Ignored* each one has *Put back
 * among the movements*; the row goes at once and the server writes it. A booked transaction in euros also has
 * *Create the expense* (or the income: the form of a new movement that starts from it) and *It belongs to a planned
 * expense* (or income), which opens a panel with the planned items that have a due date to pay: the closest due date is
 * paid and the row goes. A transaction whose text came back three months in a row also offers *It comes back every
 * month: create a planned expense?*, the form of a new planned item that starts from it.
 */
import { Head, Link, router, WhenVisible } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ChevronRight,
    EyeOff,
    Funnel,
    Landmark,
    Link as LinkIcon,
    List,
    Plus,
    RefreshCw,
    Repeat,
    RotateCcw,
} from '@lucide/vue';
import { vAutoAnimate } from '@formkit/auto-animate/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { usePageMeta } from '@/composables/usePageMeta';
import FmButton from '@/components/fm/FmButton.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { Tab } from '@/components/fm/FmTabs.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import BankAllFilters from '@/components/budget/BankAllFilters.vue';
import BankAllList from '@/components/budget/BankAllList.vue';
import BankAssignSheet from '@/components/budget/BankAssignSheet.vue';
import type { BankAssignChoice } from '@/lib/bankAssign';
import BankRawPanel from '@/components/budget/BankRawPanel.vue';
import BankFeeSheet from '@/components/budget/BankFeeSheet.vue';
import BankTransferSheet from '@/components/budget/BankTransferSheet.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import { useBankFeed } from '@/composables/useBankFeed';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import { undoWords } from '@/lib/bankUndo';
import { dayOf, lastReadText, readTimes } from '@/lib/banks';
import { cn } from '@/lib/utils';
import {
    assign,
    fee as feeRoute,
    ignore,
    index as bankIndex,
    restore,
    sync,
    transfer,
} from '@/routes/budget/bank';
import { create } from '@/routes/budget/movements';
import { create as createPlanned } from '@/routes/budget/planned';
import { index as banksIndex } from '@/routes/banks';

const props = defineProps<{
    /** Which tab is open: the transactions to sort, all of them or the filters. */
    show: 'unsorted' | 'all' | 'filters';
    /** The blocks of the list loaded so far. */
    rows: App.Data.BankTransactionData[];
    /** The number of the next block of the list, null at the last one. */
    next: number | null;
    /** The banks that have to be connected again. */
    banks_to_reconnect: string[];
    /** Whether who looks can sort the transactions (every account of the family). */
    can_sort: boolean;
    /** Whether who looks can create a planned expense or income (an administrator). */
    can_plan: boolean;
    /** Whether who looks can connect the banks again (an administrator). */
    can_change_banks: boolean;
    /** When a bank was read last (`yyyy-mm-ddThh:mm`, Rome); null before the first read. */
    last_synced_at: string | null;
    /** The hours of the day the banks are read by themselves. */
    sync_hours: number[];
    /** The tab *All*: the filters in use, as the server read them. */
    filters?: AllFilters | null;
    /** The tab *All*: how many transactions the filters find and their sum. */
    total?: { count: number; sum: number } | null;
    /** The tab *All*: the accounts that have transactions, to filter by. */
    accounts?: { id: number; name: string }[];
    /** The open accounts of the family, for *It is a transfer*, with whether the bank gives their transactions. */
    accounts_all?: { id: number; name: string; connected: boolean }[];
}>();

/** The filters of the tab *All*, each null while it is not on. */
interface AllFilters {
    q: string | null;
    account: number | null;
    status: string | null;
    month: string | null;
    direction: string | null;
}

const bankFeed = useBankFeed();
const { money } = useFormat();
const wide = useWideLayout();

/** An update is running: the button waits, so a double tap does not read the banks twice. */
const updating = ref(false);

/** The number still to sort, from the shared props so it follows an update. */
const unsorted = computed((): number => bankFeed.value?.unsorted ?? 0);

/** The three tabs of the page, as the grey bar of the boards: the first says how many there are to sort. */
const filters = computed((): Tab[] => [
    {
        label:
            unsorted.value > 0
                ? trans('To sort · :count', {
                      count: String(unsorted.value),
                  })
                : trans('Left to sort'),
        href: bankIndex().url,
        current: props.show === 'unsorted',
    },
    {
        label: trans('All'),
        href: bankIndex({ query: { show: 'all' } }).url,
        current: props.show === 'all',
    },
    {
        label: trans('Filters'),
        href: bankIndex({ query: { show: 'filters' } }).url,
        current: props.show === 'filters',
    },
]);

/** The line under the buttons: the last read and when the next ones come by themselves. */
const readLine = computed((): string => {
    const times = readTimes(props.sync_hours);

    return props.last_synced_at
        ? trans(':update · they arrive by themselves :times', {
              update: lastReadText(props.last_synced_at),
              times,
          })
        : trans('They arrive by themselves :times', { times });
});

/** What the next block of the list asks for; under *All* the status too, as `?show=ignored` leaves it out of the address. */
const nextData = computed(() => ({
    page: props.next,
    ...(props.show === 'all'
        ? {
              show: 'all',
              ...(props.filters?.status
                  ? { status: props.filters.status }
                  : {}),
          }
        : {}),
}));

/** Reads the banks now. */
function update(): void {
    if (updating.value) {
        return;
    }

    router.post(
        sync().url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                updating.value = true;
            },
            onFinish: () => {
                updating.value = false;
            },
        },
    );
}

/** The rows that were ignored or put back here: they leave the list at once, the next load no longer has them. */
const gone = ref<number[]>([]);

/** The rows shown: the ones loaded, without the ones that went. */
const shown = computed(() =>
    props.rows.filter((row) => !gone.value.includes(row.id)),
);

/**
 * Hides a transaction (*Ignore*) or brings it back (*Put back*), without leaving the page: the row goes when the
 * server has written it.
 *
 * @param row - the transaction
 */
function toggleIgnored(row: App.Data.BankTransactionData): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            gone.value = [...gone.value, row.id];
        },
    };

    if (row.ignored) {
        router.delete(restore(row.id).url, options);
    } else {
        router.post(ignore(row.id).url, {}, options);
    }
}

/** The transaction the panel *It belongs to a planned expense* is open for, null while it is closed. */
const assigning = ref<App.Data.BankTransactionData | null>(null);

/** The panel is open. */
const assignOpen = computed({
    get: () => assigning.value !== null,
    set: (value: boolean) => {
        if (!value) {
            assigning.value = null;
        }
    },
});

/** What the server said when it refused the planned item. */
const assignError = ref<string | null>(null);

/** The planned item is being sent. */
const assignProcessing = ref(false);

/**
 * Whether the row can be sorted here: booked, in euros, not put aside, and the person may sort.
 *
 * @param row - the transaction
 */
function sortable(row: App.Data.BankTransactionData): boolean {
    return (
        props.can_sort && !row.ignored && !row.pending && row.currency === 'EUR'
    );
}

/**
 * Whether the row offers «it comes back every month»: its text does, it can be sorted and the person can plan.
 *
 * @param row - the transaction
 */
function recurs(row: App.Data.BankTransactionData): boolean {
    return row.recurring && props.can_plan && sortable(row);
}

/** The look of the offer to create a planned expense, as the boards draw it. */
const recurClass = cn(
    'flex items-center gap-2 rounded-md bg-action-soft px-3 py-2 text-label text-action-dark',
);

/** The tag *Commission* of a commission left alone, grey on the white of the board. */
const orphanTagClass = cn(
    'inline-flex self-start rounded-tag bg-card-sunken px-2 py-[3px] text-meta-strong whitespace-nowrap text-ink-soft',
);

/**
 * The words of the offer for a payment or a credit.
 *
 * @param row - the transaction
 */
function recurLabel(row: App.Data.BankTransactionData): string {
    return row.amount > 0
        ? trans('It comes back every month: create a planned income?')
        : trans('It comes back every month: create a planned expense?');
}

/**
 * The form of a new planned expense (or income) that starts from the transaction and the months before it.
 *
 * @param row - the transaction
 */
function recurUrl(row: App.Data.BankTransactionData): string {
    return createPlanned.url(row.amount > 0 ? 'income' : 'expense', {
        query: { bank: row.id },
    });
}

/**
 * The label of the button that opens the panel of the expenses or incomes it can belong to.
 *
 * @param row - the transaction
 */
function assignLabel(row: App.Data.BankTransactionData): string {
    return row.amount > 0
        ? trans('It belongs to an income')
        : trans('It belongs to an expense');
}

/**
 * The form of a new expense or income that starts from the transaction.
 *
 * @param row - the transaction
 */
function createUrl(row: App.Data.BankTransactionData): string {
    return create.url({ query: { bank: row.id } });
}

/**
 * Opens the panel for a transaction; the panel asks the server for what it can belong to.
 *
 * @param row - the transaction
 */
function openAssign(row: App.Data.BankTransactionData): void {
    assignError.value = null;
    assigning.value = row;
}

/**
 * Sends the choice of the panel: a due date to pay or a recorded movement to link, and the row goes.
 *
 * @param choice - the due date or the movement chosen
 */
function assignTo(choice: BankAssignChoice): void {
    const row = assigning.value;

    if (row === null) {
        return;
    }

    router.post(assign(row.id).url, choice, {
        preserveScroll: true,
        onStart: () => {
            assignError.value = null;
            assignProcessing.value = true;
        },
        onSuccess: () => {
            gone.value = [...gone.value, row.id];
            assigning.value = null;
        },
        onError: (messages) => {
            assignError.value =
                messages.planned_id ??
                messages.movement_id ??
                Object.values(messages)[0] ??
                null;
        },
        onFinish: () => {
            assignProcessing.value = false;
        },
    });
}

/** The transaction the sheet *It is a transfer* is open for, null while it is closed. */
const transferring = ref<App.Data.BankTransactionData | null>(null);

/** The sheet is open. */
const transferOpen = computed({
    get: () => transferring.value !== null,
    set: (value: boolean) => {
        if (!value) {
            transferring.value = null;
        }
    },
});

/** What the server said when it refused the account. */
const transferError = ref<string | null>(null);

/** The account is being sent. */
const transferProcessing = ref(false);

/**
 * Opens the sheet *It is a transfer* for a row.
 *
 * @param row - the transaction
 */
function openTransfer(row: App.Data.BankTransactionData): void {
    transferError.value = null;
    transferring.value = row;
}

/**
 * Sends the account chosen in the sheet: the row is a transfer and leaves the list to sort.
 *
 * @param accountId - the account on the other side
 */
function markTransfer(accountId: number): void {
    const row = transferring.value;

    if (row === null) {
        return;
    }

    router.post(
        transfer(row.id).url,
        { account_id: accountId },
        {
            preserveScroll: true,
            onStart: () => {
                transferError.value = null;
                transferProcessing.value = true;
            },
            onSuccess: () => {
                gone.value = [...gone.value, row.id];
                transferring.value = null;
            },
            onError: (messages) => {
                transferError.value =
                    messages.account_id ?? Object.values(messages)[0] ?? null;
            },
            onFinish: () => {
                transferProcessing.value = false;
            },
        },
    );
}

/** The commission the sheet *It is the commission of…* is open for, null while it is closed. */
const feeing = ref<App.Data.BankTransactionData | null>(null);

/** The sheet is open. */
const feeOpen = computed({
    get: () => feeing.value !== null,
    set: (value: boolean) => {
        if (!value) {
            feeing.value = null;
        }
    },
});

/** What the server said when it refused the expense. */
const feeError = ref<string | null>(null);

/** The expense is being sent. */
const feeProcessing = ref(false);

/**
 * Opens the sheet *It is the commission of…* for a row.
 *
 * @param row - the commission left alone
 */
function openFee(row: App.Data.BankTransactionData): void {
    feeError.value = null;
    feeing.value = row;
}

/**
 * Sends the expense chosen in the sheet: the commission joins it and leaves the list to sort.
 *
 * @param expenseId - the expense the commission belongs to
 */
function attachFee(expenseId: number): void {
    const row = feeing.value;

    if (row === null) {
        return;
    }

    router.post(
        feeRoute(row.id).url,
        { expense_id: expenseId },
        {
            preserveScroll: true,
            onStart: () => {
                feeError.value = null;
                feeProcessing.value = true;
            },
            onSuccess: () => {
                gone.value = [...gone.value, row.id];
                feeing.value = null;
            },
            onError: (messages) => {
                feeError.value =
                    messages.expense_id ?? Object.values(messages)[0] ?? null;
            },
            onFinish: () => {
                feeProcessing.value = false;
            },
        },
    );
}

/** The tab *All*: the filters in use, none when the server did not send them. */
const allFilters = computed(
    (): AllFilters =>
        props.filters ?? {
            q: null,
            account: null,
            status: null,
            month: null,
            direction: null,
        },
);

/** The tab *All*: whether a filter is on. */
const filterOn = computed((): boolean =>
    Object.values(allFilters.value).some((value) => value !== null),
);

/** The transaction whose bank panel is open, by id so it follows the row when the list changes. */
const rawId = ref<number | null>(null);

/** The transaction of the bank panel, null while it is closed or the row is gone. */
const rawRow = computed(
    (): App.Data.BankTransactionData | null =>
        shown.value.find((row) => row.id === rawId.value) ?? null,
);

/** The transaction whose link *It was not this one* is asking to take back. */
const undoing = ref<App.Data.BankTransactionData | null>(null);

/** The words of the question to take the link back, for what the undo does. */
const undoText = computed(() =>
    undoWords(undoing.value?.link?.kind, undoing.value?.link?.undo),
);

/** The question to take a link back is open. */
const undoOpen = computed({
    get: () => undoing.value !== null,
    set: (value: boolean) => {
        if (!value) {
            undoing.value = null;
        }
    },
});

/**
 * The row stopped being what the status filter looks for (put back, link taken back): it goes from the list now, the
 * server no longer sends it.
 *
 * @param row - the transaction that changed
 */
function leaveFilteredList(row: App.Data.BankTransactionData): void {
    if (allFilters.value.status !== null) {
        gone.value = [...gone.value, row.id];
    }
}

/**
 * *Put back among the movements* from the tab *All*.
 *
 * @param row - the transaction set aside
 */
function restoreRow(row: App.Data.BankTransactionData): void {
    router.delete(restore(row.id).url, {
        preserveScroll: true,
        onSuccess: () => leaveFilteredList(row),
    });
}

/** Takes the link of the transaction asked about back, once confirmed. */
function takeLinkBack(): void {
    const row = undoing.value;

    if (row?.link?.undo_url == null) {
        return;
    }

    router.delete(row.link.undo_url, {
        preserveScroll: true,
        onSuccess: () => leaveFilteredList(row),
        onFinish: () => {
            undoing.value = null;
        },
    });
}

// New filters start the list over: what went from the old list is not hidden from the new one
watch(
    () => props.filters,
    () => {
        gone.value = [];
    },
);

const { title, head } = usePageMeta({
    info: 'bank-transactions',
    title: () => trans('From the bank'),
    parent: 'budget',
});
</script>

<template>
    <Head :title="head" />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle :title="title" />

        <FmNotice
            v-for="bank in banks_to_reconnect"
            :key="bank"
            tone="warning"
            :title="$t(':bank needs to be connected again', { bank })"
        >
            {{
                can_change_banks
                    ? $t('Its movements do not arrive any more.')
                    : $t(
                          'Only who manages the family connects and disconnects the banks.',
                      )
            }}
            <template v-if="can_change_banks" #action>
                <FmButton
                    variant="outline"
                    :icon="RefreshCw"
                    :href="banksIndex().url"
                    >{{ $t('Reconnect') }}</FmButton
                >
            </template>
        </FmNotice>

        <div class="flex flex-wrap items-center gap-2.5">
            <FmTabs
                variant="pill"
                :tabs="filters"
                :label="$t('Movements from the bank')"
                class="min-w-fit flex-1 basis-50 wide:w-75 wide:max-w-full wide:flex-none"
            />
            <FmButton
                variant="neutral"
                :icon="RefreshCw"
                :disabled="updating"
                @click="update"
                >{{ $t('Update') }}</FmButton
            >
            <span class="basis-full text-meta text-ink-soft">{{
                readLine
            }}</span>
        </div>

        <FmEmpty
            v-if="show === 'filters'"
            :icon="Funnel"
            :title="$t('No filter yet')"
            :text="
                $t(
                    'A filter sorts by itself the movements that look alike, the ones that arrive and the ones already here.',
                )
            "
        />

        <template v-else-if="show === 'all'">
            <BankAllFilters
                :filters="allFilters"
                :accounts="accounts ?? []"
                :total="total ?? { count: shown.length, sum: 0 }"
            />
            <div
                :class="
                    wide && rawRow
                        ? 'grid grid-cols-[minmax(0,1fr)_380px] items-start gap-5'
                        : 'flex flex-col'
                "
            >
                <div class="flex min-w-0 flex-col gap-4">
                    <BankAllList
                        v-if="shown.length > 0"
                        :rows="shown"
                        :can-sort="can_sort"
                        :selected="rawRow?.id ?? null"
                        @open="(row) => (rawId = row.id)"
                        @undo="(row) => (undoing = row)"
                        @restore="restoreRow"
                    />
                    <FmEmpty
                        v-else
                        :icon="Landmark"
                        :title="
                            filterOn
                                ? $t('No movement matches the filters')
                                : $t('No movement yet')
                        "
                        :text="
                            filterOn
                                ? $t('Remove a filter or change the search.')
                                : $t(
                                      'The movements the bank gives you appear here.',
                                  )
                        "
                    />
                    <WhenVisible
                        v-if="next"
                        :key="next"
                        always
                        :params="{
                            data: nextData,
                            only: ['rows', 'next'],
                            preserveUrl: true,
                        }"
                    />
                </div>
                <div
                    v-if="wide && rawRow"
                    class="sticky top-[calc(var(--app-header,0px)+--spacing(4))]"
                >
                    <BankRawPanel
                        :row="rawRow"
                        :can-sort="can_sort"
                        @close="rawId = null"
                        @undo="(row) => (undoing = row)"
                    />
                </div>
            </div>
            <BankRawPanel
                v-if="!wide && rawRow"
                :row="rawRow"
                :can-sort="can_sort"
                @close="rawId = null"
                @undo="(row) => (undoing = row)"
            />
            <FmConfirm
                v-model:open="undoOpen"
                :question="undoText.question"
                :text="undoText.text"
                :confirm-label="$t('It was not this one')"
                @confirm="takeLinkBack"
            />
        </template>

        <FmEmpty
            v-else-if="shown.length === 0"
            :icon="Landmark"
            :title="$t('Nothing left to sort')"
            :text="
                $t(
                    'The filters and the fixed expenses with the rule sort by themselves the movements that arrive. Here you find only the ones nobody recognises.',
                )
            "
        >
            <FmButton
                variant="outline"
                :icon="List"
                :href="bankIndex({ query: { show: 'all' } }).url"
                >{{ $t('All the movements') }}</FmButton
            >
            <FmButton
                variant="outline"
                :icon="Funnel"
                :href="bankIndex({ query: { show: 'filters' } }).url"
                >{{ $t('Your filters') }}</FmButton
            >
        </FmEmpty>

        <template v-else>
            <ul
                v-auto-animate
                class="m-0 flex list-none flex-col divide-y divide-divider overflow-hidden rounded-card bg-card p-0 shadow-card"
            >
                <li
                    v-for="row in shown"
                    :key="row.id"
                    :data-wide="wide ? '' : undefined"
                    :class="
                        wide
                            ? 'grid grid-cols-[56px_minmax(0,1fr)_104px_auto] items-center gap-4 px-5 py-3.5'
                            : 'flex flex-col gap-2.5 px-4 py-3.5'
                    "
                >
                    <span v-if="wide" class="text-label text-ink">{{
                        dayOf(row)
                    }}</span>
                    <div :class="wide ? 'contents' : 'flex items-start gap-3'">
                        <span
                            :class="
                                cn(
                                    'flex min-w-0 grow flex-col',
                                    wide ? 'gap-1' : 'gap-0.5',
                                )
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
                                v-if="row.fee"
                                class="text-meta text-ink-soft"
                                >{{
                                    $t('+ :fee commission, :day', {
                                        fee: money(
                                            Math.abs(row.fee.amount),
                                            row.currency,
                                        ),
                                        day: dayOf({
                                            booked_on: row.fee.booked_on,
                                            value_on: null,
                                        }),
                                    })
                                }}</span
                            >
                            <Link
                                v-if="wide && recurs(row)"
                                :href="recurUrl(row)"
                                :class="cn(recurClass, 'min-h-9 self-start')"
                            >
                                <Repeat
                                    aria-hidden="true"
                                    class="size-[18px]"
                                />
                                {{ recurLabel(row) }}
                                <ChevronRight
                                    aria-hidden="true"
                                    class="size-[18px]"
                                />
                            </Link>
                            <span
                                v-if="wide && row.orphan_fee"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <span :class="orphanTagClass">{{
                                    $t('Commission')
                                }}</span>
                                <span class="text-meta text-ink-soft">{{
                                    $t('Left without its direct debit')
                                }}</span>
                            </span>
                            <span
                                v-if="wide && row.pending"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <span
                                    class="inline-flex self-start rounded-tag bg-warning-soft px-2 py-[3px] text-meta-strong whitespace-nowrap text-warning"
                                    >{{ $t('Pending') }}</span
                                >
                                <span class="text-meta text-ink-soft">{{
                                    $t('It is sorted when the bank books it')
                                }}</span>
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end">
                            <span
                                :class="
                                    cn(
                                        'shrink-0 text-amount whitespace-nowrap',
                                        wide && 'text-right',
                                        row.pending && 'text-ink-soft',
                                        row.amount > 0 && 'text-paid',
                                    )
                                "
                                >{{ money(row.amount, row.currency) }}</span
                            >
                            <span
                                v-if="row.fee"
                                class="text-meta whitespace-nowrap text-ink-soft"
                                >+
                                {{
                                    money(
                                        Math.abs(row.fee.amount),
                                        row.currency,
                                    )
                                }}</span
                            >
                        </span>
                    </div>
                    <span v-if="wide" class="flex justify-end">
                        <template v-if="can_sort">
                            <FmButton
                                v-if="row.ignored"
                                variant="neutral"
                                :icon="RotateCcw"
                                @click="toggleIgnored(row)"
                                >{{
                                    $t('Put back among the movements')
                                }}</FmButton
                            >
                            <span
                                v-else
                                class="flex flex-nowrap items-center gap-2"
                            >
                                <FmButton
                                    v-if="sortable(row) && row.orphan_fee"
                                    variant="neutral"
                                    row-action
                                    :icon="LinkIcon"
                                    @click="openFee(row)"
                                    >{{
                                        $t('It is the commission of…')
                                    }}</FmButton
                                >
                                <template v-else-if="sortable(row)">
                                    <FmButton
                                        variant="neutral"
                                        row-action
                                        :icon="Plus"
                                        :href="createUrl(row)"
                                        >{{
                                            row.amount > 0
                                                ? $t('Create the income')
                                                : $t('Create the expense')
                                        }}</FmButton
                                    >
                                    <FmButton
                                        variant="neutral"
                                        row-action
                                        :icon="Repeat"
                                        @click="openAssign(row)"
                                        >{{ assignLabel(row) }}</FmButton
                                    >
                                </template>
                                <span
                                    v-if="!row.pending"
                                    class="flex text-ink-soft [&_button]:text-ink-soft"
                                    ><FmMenu plain>
                                        <FmMenuItem
                                            v-if="sortable(row)"
                                            :icon="ArrowLeftRight"
                                            @select="openTransfer(row)"
                                            >{{
                                                $t('It is a transfer')
                                            }}</FmMenuItem
                                        >
                                        <FmMenuItem
                                            :icon="EyeOff"
                                            @select="toggleIgnored(row)"
                                            >{{ $t('Ignore') }}</FmMenuItem
                                        >
                                    </FmMenu></span
                                >
                            </span>
                        </template>
                    </span>
                    <Link
                        v-if="!wide && recurs(row)"
                        :href="recurUrl(row)"
                        :class="cn(recurClass, 'min-h-11')"
                    >
                        <Repeat
                            aria-hidden="true"
                            class="size-[18px] shrink-0"
                        />
                        <span class="grow">{{ recurLabel(row) }}</span>
                        <ChevronRight
                            aria-hidden="true"
                            class="size-[18px] shrink-0"
                        />
                    </Link>
                    <div
                        v-if="
                            !wide && can_sort && (row.ignored || !row.pending)
                        "
                        class="flex flex-wrap items-center gap-2"
                    >
                        <FmButton
                            v-if="row.ignored"
                            variant="neutral"
                            :icon="RotateCcw"
                            @click="toggleIgnored(row)"
                            >{{ $t('Put back among the movements') }}</FmButton
                        >
                        <template v-else>
                            <FmButton
                                v-if="sortable(row) && row.orphan_fee"
                                variant="neutral"
                                row-action
                                :icon="LinkIcon"
                                @click="openFee(row)"
                                >{{ $t('It is the commission of…') }}</FmButton
                            >
                            <template v-else-if="sortable(row)">
                                <FmButton
                                    variant="neutral"
                                    row-action
                                    :href="createUrl(row)"
                                    >{{
                                        row.amount > 0
                                            ? $t('Create the income')
                                            : $t('Create the expense')
                                    }}</FmButton
                                >
                                <FmButton
                                    variant="neutral"
                                    row-action
                                    @click="openAssign(row)"
                                    >{{ assignLabel(row) }}</FmButton
                                >
                            </template>
                            <span
                                v-if="!row.pending"
                                class="ml-auto flex text-ink-soft [&_button]:text-ink-soft"
                                ><FmMenu plain>
                                    <FmMenuItem
                                        v-if="sortable(row)"
                                        :icon="ArrowLeftRight"
                                        @select="openTransfer(row)"
                                        >{{
                                            $t('It is a transfer')
                                        }}</FmMenuItem
                                    >
                                    <FmMenuItem
                                        :icon="EyeOff"
                                        @select="toggleIgnored(row)"
                                        >{{ $t('Ignore') }}</FmMenuItem
                                    >
                                </FmMenu></span
                            >
                        </template>
                    </div>
                    <div
                        v-if="!wide && row.orphan_fee"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span :class="orphanTagClass">{{
                            $t('Commission')
                        }}</span>
                        <span class="text-meta text-ink-soft">{{
                            $t('Left without its direct debit')
                        }}</span>
                    </div>
                    <div
                        v-if="!wide && row.pending"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span
                            class="inline-flex self-start rounded-tag bg-warning-soft px-2 py-[3px] text-meta-strong whitespace-nowrap text-warning"
                            >{{ $t('Pending') }}</span
                        >
                        <span class="text-meta text-ink-soft">{{
                            $t('It is sorted when the bank books it')
                        }}</span>
                    </div>
                </li>
            </ul>
            <WhenVisible
                v-if="next"
                :key="next"
                always
                :params="{
                    data: nextData,
                    only: ['rows', 'next'],
                    preserveUrl: true,
                }"
            />
            <p class="m-0 px-1 text-meta text-ink-soft">
                {{
                    $t(
                        'Only the movements that no fixed expense recognises stay here. The recognised ones are marked paid by themselves.',
                    )
                }}
            </p>
        </template>

        <BankAssignSheet
            v-if="assigning"
            v-model:open="assignOpen"
            :transaction="assigning"
            :error="assignError"
            :processing="assignProcessing"
            @assign="assignTo"
        />
        <BankFeeSheet
            v-if="feeing"
            v-model:open="feeOpen"
            :transaction="feeing"
            :error="feeError"
            :processing="feeProcessing"
            @choose="attachFee"
        />
        <BankTransferSheet
            v-if="transferring"
            v-model:open="transferOpen"
            :transaction="transferring"
            :accounts="accounts_all ?? []"
            :error="transferError"
            :processing="transferProcessing"
            @choose="markTransfer"
        />
    </div>
</template>
