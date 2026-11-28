<script setup lang="ts">
/**
 * *Budget → From the bank*, a page of its own under the Budget (trail *Budget › From the bank*, no tabs of the
 * Budget), as the boards *Dalla banca* and *Dalla banca-pc* draw it: the transactions the bank gave that no fixed
 * expense recognised, newest first, 50 at a time and the next ones while scrolling. A pending transaction says so
 * and waits for the bank to book it. *To sort*, *All* and *Filters* are the three tabs inside the page, in the grey
 * bar of the boards. *Filters* lists the filters of the family (`BankFiltersList`) with *New filter*. *All* lists every transaction the bank gave with its tag and what it
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
import { Head, router, usePage, WhenVisible } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    CircleCheck,
    Funnel,
    Landmark,
    List,
    ListFilter,
    Plus,
    RefreshCw,
} from '@lucide/vue';
import { vAutoAnimate } from '@formkit/auto-animate/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { usePageMeta } from '@/composables/usePageMeta';
import FmButton from '@/components/fm/FmButton.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { Tab } from '@/components/fm/FmTabs.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import BankAllFilters from '@/components/budget/BankAllFilters.vue';
import BankAllList from '@/components/budget/BankAllList.vue';
import BankFiltersList from '@/components/budget/BankFiltersList.vue';
import BankAssignSheet from '@/components/budget/BankAssignSheet.vue';
import type { BankAssignChoice } from '@/lib/bankAssign';
import BankGroupCard from '@/components/budget/BankGroupCard.vue';
import BankGroupPanel from '@/components/budget/BankGroupPanel.vue';
import BankLoneRow from '@/components/budget/BankLoneRow.vue';
import BankProposalLine from '@/components/budget/BankProposalLine.vue';
import BankRawPanel from '@/components/budget/BankRawPanel.vue';
import BankFeeSheet from '@/components/budget/BankFeeSheet.vue';
import BankSimilarSheet from '@/components/budget/BankSimilarSheet.vue';
import BankTransferSheet from '@/components/budget/BankTransferSheet.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import { useBankFeed } from '@/composables/useBankFeed';
import { useWideLayout } from '@/composables/useWideLayout';
import { undoWords } from '@/lib/bankUndo';
import { lastReadText, readTimes, similarOf } from '@/lib/banks';
import { splitAround } from '@/lib/text';
import { cn } from '@/lib/utils';
import type { FlashSimilar } from '@/types/ui';
import {
    assign,
    fee as feeRoute,
    ignore,
    index as bankIndex,
    leave,
    restore,
    sync,
    transfer,
} from '@/routes/budget/bank';
import { create as createFilter } from '@/routes/budget/bank/filters';
import {
    accept as acceptRoute,
    reject as rejectRoute,
} from '@/routes/budget/bank/proposal';
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
    /** The tab *Filters*: the filters of the family, by name. */
    bank_filters?: App.Data.BankFilterData[];
    /** The tab *To sort*: the groups of movements that look alike, biggest first. */
    groups?: App.Data.BankGroupData[];
    /** The movements of the group asked with `?group=`, only when the page asks them (read from the answer of that request). */
    group_rows?: App.Data.BankGroupRowData[];
    /** The tab *To sort*: how many to sort, how many groups and how many lone movements (pending and in other currencies too: not a sum). */
    counts?: { unsorted: number; groups: number; lone: number };
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

/** How many groups show before *Show the other groups*, as the boards. */
const FIRST_GROUPS = 5;

/** The groups of the tab *To sort*, none on the other tabs. */
const groups = computed((): App.Data.BankGroupData[] => props.groups ?? []);

/** *Show the other groups* was tapped: every group shows. */
const allGroups = ref(false);

/** The groups shown now. */
const shownGroups = computed((): App.Data.BankGroupData[] =>
    allGroups.value ? groups.value : groups.value.slice(0, FIRST_GROUPS),
);

/** How many groups wait behind *Show the other groups*. */
const hiddenGroups = computed(
    (): number => groups.value.length - shownGroups.value.length,
);

/** The words of *Show the other groups*. */
const moreGroupsText = computed((): string =>
    transChoice(
        '{1} Show the other group|[2,*] Show the other :count groups',
        hiddenGroups.value,
    ),
);

/** The head of the groups: how many to sort and how many groups («268 to sort · 31 groups»). */
const groupsHead = computed((): string =>
    transChoice(
        '{1} :total to sort · :count group|[2,*] :total to sort · :count groups',
        props.counts?.groups ?? groups.value.length,
        { total: String(props.counts?.unsorted ?? unsorted.value) },
    ),
);

/**
 * How many lone movements there are. The server sends the number already lowered after a sort (the sorting actions
 * redirect back), so the rows gone from the page are not taken off again.
 */
const loneCount = computed(
    (): number => props.counts?.lone ?? shown.value.length,
);

/** How many lone movements are not on the page yet. */
const moreLone = computed((): number =>
    Math.max(0, loneCount.value - shown.value.length),
);

/** The look of the *Show…* buttons at the end of a list. */
const moreClass = cn(
    'flex min-h-13 w-full cursor-pointer items-center justify-center gap-2 border-0 bg-transparent text-label text-action hover:text-action-dark',
);

/** Loads the next block of lone movements. */
function loadMore(): void {
    router.reload({
        data: nextData.value,
        only: ['rows', 'next'],
        preserveUrl: true,
    });
}

/** The key of the group open, null while none is. */
const openKey = ref<string | null>(null);

/**
 * The movements of the group open, null while the page asks them. Kept here from the answer of the request of that
 * group: two groups opened quickly can answer in any order, and a later visit without `group_rows` does not empty
 * the panel.
 */
const openedRows = ref<App.Data.BankGroupRowData[] | null>(null);

/** The group open, null while none is (or it went from the list). */
const openedGroup = computed(
    (): App.Data.BankGroupData | null =>
        groups.value.find((group) => group.key === openKey.value) ?? null,
);

/** Wide with a group open: the groups become a list and the group goes beside it, as the boards *Dalla banca, il gruppo*. */
const beside = computed(
    (): boolean => wide.value && openedGroup.value !== null,
);

/**
 * Wide with a group open or the sheet of the similar movements: the groups are the list of one column and the lone
 * movements a row that brings them back, as the boards *Dalla banca, il gruppo* and *altri movimenti come questo*.
 */
const groupList = computed(
    (): boolean =>
        groups.value.length > 0 &&
        wide.value &&
        (openedGroup.value !== null || similar.value !== null),
);

/**
 * Opens a group and asks the server for its movements.
 *
 * @param group - the group
 */
function openGroup(group: App.Data.BankGroupData): void {
    // One panel beside the list at a time
    closePanels();
    openKey.value = group.key;
    lastKey = group.key;
    openedRows.value = null;
    router.reload({
        data: { group: group.key },
        only: ['group_rows'],
        preserveUrl: true,
        onSuccess: (page) => {
            // The answer of a group opened before the last one is dropped
            if (openKey.value === group.key) {
                openedRows.value =
                    (page.props.group_rows as
                        | App.Data.BankGroupRowData[]
                        | undefined) ?? [];
            }
        },
    });
}

/**
 * *It is not of this group* in the panel of a group: the movement leaves the group for good and becomes a lone
 * movement. The panel drops it at once (its rows come from the request of the group, which the visit does not
 * repeat); the groups, the numbers and the lone movements come back from the server.
 *
 * @param row - the movement
 */
function leaveGroup(row: App.Data.BankTransactionData): void {
    const key = openKey.value;

    if (key === null) {
        return;
    }

    router.post(
        leave(row.id).url,
        { group: key },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (openKey.value === key && openedRows.value !== null) {
                    openedRows.value = openedRows.value.filter(
                        (other) => other.transaction.id !== row.id,
                    );
                }
            },
        },
    );
}

/** The key of the group opened last, kept after it closes to give the focus back to its card. */
let lastKey: string | null = null;

/** The page, where the cards of the groups and the tabs are looked for when a panel gives the focus back. */
const pageRoot = ref<HTMLElement | null>(null);

/**
 * The button of the card of the group opened last: where the focus goes back when the group closes, as the card that
 * opened it leaves the page while the group is open (the groups become a list beside it).
 *
 * @returns the button, null when the group is not in the page any more
 */
function groupCard(): HTMLElement | null {
    const card = Array.from(
        pageRoot.value?.querySelectorAll<HTMLElement>('[data-group-key]') ?? [],
    ).find((element) => element.dataset.groupKey === lastKey);

    if (card === undefined) {
        return null;
    }

    return card.matches('button')
        ? card
        : card.querySelector<HTMLElement>('button');
}

/**
 * The tab open: where the focus goes back when the sheet of the similar movements closes, as what opened it (a
 * button of a panel, the row sorted) is gone.
 *
 * @returns the link of the tab open
 */
function currentTab(): HTMLElement | null {
    return (
        pageRoot.value?.querySelector<HTMLElement>('[aria-current="page"]') ??
        null
    );
}

/** Closes the group open. */
function closeGroup(): void {
    openKey.value = null;
    openedRows.value = null;
}

/** Closes every panel: one is open at a time, beside the list when wide. */
function closePanels(): void {
    closeGroup();
    similar.value = null;
    assigning.value = null;
    feeing.value = null;
    transferring.value = null;
    rawId.value = null;
}

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

/** What *Yes* and *No* ask back: the rows, the numbers and the shared number to sort. */
const PROPOSAL_ONLY = ['rows', 'counts', 'next', 'bank_feed'];

/** The movement *Yes* or *No* of «Maybe it is…» is being sent for, null while none is. */
const proposing = ref<number | null>(null);

/** The keys of the proposals turned down here (`id:key`): the line goes at once, the next candidate comes from the server. */
const turnedDown = ref<string[]>([]);

/**
 * Whether the line «Maybe it is…» shows for a row: it has a proposal, the person can sort and it was not turned down.
 *
 * @param row - the movement
 */
function proposes(row: App.Data.BankTransactionData): boolean {
    return (
        props.can_sort &&
        row.proposal !== null &&
        !turnedDown.value.includes(`${row.id}:${row.proposal.key}`)
    );
}

/**
 * *Yes* on «Maybe it is…»: the server links, pays or marks it (the message says which) and the row goes. The server
 * sends the numbers already lowered, so nothing is subtracted here.
 *
 * @param row - the movement
 */
function acceptProposal(row: App.Data.BankTransactionData): void {
    if (row.proposal === null || proposing.value !== null) {
        return;
    }

    router.post(
        acceptRoute(row.id).url,
        { key: row.proposal.key },
        {
            preserveScroll: true,
            only: PROPOSAL_ONLY,
            onStart: () => {
                proposing.value = row.id;
            },
            onSuccess: () => {
                gone.value = [...gone.value, row.id];
            },
            onError: (messages) => {
                // A key that is not the proposal any more: the next load brings the right one
                toast.error(
                    messages.key ??
                        Object.values(messages)[0] ??
                        trans('Something went wrong, try again.'),
                );
            },
            onFinish: () => {
                proposing.value = null;
            },
        },
    );
}

/**
 * *No* on «Maybe it is…»: the line goes and the server remembers, so the proposal does not come back.
 *
 * @param row - the movement
 */
function rejectProposal(row: App.Data.BankTransactionData): void {
    if (row.proposal === null || proposing.value !== null) {
        return;
    }

    const key = `${row.id}:${row.proposal.key}`;

    router.delete(rejectRoute(row.id).url, {
        data: { key: row.proposal.key },
        preserveScroll: true,
        only: PROPOSAL_ONLY,
        onStart: () => {
            proposing.value = row.id;
        },
        onSuccess: () => {
            turnedDown.value = [...turnedDown.value, key];
        },
        onFinish: () => {
            proposing.value = null;
        },
    });
}

/** What the server said about the movements like the one just sorted by hand; the sheet is open while it is there. */
const similar = ref<FlashSimilar | null>(null);

/** The sheet *I found… more movements like this* is open. */
const similarOpen = computed({
    get: () => similar.value !== null,
    set: (value: boolean) => {
        if (!value) {
            similar.value = null;
        }
    },
});

/** The last flash `similar` offered: the same one brought again (the page opened with it, then its visit) is not offered twice. */
let lastOffered: FlashSimilar | null = null;

/**
 * Opens the sheet when a visit brings the flash `similar` (after a movement is linked or marked as a transfer here),
 * closing the group open: one panel beside the list at a time.
 *
 * @param flash - the flash of the visit
 */
function offerSimilar(flash: unknown): void {
    const found: FlashSimilar | null = similarOf(flash);

    if (found === null || found === lastOffered) {
        return;
    }

    lastOffered = found;
    closePanels();
    similar.value = found;
}

// The expense made from the form of a movement lands here with the flash: it arrives once, so *Back* does not reopen it
onMounted(() => offerSimilar(usePage().flash));

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
 * Opens the panel for a transaction; the panel asks the server for what it can belong to.
 *
 * @param row - the transaction
 */
function openAssign(row: App.Data.BankTransactionData): void {
    closePanels();
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
        onFlash: offerSimilar,
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
    closePanels();
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
            onFlash: offerSimilar,
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
    closePanels();
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

/** The line under the filters, cut around *It was not this one*, to draw it in italics. */
const filtersFooter = computed((): [string, string] =>
    splitAround(
        'Filters work on the new movements and, when you save them, on the ones still to sort. An expense created by a filter goes away with :undo.',
        'undo',
    ),
);

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

/**
 * Opens the transaction as the bank gave it.
 *
 * @param row - the transaction
 */
function openRaw(row: App.Data.BankTransactionData): void {
    closePanels();
    rawId.value = row.id;
}

/** A panel that goes in the column at the right of the page when wide is open (all but the group's). */
const sideOpen = computed(
    (): boolean =>
        similar.value !== null ||
        assigning.value !== null ||
        feeing.value !== null ||
        transferring.value !== null ||
        (props.show === 'all' && rawRow.value !== null),
);

/** The movement a panel is open for, marked in its list. */
const markedId = computed(
    (): number | null =>
        assigning.value?.id ??
        feeing.value?.id ??
        transferring.value?.id ??
        null,
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

    <div ref="pageRoot" class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle :title="title" />

        <div class="flex items-start gap-5">
            <div class="flex min-w-0 flex-1 flex-col gap-4">
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

                <div
                    class="flex flex-col gap-2.5 wide:flex-row wide:flex-wrap wide:items-center"
                >
                    <FmTabs
                        variant="pill"
                        :tabs="filters"
                        :label="$t('Movements from the bank')"
                        class="wide:w-115 wide:max-w-full"
                    />
                    <!-- On the phone the last read and Update share a line; wide, Update follows the tabs and the
                         last read goes under them (on the row of the tabs under All on the computer, with room) -->
                    <div class="flex items-center gap-3 wide:contents">
                        <span
                            :class="
                                cn(
                                    'min-w-0 flex-1 text-meta text-ink-soft wide:order-last wide:basis-full',
                                    show === 'all' &&
                                        !sideOpen &&
                                        'computer:order-0 computer:min-w-80 computer:basis-0',
                                )
                            "
                            >{{ readLine }}</span
                        >
                        <FmButton
                            variant="neutral"
                            :icon="RefreshCw"
                            :disabled="updating"
                            @click="update"
                            >{{ $t('Update') }}</FmButton
                        >
                    </div>
                </div>

                <template v-if="show === 'filters'">
                    <FmEmpty
                        v-if="(bank_filters ?? []).length === 0"
                        :icon="Funnel"
                        :title="$t('No filter yet')"
                        :text="
                            $t(
                                'A filter sorts by itself the movements that look alike, the ones that arrive and the ones already here.',
                            )
                        "
                    >
                        <FmButton :icon="Plus" :href="createFilter().url">{{
                            $t('New filter')
                        }}</FmButton>
                    </FmEmpty>
                    <template v-else>
                        <div
                            class="flex flex-col gap-4 wide:flex-row wide:flex-wrap wide:items-center wide:justify-between"
                        >
                            <p
                                class="m-0 px-1 text-body-sm text-ink-soft wide:max-w-160 wide:px-0"
                            >
                                {{
                                    $t(
                                        'Filters recognise the movements of the bank and sort them by themselves, at every read. A movement is taken by one filter only.',
                                    )
                                }}
                            </p>
                            <FmButton
                                :icon="Plus"
                                :href="createFilter().url"
                                :wide="!wide"
                                >{{ $t('New filter') }}</FmButton
                            >
                        </div>
                        <BankFiltersList :filters="bank_filters ?? []" />
                        <p class="m-0 px-1 text-meta text-ink-soft">
                            {{ filtersFooter[0]
                            }}<em>{{ $t('It was not this one') }}</em
                            >{{ filtersFooter[1] }}
                        </p>
                    </template>
                </template>

                <template v-else-if="show === 'all'">
                    <BankAllFilters
                        :filters="allFilters"
                        :accounts="accounts ?? []"
                        :total="total ?? { count: shown.length, sum: 0 }"
                    />
                    <div class="flex min-w-0 flex-col gap-4">
                        <BankAllList
                            v-if="shown.length > 0"
                            :rows="shown"
                            :can-sort="can_sort"
                            :selected="rawRow?.id ?? null"
                            :compact="wide && rawRow !== null"
                            @open="openRaw"
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
                                    ? $t(
                                          'Remove a filter or change the search.',
                                      )
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
                    <FmConfirm
                        v-model:open="undoOpen"
                        :question="undoText.question"
                        :text="undoText.text"
                        :confirm-label="$t('It was not this one')"
                        @confirm="takeLinkBack"
                    />
                </template>

                <FmEmpty
                    v-else-if="shown.length === 0 && groups.length === 0"
                    :icon="CircleCheck"
                    tone="paid"
                    :title="$t('Nothing left to sort')"
                    :text="
                        $t(
                            'The filters and the fixed expenses with the rule sort by themselves the movements that arrive. Here you find only the ones nobody recognises.',
                        )
                    "
                >
                    <FmButton
                        variant="neutral"
                        row-action
                        :icon="List"
                        :href="bankIndex({ query: { show: 'all' } }).url"
                        >{{ $t('All the movements') }}</FmButton
                    >
                    <FmButton
                        variant="neutral"
                        row-action
                        :icon="ListFilter"
                        :href="bankIndex({ query: { show: 'filters' } }).url"
                        >{{ $t('Your filters') }}</FmButton
                    >
                </FmEmpty>

                <template v-else>
                    <div
                        :class="
                            beside
                                ? 'grid grid-cols-[360px_minmax(0,1fr)] items-start gap-6 computer:grid-cols-[400px_minmax(0,1fr)]'
                                : 'flex flex-col gap-6'
                        "
                    >
                        <section
                            v-if="groups.length > 0"
                            :aria-label="$t('Groups')"
                            class="flex min-w-0 flex-col gap-3"
                        >
                            <div class="flex flex-col gap-0.5 px-1">
                                <h2 class="m-0 text-section text-ink">
                                    {{ groupsHead }}
                                </h2>
                                <span
                                    v-if="!groupList"
                                    class="text-meta text-ink-soft"
                                    >{{
                                        $t(
                                            'Movements that look alike stay together: create the filter of a group and they are all sorted, the ones to come too.',
                                        )
                                    }}</span
                                >
                            </div>
                            <nav
                                v-if="groupList"
                                v-auto-animate
                                :aria-label="$t('Groups')"
                                class="flex flex-col divide-y divide-divider overflow-hidden rounded-card bg-card shadow-card"
                            >
                                <BankGroupCard
                                    v-for="group in shownGroups"
                                    :key="group.key"
                                    :group="group"
                                    variant="row"
                                    :data-group-key="group.key"
                                    :selected="group.key === openKey"
                                    @open="openGroup(group)"
                                />
                                <button
                                    v-if="hiddenGroups > 0"
                                    type="button"
                                    :class="moreClass"
                                    @click="allGroups = true"
                                >
                                    {{ moreGroupsText
                                    }}<ChevronDown
                                        aria-hidden="true"
                                        class="size-[18px]"
                                    />
                                </button>
                            </nav>
                            <template v-else>
                                <div
                                    v-auto-animate
                                    :class="
                                        cn(
                                            'grid grid-cols-1 gap-3',
                                            // Beside a panel the column is too narrow for two cards
                                            !sideOpen && 'wide:grid-cols-2',
                                        )
                                    "
                                >
                                    <BankGroupCard
                                        v-for="group in shownGroups"
                                        :key="group.key"
                                        :group="group"
                                        :data-group-key="group.key"
                                        @open="openGroup(group)"
                                    />
                                </div>
                                <button
                                    v-if="hiddenGroups > 0"
                                    type="button"
                                    :class="
                                        cn(
                                            moreClass,
                                            'rounded-card bg-card shadow-card',
                                        )
                                    "
                                    @click="allGroups = true"
                                >
                                    {{ moreGroupsText
                                    }}<ChevronDown
                                        aria-hidden="true"
                                        class="size-[18px]"
                                    />
                                </button>
                            </template>
                            <button
                                v-if="groupList && loneCount > 0"
                                type="button"
                                class="flex cursor-pointer items-center gap-3 rounded-card border-0 bg-card px-4 py-3.5 text-left text-ink shadow-card"
                                @click="closePanels"
                            >
                                <span class="flex grow flex-col gap-0.5">
                                    <span class="text-body-strong">{{
                                        $t('Lone movements · :count', {
                                            count: String(loneCount),
                                        })
                                    }}</span>
                                    <span class="text-meta text-ink-soft">{{
                                        $t('The ones that are in no group')
                                    }}</span>
                                </span>
                                <ChevronRight
                                    aria-hidden="true"
                                    class="size-5 shrink-0 text-ink-soft"
                                />
                            </button>
                        </section>

                        <div
                            v-if="beside && openedGroup"
                            class="sticky top-[calc(var(--app-header,0px)+--spacing(4))] min-w-0"
                        >
                            <BankGroupPanel
                                :group="openedGroup"
                                :rows="openedRows"
                                :return-focus="groupCard"
                                @close="closeGroup"
                                @not-of-group="leaveGroup"
                            />
                        </div>

                        <section
                            v-if="!groupList && shown.length > 0"
                            :aria-label="$t('Lone movements')"
                            class="flex flex-col gap-3"
                        >
                            <div class="flex flex-col gap-0.5 px-1">
                                <h2 class="m-0 text-section text-ink">
                                    {{
                                        $t('Lone movements · :count', {
                                            count: String(loneCount),
                                        })
                                    }}
                                </h2>
                                <span class="text-meta text-ink-soft">{{
                                    $t(
                                        'The ones that are in no group. Where it finds an expense that fits, it proposes it to you.',
                                    )
                                }}</span>
                            </div>
                            <div
                                class="flex flex-col overflow-hidden rounded-card bg-card shadow-card"
                            >
                                <ul
                                    v-auto-animate
                                    class="m-0 flex list-none flex-col divide-y divide-divider p-0"
                                >
                                    <BankLoneRow
                                        v-for="row in shown"
                                        :key="row.id"
                                        :row="row"
                                        :can-sort="can_sort"
                                        :can-plan="can_plan"
                                        :selected="wide && row.id === markedId"
                                        :compact="wide && sideOpen"
                                        @assign="openAssign"
                                        @transfer="openTransfer"
                                        @fee="openFee"
                                        @ignore="toggleIgnored"
                                    >
                                        <template
                                            v-if="proposes(row)"
                                            #proposal
                                        >
                                            <BankProposalLine
                                                :row="row"
                                                :busy="proposing !== null"
                                                @yes="acceptProposal"
                                                @no="rejectProposal"
                                            />
                                        </template>
                                    </BankLoneRow>
                                </ul>
                                <button
                                    v-if="next && moreLone > 0"
                                    type="button"
                                    :class="
                                        cn(moreClass, 'border-t border-divider')
                                    "
                                    @click="loadMore"
                                >
                                    {{
                                        $tChoice(
                                            '{1} Show one more movement|[2,*] Show :count more movements',
                                            moreLone,
                                        )
                                    }}<ChevronDown
                                        aria-hidden="true"
                                        class="size-[18px]"
                                    />
                                </button>
                            </div>
                            <p class="m-0 px-1 text-meta text-ink-soft">
                                {{
                                    $t(
                                        'Only the movements no filter takes stay here. The ones a filter takes, or a fixed expense with the rule, are sorted by themselves.',
                                    )
                                }}
                            </p>
                        </section>
                    </div>
                    <BankGroupPanel
                        v-if="!wide && openedGroup"
                        :group="openedGroup"
                        :rows="openedRows"
                        @close="closeGroup"
                        @not-of-group="leaveGroup"
                    />
                </template>
            </div>

            <!-- The panels that follow an action: wide in the column at the right of the page, on the phone sheets -->
            <div
                v-if="sideOpen"
                :class="
                    wide
                        ? 'sticky top-[calc(var(--app-header,0px)+--spacing(4))] w-[400px] shrink-0'
                        : 'contents'
                "
            >
                <BankSimilarSheet
                    v-if="similar"
                    v-model:open="similarOpen"
                    :similar="similar"
                    :return-focus="currentTab"
                />
                <BankAssignSheet
                    v-if="assigning"
                    v-model:open="assignOpen"
                    :transaction="assigning"
                    :error="assignError"
                    :processing="assignProcessing"
                    :return-focus="currentTab"
                    @assign="assignTo"
                />
                <BankFeeSheet
                    v-if="feeing"
                    v-model:open="feeOpen"
                    :transaction="feeing"
                    :error="feeError"
                    :processing="feeProcessing"
                    :return-focus="currentTab"
                    @choose="attachFee"
                />
                <BankTransferSheet
                    v-if="transferring"
                    v-model:open="transferOpen"
                    :transaction="transferring"
                    :accounts="accounts_all ?? []"
                    :error="transferError"
                    :processing="transferProcessing"
                    :return-focus="currentTab"
                    @choose="markTransfer"
                />
                <BankRawPanel
                    v-if="show === 'all' && rawRow"
                    :row="rawRow"
                    :can-sort="can_sort"
                    :return-focus="currentTab"
                    @close="rawId = null"
                    @undo="(row) => (undoing = row)"
                />
            </div>
        </div>
    </div>
</template>
