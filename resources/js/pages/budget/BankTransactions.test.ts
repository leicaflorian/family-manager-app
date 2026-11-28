import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { nextTick } from 'vue';
import { toast } from 'vue-sonner';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    bankFilters,
    bankGroupRows,
    bankGroups,
    bankProposals,
    bankTransactions,
} from '@/test/fixtures';
import { renderPage, screenWidth, settle, withTrail } from '@/test/page';
import type { FlashSimilar } from '@/types/ui';
import BankTransactions from './BankTransactions.vue';

vi.mock('vue-sonner', () => ({ toast: { error: vi.fn() } }));

const shared = vi.hoisted(() => ({
    bank_feed: { unsorted: 6 } as { unsorted: number } | null,
    flash: {} as Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        url: '/budget/bank',
        props: { name: 'Fameja.app', bank_feed: shared.bank_feed },
        flash: shared.flash,
    }),
}));

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-20T14:30:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    shared.bank_feed = { unsorted: 6 };
    shared.flash = {};
});

/**
 * Finds `fetch` with a server that answers the search of the sheet.
 *
 * @param answer - the two lists of the answer
 */
function stubSearch(answer: {
    planned: App.Data.BankPlannedChoiceData[];
    recorded: App.Data.BankRecordedChoiceData[];
}): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve(answer) }),
        ),
    );
}

/** The props of the page as the controller sends them. */
function props(extra: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        show: 'unsorted',
        rows: bankTransactions,
        next: null,
        banks_to_reconnect: [],
        can_sort: true,
        can_plan: true,
        can_change_banks: true,
        last_synced_at: '2026-11-20T13:00',
        sync_hours: [7, 13, 19],
        groups: [],
        counts: { unsorted: 3, groups: 0, lone: 3 },
        ...extra,
    };
}

/** The props of the tab *All* as the controller sends them, with the rows and the filters given. */
function allProps(
    extra: Record<string, unknown> = {},
): Record<string, unknown> {
    return props({
        show: 'all',
        filters: {
            q: null,
            account: null,
            status: null,
            month: null,
            direction: null,
        },
        total: { count: 94, sum: -1842.3 },
        accounts: [{ id: 4, name: 'Conto BancoPosta ·· 3307' }],
        ...extra,
    });
}

describe('Budget, From the bank', () => {
    it('a direct debit shows its commission under it and both amounts', () => {
        const debit = {
            ...bankTransactions[1],
            id: 40,
            text: 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. MAN.SAT0023101207360112900',
            amount: -10,
            booked_on: '2026-11-24',
            value_on: '2026-11-24',
            fee: { id: 41, amount: -0.4, booked_on: '2026-11-25' },
        };
        renderPage(BankTransactions, props({ rows: [debit] }));

        const row = screen.getByText(/Satispay Europe/).closest('li')!;
        expect(
            within(row).getByText('+ 0,40 € commission, 25/11'),
        ).toBeInTheDocument();
        expect(within(row).getByText('-10,00 €')).toBeInTheDocument();
        expect(within(row).getByText('+ 0,40 €')).toBeInTheDocument();
    });

    it('lists the transactions with the day, the account and the amount', () => {
        renderPage(BankTransactions, props());

        const row = screen
            .getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            )
            .closest('li')!;
        expect(
            within(row).getByText('05/11 · Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
        expect(within(row).getByText('-72,40 €')).toBeInTheDocument();
    });

    it('marks a transaction the bank has not booked yet, with the day it gave', () => {
        renderPage(BankTransactions, props());

        const row = screen
            .getByText('PAGAMENTO POS Q8 STAZIONE SERVIZIO VIA EMILIA BOLOGNA')
            .closest('li')!;
        expect(within(row).getByText('Pending')).toBeInTheDocument();
        expect(
            within(row).getByText('It is sorted when the bank books it'),
        ).toBeInTheDocument();
        expect(
            within(row).getByText('06/11 · Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
        expect(
            within(
                screen.getByText(/EDISON ENERGIA/).closest('li')!,
            ).queryByText('Pending'),
        ).not.toBeInTheDocument();
    });

    it('writes an amount in another currency with its code', () => {
        renderPage(BankTransactions, props());

        expect(
            within(
                screen.getByText('CARD PAYMENT NEW YORK HOTEL').closest('li')!,
            ).getByText('-120,00 USD'),
        ).toBeInTheDocument();
    });

    it('writes how the page is updated and when it was last', () => {
        renderPage(BankTransactions, props());

        expect(
            screen.getByText(
                'Last update today at 13:00 · they arrive by themselves at 7, at 13, and at 19',
            ),
        ).toBeInTheDocument();

        renderPage(BankTransactions, props({ last_synced_at: null }));
        expect(
            screen.getByText(
                'They arrive by themselves at 7, at 13, and at 19',
            ),
        ).toBeInTheDocument();
    });

    it('the title is From the bank and the back link leads to the budget', () => {
        const { state, provide } = withTrail();
        renderPage(BankTransactions, props(), provide);

        expect(state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'From the bank' },
        ]);
        expect(
            screen.getByRole('heading', { level: 1, name: 'From the bank' }),
        ).toBeInTheDocument();
        // It is a page of its own: the tabs of the budget are not here
        expect(
            screen.queryByRole('navigation', { name: 'Budget' }),
        ).not.toBeInTheDocument();
    });

    it('has the tabs To sort, All and Filters, the number on the first and the open one marked', () => {
        renderPage(BankTransactions, props());

        const tabs = screen.getByRole('navigation', {
            name: 'Movements from the bank',
        });
        expect(tabs).toHaveClass('bg-segment');
        expect(
            within(tabs)
                .getAllByRole('link')
                .map((link) => link.textContent?.trim()),
        ).toEqual(['To sort · 6', 'All', 'Filters']);
        expect(
            within(tabs).getByRole('link', { name: 'To sort · 6' }),
        ).toHaveAttribute('href', '/budget/bank');
        expect(
            within(tabs).getByRole('link', { name: 'To sort · 6' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(within(tabs).getByRole('link', { name: 'All' })).toHaveAttribute(
            'href',
            '/budget/bank?show=all',
        );
        expect(
            within(tabs).getByRole('link', { name: 'Filters' }),
        ).toHaveAttribute('href', '/budget/bank?show=filters');
        expect(
            within(tabs).getByRole('link', { name: 'All' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('marks the tab that is open and shows no number when nothing is to sort', () => {
        shared.bank_feed = { unsorted: 0 };
        renderPage(BankTransactions, props({ show: 'filters', rows: [] }));

        const tabs = screen.getByRole('navigation', {
            name: 'Movements from the bank',
        });
        expect(
            within(tabs).getByRole('link', { name: 'Left to sort' }),
        ).not.toHaveAttribute('aria-current');
        expect(
            within(tabs).getByRole('link', { name: 'Filters' }),
        ).toHaveAttribute('aria-current', 'page');
    });

    it('the empty page says the filters sort by themselves', () => {
        renderPage(BankTransactions, props({ rows: [] }));

        expect(screen.getByText('Nothing left to sort')).toBeInTheDocument();
        expect(
            screen.getByText(
                'The filters and the fixed expenses with the rule sort by themselves the movements that arrive. Here you find only the ones nobody recognises.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'All the movements' }),
        ).toHaveAttribute('href', '/budget/bank?show=all');
        expect(
            screen.getByRole('link', { name: 'Your filters' }),
        ).toHaveAttribute('href', '/budget/bank?show=filters');
    });

    it('under Filters says there is no filter yet, with New filter', () => {
        renderPage(BankTransactions, props({ show: 'filters', rows: [] }));

        expect(screen.getByText('No filter yet')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'New filter' }),
        ).toHaveAttribute('href', '/budget/bank/filters/create');
    });

    it('under Filters lists the filters, with what they do and New filter', () => {
        renderPage(
            BankTransactions,
            props({ show: 'filters', rows: [], bank_filters: bankFilters }),
        );

        expect(screen.queryByText('No filter yet')).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'Filters recognise the movements of the bank and sort them by themselves, at every read. A movement is taken by one filter only.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'New filter' }),
        ).toHaveAttribute('href', '/budget/bank/filters/create');
        expect(
            screen.getByText('Satispay', { selector: '[data-filter-name]' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Luce', { selector: '[data-filter-name]' }),
        ).toBeInTheDocument();
        expect(screen.getByText('It was not this one').tagName).toBe('EM');
    });

    it('under All lists every transaction with its tag, the filters and the last line', () => {
        renderPage(
            BankTransactions,
            allProps({
                rows: [
                    {
                        ...bankTransactions[1]!,
                        ignored: true,
                        status: 'ignored',
                    },
                    bankTransactions[0],
                ],
            }),
        );

        expect(screen.getByText(/EDISON ENERGIA/)).toBeInTheDocument();
        expect(screen.getByText('Set aside')).toBeInTheDocument();
        expect(
            within(
                screen.getByText(bankTransactions[0]!.text).closest('li')!,
            ).getByText('Pending at the bank'),
        ).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent('94 movements');
        expect(
            screen.getByText(
                'Movements not linked to an expense are kept 12 months.',
            ),
        ).toBeInTheDocument();
    });

    it('under All says nothing is there yet, or that no movement matches the filters', () => {
        const view = renderPage(BankTransactions, allProps({ rows: [] }));
        expect(screen.getByText('No movement yet')).toBeInTheDocument();
        view.unmount();

        renderPage(
            BankTransactions,
            allProps({
                rows: [],
                filters: {
                    q: 'zzz',
                    account: null,
                    status: null,
                    month: null,
                    direction: null,
                },
            }),
        );
        expect(
            screen.getByText('No movement matches the filters'),
        ).toBeInTheDocument();
    });

    it('opens the transaction as the bank gave it from its text and closes it', async () => {
        renderPage(
            BankTransactions,
            allProps({ rows: [bankTransactions[1]!] }),
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: 'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            }),
        );
        const panel = await screen.findByRole('dialog', {
            name: 'Bank transaction',
        });
        expect(within(panel).getByText('tx-2')).toBeInTheDocument();

        await userEvent.click(
            within(panel).getByRole('button', { name: 'Close' }),
        );
        expect(
            screen.queryByRole('dialog', { name: 'Bank transaction' }),
        ).not.toBeInTheDocument();
    });

    it('puts the panel beside the list when wide', async () => {
        screenWidth(1280);
        renderPage(
            BankTransactions,
            allProps({ rows: [bankTransactions[1]!] }),
        );

        await userEvent.click(
            screen.getByRole('button', { name: /ADDEBITO DIRETTO SDD EDISON/ }),
        );

        expect(
            screen
                .getByRole('dialog', { name: 'Bank transaction' })
                .closest('li'),
        ).toBeNull();
        expect(screen.getByText('tx-2')).toBeInTheDocument();
    });

    it('takes a link back after asking, and the row goes under a status filter', async () => {
        const del = vi
            .spyOn(router, 'delete')
            .mockImplementation((_url, options) => {
                options?.onSuccess?.({} as never);
                options?.onFinish?.({} as never);
            });
        const linked = {
            ...bankTransactions[1]!,
            status: 'linked',
            link: {
                kind: 'expense',
                id: 7,
                title: 'Luce',
                url: '/budget/movements/expense/7',
                how: 'rule',
                filter_id: null,
                filter_name: null,
                undo: 'delete',
                undo_url: '/budget/bank/2/payment',
            },
        };
        renderPage(
            BankTransactions,
            allProps({
                rows: [linked],
                filters: {
                    q: null,
                    account: null,
                    status: 'linked',
                    month: null,
                    direction: null,
                },
            }),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'It was not this one' }),
        );
        expect(del).not.toHaveBeenCalled();
        await userEvent.click(
            within(await screen.findByRole('dialog')).getByRole('button', {
                name: 'It was not this one',
            }),
        );

        expect(del).toHaveBeenCalledWith(
            '/budget/bank/2/payment',
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.getByText('No movement matches the filters'),
        ).toBeInTheDocument();
    });

    it('says in the question that a link to an expense already there only comes off', async () => {
        const linked = {
            ...bankTransactions[1]!,
            status: 'linked',
            link: {
                kind: 'expense',
                id: 7,
                title: 'Luce',
                url: '/budget/movements/expense/7',
                how: 'duplicate',
                filter_id: null,
                filter_name: null,
                undo: 'detach',
                undo_url: '/budget/bank/2/payment',
            },
        };
        renderPage(BankTransactions, allProps({ rows: [linked] }));

        await userEvent.click(
            screen.getByRole('button', { name: 'It was not this one' }),
        );
        const dialog = await screen.findByRole('dialog');

        expect(
            within(dialog).getByText('Take off this link?'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                'The expense stays as it is. The movement goes back to be sorted.',
            ),
        ).toBeInTheDocument();
    });

    it('tells a bank to connect again and leads the one who can to the banks', () => {
        renderPage(
            BankTransactions,
            props({ banks_to_reconnect: ['BancoPosta'] }),
        );

        expect(
            screen.getByText('BancoPosta needs to be connected again'),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Reconnect' })).toHaveAttribute(
            'href',
            '/settings/banks',
        );
    });

    it('gives a member the strip without the button', () => {
        renderPage(
            BankTransactions,
            props({
                banks_to_reconnect: ['BancoPosta'],
                can_change_banks: false,
            }),
        );

        expect(
            screen.getByText('BancoPosta needs to be connected again'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Reconnect' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'Only who manages the family connects and disconnects the banks.',
            ),
        ).toBeInTheDocument();
    });

    it('updates the banks, once while the update runs', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onStart?.({} as never);
            });
        renderPage(BankTransactions, props());

        const button = screen.getByRole('button', { name: 'Update' });
        await userEvent.click(button);
        await userEvent.click(button);

        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith(
            '/budget/bank/sync',
            {},
            expect.anything(),
        );
        expect(button).toBeDisabled();

        post.mock.calls[0]![2]!.onFinish?.({} as never);
        await vi.waitFor(() => expect(button).toBeEnabled());
    });

    it('keeps the day inside the line of the account on the phone', () => {
        renderPage(BankTransactions, props());

        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;
        expect(row).not.toHaveAttribute('data-wide');
        expect(
            within(row).getByText('05/11 · Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
    });

    it('puts the day in its own column on the wide layout, the account under the text', () => {
        screenWidth(true);
        renderPage(BankTransactions, props());

        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;
        expect(row).toHaveAttribute('data-wide');
        expect(within(row).getByText('05/11')).toBeInTheDocument();
        expect(
            within(row).getByText('Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
        expect(within(row).getByText('-72,40 €')).toBeInTheDocument();
        // A pending transaction keeps its mark and the day the bank gave
        const pending = screen.getByText(/Q8 STAZIONE/).closest('li')!;
        expect(within(pending).getByText('06/11')).toBeInTheDocument();
        expect(within(pending).getByText('Pending')).toBeInTheDocument();
    });
});

describe('Budget, From the bank, the menu of a row', () => {
    it('the menu of a row has It is a transfer next to Ignore', async () => {
        renderPage(BankTransactions, props());
        const row = screen
            .getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            )
            .closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', { name: 'More actions' }),
        );

        const items = screen.getAllByRole('menuitem');
        expect(items.map((item) => item.textContent?.trim())).toEqual([
            'It is a transfer',
            'Ignore',
        ]);
    });

    it('opens the sheet for the row and posts the account chosen', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(
            BankTransactions,
            props({
                accounts_all: [
                    {
                        id: 4,
                        name: 'Conto BancoPosta ·· 3307',
                        connected: true,
                    },
                    { id: 6, name: 'Buddy Bank ·· 5521', connected: true },
                ],
            }),
        );
        const row = screen
            .getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            )
            .closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(
            screen.getByRole('menuitem', { name: 'It is a transfer' }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Mark as a transfer' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/budget/bank/2/transfer',
            { account_id: 6 },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.queryByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            ),
        ).not.toBeInTheDocument();
    });
});

describe('Budget, From the bank, ignoring', () => {
    it('ignores a transaction and the row goes', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(BankTransactions, props());
        const row = screen
            .getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            )
            .closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(screen.getByRole('menuitem', { name: 'Ignore' }));

        expect(post).toHaveBeenCalledWith(
            '/budget/bank/2/ignore',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.queryByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            ),
        ).not.toBeInTheDocument();
    });

    it('puts an ignored transaction back among the movements', async () => {
        const del = vi
            .spyOn(router, 'delete')
            .mockImplementation((_url, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(
            BankTransactions,
            allProps({
                rows: [
                    {
                        ...bankTransactions[1]!,
                        ignored: true,
                        status: 'ignored',
                    },
                ],
                filters: {
                    q: null,
                    account: null,
                    status: 'ignored',
                    month: null,
                    direction: null,
                },
            }),
        );

        expect(
            screen.queryByRole('button', { name: 'Ignore' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Put back among the movements',
            }),
        );

        expect(del).toHaveBeenCalledWith(
            '/budget/bank/2/ignore',
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.getByText('No movement matches the filters'),
        ).toBeInTheDocument();
    });

    it('offers no action to who cannot sort', () => {
        renderPage(BankTransactions, props({ can_sort: false }));

        expect(
            screen.queryByRole('button', { name: 'More actions' }),
        ).not.toBeInTheDocument();
    });
});

describe('Budget, From the bank, sorting', () => {
    const credit: App.Data.BankTransactionData = {
        ...bankTransactions[1]!,
        id: 9,
        text: 'BONIFICO A VOSTRO FAVORE DA ROSSI GIULIA',
        amount: 35,
    };

    it('offers Create the expense and the planned expense to a booked payment', () => {
        renderPage(BankTransactions, props());
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        expect(
            within(row).getByRole('link', { name: 'Create the expense' }),
        ).toHaveAttribute('href', '/budget/movements/create?bank=2');
        expect(
            within(row).getByRole('button', {
                name: 'It belongs to an expense',
            }),
        ).toBeInTheDocument();
    });

    it('draws the actions of a row on the phone at their own width, in the small text', () => {
        renderPage(BankTransactions, props());
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        for (const action of [
            within(row).getByRole('link', { name: 'Create the expense' }),
            within(row).getByRole('button', {
                name: 'It belongs to an expense',
            }),
        ]) {
            expect(action).toHaveClass('text-body-sm', 'px-2.5', 'rounded-md');
            expect(action).not.toHaveClass('flex-1', 'text-label');
        }
    });

    it('offers Create the income and the planned income to a credit', () => {
        renderPage(BankTransactions, props({ rows: [credit] }));
        const row = screen.getByText(/ROSSI GIULIA/).closest('li')!;

        expect(
            within(row).getByRole('link', { name: 'Create the income' }),
        ).toHaveAttribute('href', '/budget/movements/create?bank=9');
        expect(
            within(row).getByRole('button', {
                name: 'It belongs to an income',
            }),
        ).toBeInTheDocument();
    });

    it('writes the year only for a day that is not of this year', () => {
        renderPage(
            BankTransactions,
            props({
                rows: [{ ...bankTransactions[1]!, booked_on: '2025-12-31' }],
            }),
        );

        expect(
            screen.getByText('31/12/2025 · Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
    });

    it('does not offer to ignore a pending transaction, in either layout', () => {
        renderPage(BankTransactions, props());
        const pending = screen.getByText(/Q8 STAZIONE/).closest('li')!;

        expect(
            within(pending).queryByRole('button', { name: 'More actions' }),
        ).not.toBeInTheDocument();
    });

    it('offers nothing to a pending transaction, one in another currency, one put aside or to who cannot sort', () => {
        renderPage(
            BankTransactions,
            props({ rows: [bankTransactions[0]!, bankTransactions[2]!] }),
        );
        expect(
            screen.queryByRole('link', { name: /Create the/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /It belongs to/ }),
        ).not.toBeInTheDocument();
    });

    it('offers nothing under Ignored or to who cannot sort', () => {
        const view = renderPage(
            BankTransactions,
            allProps({
                rows: [
                    {
                        ...bankTransactions[1]!,
                        ignored: true,
                        status: 'ignored',
                    },
                ],
            }),
        );
        expect(
            screen.queryByRole('link', { name: /Create the/ }),
        ).not.toBeInTheDocument();
        view.unmount();

        renderPage(BankTransactions, props({ can_sort: false }));
        expect(
            screen.queryByRole('link', { name: /Create the/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /It belongs to/ }),
        ).not.toBeInTheDocument();
    });

    it('puts the actions in the column of the row on the wide layout', () => {
        screenWidth(1280);
        renderPage(BankTransactions, props());
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        // As the board *Dalla banca-pc*: the buttons of a row, and the ⋯ menu (It is a transfer, Ignore) grey without an edge
        expect(
            within(row).getByRole('link', { name: 'Create the expense' }),
        ).toHaveClass('text-body-sm', 'border-edge-soft');
        expect(
            within(row).getByRole('button', {
                name: 'It belongs to an expense',
            }),
        ).toHaveClass('text-body-sm', 'border-edge-soft');
        const menuButton = within(row).getByRole('button', {
            name: 'More actions',
        });
        // As the board: transparent, 44 px, ink-soft
        expect(menuButton.parentElement).toHaveClass('text-ink-soft');
        expect(menuButton).toHaveClass('size-control-md', 'bg-transparent');
        expect(menuButton).not.toHaveClass('border');
    });

    it('posts the planned due date chosen in the sheet, with its original date', async () => {
        stubSearch({
            planned: [
                {
                    kind: 'expense',
                    id: 6,
                    title: 'Gas',
                    organization: null,
                    due_on: '2026-11-02',
                    occurrence: '2026-10-30',
                    late: true,
                    amount: 84.2,
                    estimated: true,
                    paid_on: null,
                    ended: false,
                    ended_on: null,
                },
            ],
            recorded: [],
        });
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(BankTransactions, props());
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', {
                name: 'It belongs to an expense',
            }),
        );
        const dialog = await screen.findByRole('dialog', {
            name: 'It belongs to an expense',
        });
        await userEvent.click(
            await within(dialog).findByRole('button', { name: 'Mark as paid' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/budget/bank/2/assign',
            { kind: 'expense', planned_id: 6, occurrence: '2026-10-30' },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(screen.queryByText(/EDISON ENERGIA/)).not.toBeInTheDocument();
    });

    it('posts the recorded movement chosen in the sheet', async () => {
        stubSearch({
            planned: [],
            recorded: [
                {
                    kind: 'expense',
                    id: 31,
                    title: 'Luce settembre',
                    organization: 'Edison Energia',
                    date: '2026-09-05',
                    amount: 70.1,
                },
            ],
        });
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(BankTransactions, props());
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', {
                name: 'It belongs to an expense',
            }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Link the transaction' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/budget/bank/2/assign',
            { kind: 'expense', movement_id: 31 },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('keeps the sheet and shows the message when the server refuses', async () => {
        stubSearch({
            planned: [
                {
                    kind: 'expense',
                    id: 5,
                    title: 'Luce',
                    organization: null,
                    due_on: '2026-11-05',
                    occurrence: '2026-11-05',
                    late: false,
                    amount: 72,
                    estimated: false,
                    paid_on: null,
                    ended: false,
                    ended_on: null,
                },
            ],
            recorded: [],
        });
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onError?.({
                planned_id: 'This planned expense has no due dates to pay.',
            });
        });
        renderPage(BankTransactions, props());
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', {
                name: 'It belongs to an expense',
            }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Mark as paid' }),
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'This planned expense has no due dates to pay.',
        );
        expect(screen.getAllByText(/EDISON ENERGIA/).length).toBeGreaterThan(1);
    });
});

describe('Budget, From the bank, a text that comes back every month', () => {
    const netflix: App.Data.BankTransactionData = {
        ...bankTransactions[1]!,
        id: 7,
        text: 'ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV',
        counterparty: 'Netflix',
        amount: -13.99,
        recurring: true,
    };

    it('offers a planned expense that starts from the transaction', () => {
        renderPage(BankTransactions, props({ rows: [netflix] }));
        const row = screen.getByText(/NETFLIX/).closest('li')!;

        expect(
            within(row).getByRole('link', {
                name: 'It comes back every month: create a planned expense?',
            }),
        ).toHaveAttribute('href', '/budget/planned/expense/create?bank=7');
    });

    it('opens a planned income for a credit', () => {
        renderPage(
            BankTransactions,
            props({ rows: [{ ...netflix, amount: 35 }] }),
        );

        expect(
            screen.getByRole('link', {
                name: 'It comes back every month: create a planned income?',
            }),
        ).toHaveAttribute('href', '/budget/planned/income/create?bank=7');
    });

    it('is not offered on the wide layout either to who cannot plan, nor on a pending or ignored one', () => {
        screenWidth(1280);
        const view = renderPage(BankTransactions, props({ rows: [netflix] }));
        expect(
            screen.getByRole('link', { name: /It comes back every month/ }),
        ).toBeInTheDocument();
        view.unmount();

        for (const extra of [
            { can_plan: false },
            { rows: [{ ...netflix, pending: true, booked_on: null }] },
            {
                rows: [{ ...netflix, ignored: true }],
                show: 'all',
                ...allProps(),
            },
            { rows: [{ ...netflix, currency: 'USD' }] },
            { rows: [{ ...netflix, recurring: false }] },
            { can_sort: false },
        ]) {
            const rendered = renderPage(
                BankTransactions,
                props({ rows: [netflix], ...extra }),
            );
            expect(
                screen.queryByRole('link', {
                    name: /It comes back every month/,
                }),
            ).not.toBeInTheDocument();
            rendered.unmount();
        }
    });
});

describe('Budget, From the bank, a commission left alone', () => {
    const fee: App.Data.BankTransactionData = {
        ...bankTransactions[1]!,
        id: 14,
        text: 'COMMISSIONI DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A.',
        counterparty: null,
        amount: -0.4,
        orphan_fee: true,
    };

    const satispay: App.Data.BankRecordedChoiceData[] = [
        {
            kind: 'expense',
            id: 41,
            title: 'Ricarica Satispay',
            organization: 'Satispay',
            date: '2026-08-25',
            amount: 10,
        },
    ];

    it('an orphan commission has only It is the commission of… and the menu', () => {
        renderPage(BankTransactions, props({ rows: [fee] }));
        const row = screen
            .getByText(/COMMISSIONI DOMICILIAZIONE/)
            .closest('li')!;

        expect(
            within(row).getByRole('button', {
                name: 'It is the commission of…',
            }),
        ).toBeInTheDocument();
        expect(within(row).getByText('Commission')).toBeInTheDocument();
        expect(
            within(row).getByText('Left without its direct debit'),
        ).toBeInTheDocument();
        expect(
            within(row).queryByRole('link', { name: 'Create the expense' }),
        ).not.toBeInTheDocument();
        expect(
            within(row).queryByRole('button', {
                name: 'It belongs to an expense',
            }),
        ).not.toBeInTheDocument();
        expect(
            within(row).getByRole('button', { name: 'More actions' }),
        ).toBeInTheDocument();
    });

    it('a commission with its debit has no such button', () => {
        renderPage(
            BankTransactions,
            props({ rows: [{ ...fee, orphan_fee: false }] }),
        );
        const row = screen
            .getByText(/COMMISSIONI DOMICILIAZIONE/)
            .closest('li')!;

        expect(
            within(row).queryByRole('button', {
                name: 'It is the commission of…',
            }),
        ).not.toBeInTheDocument();
    });

    it('posts the expense chosen', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    json: () =>
                        Promise.resolve({
                            planned: [],
                            recorded: satispay,
                            organization: 'Satispay',
                        }),
                }),
            ),
        );
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(BankTransactions, props({ rows: [fee] }));
        const row = screen
            .getByText(/COMMISSIONI DOMICILIAZIONE/)
            .closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', {
                name: 'It is the commission of…',
            }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Link the commission' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/budget/bank/14/fee',
            { expense_id: 41 },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.queryByText(/COMMISSIONI DOMICILIAZIONE/),
        ).not.toBeInTheDocument();
    });
});

/**
 * The 31 groups of the boards: the five of the fixtures and 26 more, biggest first as the server sorts them.
 *
 * @returns the groups
 */
function thirtyOneGroups(): App.Data.BankGroupData[] {
    return [
        ...bankGroups,
        ...Array.from({ length: 26 }, (_, index) => ({
            ...bankGroups[3],
            key: `group${index}`,
            name: `Shop ${index + 1}`,
            count: 2,
        })),
    ];
}

/** The props of the tab *To sort* of the boards: 268 to sort, 31 groups and 42 lone movements. */
function groupProps(
    extra: Record<string, unknown> = {},
): Record<string, unknown> {
    return props({
        groups: thirtyOneGroups(),
        counts: { unsorted: 268, groups: 31, lone: 42 },
        ...extra,
    });
}

describe('Budget, From the bank, the groups', () => {
    it('the head says «268 da sistemare · 31 gruppi»', () => {
        renderPage(BankTransactions, groupProps());

        expect(
            screen.getByRole('heading', { name: '268 to sort · 31 groups' }),
        ).toBeInTheDocument();
    });

    it('shows five groups and Show the other 26', async () => {
        renderPage(BankTransactions, groupProps());

        const section = screen.getByRole('region', { name: 'Groups' });
        expect(within(section).getAllByRole('article')).toHaveLength(5);

        await userEvent.click(
            within(section).getByRole('button', {
                name: 'Show the other 26 groups',
            }),
        );
        expect(within(section).getAllByRole('article')).toHaveLength(31);
        expect(
            within(section).queryByRole('button', { name: /Show the other/ }),
        ).toBeNull();
    });

    it('the lone movements come under their title with their number', () => {
        renderPage(BankTransactions, groupProps());

        const section = screen.getByRole('region', { name: 'Lone movements' });
        expect(
            within(section).getByRole('heading', {
                name: 'Lone movements · 42',
            }),
        ).toBeInTheDocument();
        expect(
            within(section).getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            ),
        ).toBeInTheDocument();
    });

    it('loads the next lone movements with Show more', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        renderPage(BankTransactions, groupProps({ next: 2 }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Show 39 more movements' }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['rows', 'next'],
                data: { page: 2 },
                preserveUrl: true,
            }),
        );
    });

    it('without groups shows only the lone movements', () => {
        renderPage(BankTransactions, props());

        expect(screen.queryByRole('region', { name: 'Groups' })).toBeNull();
        expect(
            screen.getByRole('heading', { name: 'Lone movements · 3' }),
        ).toBeInTheDocument();
    });

    it('opening a group asks its rows', async () => {
        screenWidth(true);
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation((options) => {
                options?.onSuccess?.({
                    props: { group_rows: structuredClone(bankGroupRows) },
                } as never);
            });
        renderPage(BankTransactions, groupProps());

        await userEvent.click(
            screen.getByRole('button', { name: /Satispay Europe/ }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['group_rows'],
                data: { group: '3f9a1c2b7d4e' },
                preserveUrl: true,
            }),
        );
        const panel = await screen.findByRole('dialog', {
            name: 'Satispay Europe',
        });
        expect(
            within(panel).getAllByRole('button', {
                name: 'It is not of this group',
            }),
        ).toHaveLength(6);
    });

    it('It is not of this group takes the movement out of the open group, and the panel drops it', async () => {
        screenWidth(true);
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            options?.onSuccess?.({
                props: { group_rows: structuredClone(bankGroupRows) },
            } as never);
        });
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({ props: {} } as never);
            });
        renderPage(BankTransactions, groupProps());
        await userEvent.click(
            screen.getByRole('button', { name: /Satispay Europe/ }),
        );
        const panel = await screen.findByRole('dialog', {
            name: 'Satispay Europe',
        });
        const first = bankGroupRows[0]!.transaction;

        await userEvent.click(
            within(panel).getAllByRole('button', {
                name: 'It is not of this group',
            })[0]!,
        );

        expect(post).toHaveBeenCalledWith(
            `/budget/bank/${first.id}/leave`,
            { group: '3f9a1c2b7d4e' },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            within(panel).getAllByRole('button', {
                name: 'It is not of this group',
            }),
        ).toHaveLength(6);
        await userEvent.click(
            within(panel).getByRole('button', { name: /Show/ }),
        );
        expect(
            within(panel).getAllByRole('button', {
                name: 'It is not of this group',
            }),
        ).toHaveLength(bankGroupRows.length - 1);
    });

    it('two groups opened quickly show the rows of the last one, whatever order the answers come in', async () => {
        screenWidth(true);
        const answers: Array<() => void> = [];
        const inpsRows = bankGroupRows.slice(0, 2).map((row) => ({
            ...row,
            transaction: {
                ...row.transaction,
                text: 'BONIFICO SEPA DA INPS ASSEGNO UNICO',
            },
        }));
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            const key = (options?.data as { group?: string } | undefined)
                ?.group;
            answers.push(() =>
                options?.onSuccess?.({
                    props: {
                        group_rows:
                            key === '3f9a1c2b7d4e' ? bankGroupRows : inpsRows,
                    },
                } as never),
            );
        });
        renderPage(BankTransactions, groupProps());

        await userEvent.click(
            screen.getByRole('button', { name: /Satispay Europe/ }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: /INPS · Assegno Unico/ }),
        );
        // The answer for INPS comes first, the one for Satispay after it
        answers[1]();
        answers[0]();
        await nextTick();

        const panel = screen.getByRole('dialog', {
            name: 'INPS · Assegno Unico',
        });
        expect(
            within(panel).getAllByText('BONIFICO SEPA DA INPS ASSEGNO UNICO'),
        ).toHaveLength(2);
        expect(within(panel).queryByText(/Satispay Europe S\.A\./)).toBeNull();
    });

    it('when wide, the open group puts the groups in a list beside it, and the lone movements bring the page back', async () => {
        screenWidth(true);
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            options?.onSuccess?.({ props: { group_rows: [] } } as never);
        });
        renderPage(BankTransactions, groupProps());

        await userEvent.click(
            screen.getByRole('button', { name: /INPS · Assegno Unico/ }),
        );

        const nav = screen.getByRole('navigation', { name: 'Groups' });
        expect(
            within(nav).getByRole('button', { name: /INPS · Assegno Unico/ }),
        ).toHaveAttribute('aria-current', 'true');
        expect(
            screen.queryByRole('region', { name: 'Lone movements' }),
        ).toBeNull();

        await userEvent.click(
            screen.getByRole('button', { name: /Lone movements · 42/ }),
        );
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(
            screen.getByRole('region', { name: 'Lone movements' }),
        ).toBeInTheDocument();
    });

    it('the number of the lone movements follows the server after a sort, without taking the row off twice', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onSuccess?.({} as never);
        });
        const view = renderPage(BankTransactions, groupProps({ next: 2 }));
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(screen.getByRole('menuitem', { name: 'Ignore' }));
        // The redirect after the sort brings the counts already lowered
        await view.rerender(
            groupProps({
                next: 2,
                counts: { unsorted: 267, groups: 31, lone: 41 },
            }),
        );

        expect(
            screen.getByRole('heading', { name: 'Lone movements · 41' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Show 39 more movements' }),
        ).toBeInTheDocument();
    });

    it('on the phone the open group is a sheet over the page', async () => {
        screenWidth(false);
        vi.spyOn(router, 'reload').mockImplementation(() => {});
        renderPage(BankTransactions, groupProps());

        await userEvent.click(
            screen.getByRole('button', { name: /Satispay Europe/ }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'Satispay Europe' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('region', {
                name: 'Lone movements',
                // The sheet hides the page under it from screen readers
                hidden: true,
            }),
        ).toBeInTheDocument();
    });
});

describe('Budget, From the bank, Maybe it is…', () => {
    /** The rows of the page with the proposal on the direct debit of Edison. */
    function withProposal(
        proposal: App.Data.BankProposalData | null,
    ): App.Data.BankTransactionData[] {
        return bankTransactions.map((row) =>
            row.id === 2 ? { ...row, proposal } : row,
        );
    }

    it('shows the line under the text of a movement that has a proposal, and under no other', () => {
        renderPage(
            BankTransactions,
            props({ rows: withProposal(bankProposals.recorded) }),
        );

        expect(screen.getAllByRole('button', { name: /^Yes/ })).toHaveLength(1);
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;
        expect(within(row).getByText('Spesa Famila')).toBeInTheDocument();
    });

    it('shows none to who cannot sort', () => {
        renderPage(
            BankTransactions,
            props({
                rows: withProposal(bankProposals.recorded),
                can_sort: false,
            }),
        );

        expect(screen.queryByRole('button', { name: /^Yes/ })).toBeNull();
    });

    it('Yes sends the key, keeps the scroll and the row goes', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(
            BankTransactions,
            props({ rows: withProposal(bankProposals.recorded) }),
        );

        await userEvent.click(screen.getByRole('button', { name: /^Yes/ }));

        expect(post).toHaveBeenCalledWith(
            '/budget/bank/2/proposal',
            { key: 'expense:812' },
            expect.objectContaining({
                preserveScroll: true,
                only: ['rows', 'counts', 'next', 'bank_feed'],
            }),
        );
        expect(screen.queryByText(/EDISON ENERGIA/)).not.toBeInTheDocument();
    });

    it('says something when the server refuses without a message', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onError?.({});
        });
        renderPage(
            BankTransactions,
            props({ rows: withProposal(bankProposals.recorded) }),
        );

        await userEvent.click(screen.getByRole('button', { name: /^Yes/ }));

        expect(toast.error).toHaveBeenCalledWith(
            'Something went wrong, try again.',
        );
    });

    it('Yes keeps the row and the line when the server refuses', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onError?.({ key: 'This proposal is not valid any more.' });
            options?.onFinish?.({} as never);
        });
        renderPage(
            BankTransactions,
            props({ rows: withProposal(bankProposals.recorded) }),
        );

        await userEvent.click(screen.getByRole('button', { name: /^Yes/ }));

        expect(screen.getByText(/EDISON ENERGIA/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^Yes/ })).toBeEnabled();
    });

    it('No sends the key, keeps the scroll and takes the line away but not the row', async () => {
        const del = vi
            .spyOn(router, 'delete')
            .mockImplementation((_url, options) => {
                options?.onSuccess?.({} as never);
            });
        renderPage(
            BankTransactions,
            props({ rows: withProposal(bankProposals.recorded) }),
        );

        await userEvent.click(screen.getByRole('button', { name: /^No/ }));

        expect(del).toHaveBeenCalledWith(
            '/budget/bank/2/proposal',
            expect.objectContaining({
                data: { key: 'expense:812' },
                preserveScroll: true,
                only: ['rows', 'counts', 'next', 'bank_feed'],
            }),
        );
        expect(screen.getByText(/EDISON ENERGIA/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /^Yes/ })).toBeNull();
    });

    it('shows the next candidate the server sends after No', async () => {
        vi.spyOn(router, 'delete').mockImplementation((_url, options) => {
            options?.onSuccess?.({} as never);
        });
        const view = renderPage(
            BankTransactions,
            props({ rows: withProposal(bankProposals.recorded) }),
        );

        await userEvent.click(screen.getByRole('button', { name: /^No/ }));
        await view.rerender(
            props({ rows: withProposal(bankProposals.deadline) }),
        );

        expect(
            screen.getByText('Assegno Unico novembre 2026'),
        ).toBeInTheDocument();
    });
});

/** What the server flashes after a movement of a group is sorted: 3 others, the carried rows, and the filter form. */
function similarFlash(): FlashSimilar {
    return {
        count: 3,
        kind: 'expense',
        filter: bankFilters.find((filter) => filter.action === 'create')!,
        reason: { kind: 'mandate', name: 'Satispay' },
        min: 10,
        max: 50,
        from: '2026-09-02',
        to: '2026-11-17',
        rows: [4, 5, 6].map((id) => ({
            ...bankTransactions[1]!,
            id,
            text: 'SATISPAY EUROPE',
        })),
        action: 'create',
        url: '/budget/bank/filters/create?from=2',
    };
}

describe('Budget, From the bank, the filter for the others', () => {
    it('offers the filter for the others after the movement is marked as a transfer', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onFlash?.({ similar: similarFlash() } as never);
            options?.onSuccess?.({} as never);
        });
        renderPage(
            BankTransactions,
            props({
                accounts_all: [
                    {
                        id: 4,
                        name: 'Conto BancoPosta ·· 3307',
                        connected: true,
                    },
                    { id: 6, name: 'Buddy Bank ·· 5521', connected: true },
                ],
            }),
        );
        const row = screen
            .getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            )
            .closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(
            screen.getByRole('menuitem', { name: 'It is a transfer' }),
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Mark as a transfer' }),
        );

        expect(
            await screen.findByRole('link', { name: 'Create the filter' }),
        ).toHaveAttribute('href', '/budget/bank/filters/create?from=2');
        await userEvent.click(
            screen.getByRole('button', { name: 'No, leave it so' }),
        );
        await settle();

        expect(
            screen.queryByRole('link', { name: 'Create the filter' }),
        ).not.toBeInTheDocument();
    });

    it('offers it when the page opens with the flash (the expense created from the form of a movement)', async () => {
        shared.flash = { similar: similarFlash() };
        renderPage(BankTransactions, props());

        expect(
            await screen.findByRole('link', { name: 'Create the filter' }),
        ).toHaveAttribute('href', '/budget/bank/filters/create?from=2');
    });

    it('does not open again after it was dismissed, when the page gets new props', async () => {
        shared.flash = { similar: similarFlash() };
        const view = renderPage(BankTransactions, props());

        await userEvent.click(
            await screen.findByRole('button', { name: 'No, leave it so' }),
        );
        shared.flash = {};
        await view.rerender(structuredClone(props()));
        await settle();

        expect(
            screen.queryByRole('link', { name: 'Create the filter' }),
        ).not.toBeInTheDocument();
    });

    it('offers nothing without the flash', async () => {
        renderPage(BankTransactions, props());
        await settle();

        expect(
            screen.queryByRole('link', { name: 'Create the filter' }),
        ).not.toBeInTheDocument();
    });
});
