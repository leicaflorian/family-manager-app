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
import { renderPage } from '@/test/page';
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
            within(row).getByText('05/11/2026 · Conto BancoPosta ·· 3307'),
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
            within(row).getByText('06/11/2026 · Conto BancoPosta ·· 3307'),
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
                'Last update today at 13:00 · they arrive by themselves at 7, 13, and 19',
            ),
        ).toBeInTheDocument();

        renderPage(BankTransactions, props({ last_synced_at: null }));
        expect(
            screen.getByText('They arrive by themselves at 7, 13, and 19'),
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
            within(tabs).getByRole('link', { name: /From the bank\s*6/ }),
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
});
