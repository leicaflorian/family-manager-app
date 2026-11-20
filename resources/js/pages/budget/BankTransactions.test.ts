import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { bankTransactions } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import BankTransactions from './BankTransactions.vue';

const shared = vi.hoisted(() => ({
    bank_feed: { unsorted: 6 } as { unsorted: number } | null,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        url: '/budget/bank',
        props: { name: 'Fameja.app', ...shared },
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
});

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
        ...extra,
    };
}

describe('Budget, From the bank', () => {
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

    it('shows the number to sort on the first filter and links the other', () => {
        renderPage(BankTransactions, props());

        const filters = screen.getByRole('navigation', {
            name: 'Movements from the bank',
        });
        expect(
            within(filters).getByRole('link', { name: /To sort\s*6/ }),
        ).toHaveAttribute('href', '/budget/bank');
        expect(
            within(filters).getByRole('link', { name: 'Ignored' }),
        ).toHaveAttribute('href', '/budget/bank?show=ignored');
    });

    it('says there is nothing to sort', () => {
        renderPage(BankTransactions, props({ rows: [] }));

        expect(screen.getByText('No movement to sort')).toBeInTheDocument();
        expect(
            screen.getByText(
                'The movements that pay a fixed expense are recorded by themselves. The others arrive here, to create or to ignore.',
            ),
        ).toBeInTheDocument();
    });

    it('says what stays under Ignored, and for how long', () => {
        renderPage(
            BankTransactions,
            props({
                show: 'ignored',
                rows: [{ ...bankTransactions[1]!, ignored: true }],
            }),
        );

        expect(
            screen.getByText(
                'Ignored movements stay here for 12 months, then they are removed.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText(/EDISON ENERGIA/)).toBeInTheDocument();
    });

    it('says there is nothing ignored', () => {
        renderPage(BankTransactions, props({ show: 'ignored', rows: [] }));

        expect(screen.getByText('No ignored movement')).toBeInTheDocument();
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

    it('adds the tab From the bank with its number to the tabs of the budget', () => {
        renderPage(BankTransactions, props());

        const tabs = screen.getByRole('navigation', { name: 'Budget' });
        expect(
            within(tabs).getByRole('link', {
                name: 'From the bank, 6 to sort',
            }),
        ).toHaveAttribute('aria-current', 'page');
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
            within(row).getByRole('button', { name: 'Ignore' }),
        );

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
            props({
                show: 'ignored',
                rows: [{ ...bankTransactions[1]!, ignored: true }],
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
        expect(screen.getByText('No ignored movement')).toBeInTheDocument();
    });

    it('offers no action to who cannot sort', () => {
        renderPage(BankTransactions, props({ can_sort: false }));

        expect(
            screen.queryByRole('button', { name: 'Ignore' }),
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
                name: 'It belongs to a planned expense',
            }),
        ).toBeInTheDocument();
    });

    it('draws the actions of a row on the phone at their own width, in the small text', () => {
        renderPage(BankTransactions, props());
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        for (const action of [
            within(row).getByRole('link', { name: 'Create the expense' }),
            within(row).getByRole('button', {
                name: 'It belongs to a planned expense',
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
                name: 'It belongs to a planned income',
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
            within(pending).queryByRole('button', { name: 'Ignore' }),
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
            props({
                show: 'ignored',
                rows: [{ ...bankTransactions[1]!, ignored: true }],
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

        expect(
            within(row).getByRole('link', { name: 'Create the expense' }),
        ).toBeInTheDocument();
        expect(
            within(row).getByRole('button', {
                name: 'It belongs to a planned expense',
            }),
        ).toBeInTheDocument();
        expect(
            within(row).getByRole('button', { name: 'Ignore' }),
        ).toBeInTheDocument();
    });

    it('asks for the planned expenses when the sheet opens and pays the one chosen', async () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation((options) => {
                options?.onSuccess?.({} as never);
            });
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        const choices: App.Data.BankPlannedChoiceData[] = [
            {
                kind: 'expense',
                id: 5,
                title: 'Luce',
                organization: 'Edison Energia',
                due_on: '2026-11-05',
                late: false,
                amount: 72,
                estimated: true,
            },
            {
                kind: 'expense',
                id: 6,
                title: 'Gas',
                organization: null,
                due_on: '2026-10-30',
                late: true,
                amount: 84.2,
                estimated: true,
            },
        ];
        renderPage(BankTransactions, props({ planned_options: choices }));
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', {
                name: 'It belongs to a planned expense',
            }),
        );

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                data: { assign: 2 },
                only: ['planned_options'],
                preserveUrl: true,
            }),
        );
        const dialog = await screen.findByRole('dialog', {
            name: 'It belongs to a planned expense',
        });
        await userEvent.click(
            within(dialog).getByRole('radio', { name: /Gas/ }),
        );
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Mark as paid' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/budget/bank/2/assign',
            { kind: 'expense', planned_id: 6 },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(screen.queryByText(/EDISON ENERGIA/)).not.toBeInTheDocument();
    });

    it('keeps the sheet and shows the message when the server refuses', async () => {
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            options?.onSuccess?.({} as never);
        });
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onError?.({
                planned_id: 'This planned expense has no due dates to pay.',
            });
        });
        renderPage(
            BankTransactions,
            props({
                planned_options: [
                    {
                        kind: 'expense',
                        id: 5,
                        title: 'Luce',
                        organization: null,
                        due_on: '2026-11-05',
                        late: false,
                        amount: 72,
                        estimated: false,
                    },
                ],
            }),
        );
        const row = screen.getByText(/EDISON ENERGIA/).closest('li')!;

        await userEvent.click(
            within(row).getByRole('button', {
                name: 'It belongs to a planned expense',
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
            { rows: [{ ...netflix, ignored: true }], show: 'ignored' },
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
