import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
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
import BankLoneRow from './BankLoneRow.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ url: '/budget/bank', props: { name: 'Fameja.app' } }),
}));

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-20T14:30:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const [pending, booked, dollars] = bankTransactions;

/** A direct debit with its commission of the day after, as the controller sends it. */
const debit: App.Data.BankTransactionData = {
    ...booked,
    id: 40,
    text: 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Telepass S.p.A. CID.IT40ZZZ0000001234567',
    amount: -10,
    booked_on: '2026-11-24',
    value_on: '2026-11-24',
    fee: { id: 41, amount: -0.4, booked_on: '2026-11-25' },
};

/**
 * Mounts the row as the page does.
 *
 * @param row - the transaction
 * @param extra - the other props
 */
function mount(
    row: App.Data.BankTransactionData,
    extra: Record<string, unknown> = {},
): ReturnType<typeof renderPage> {
    return renderPage(BankLoneRow, {
        row,
        canSort: true,
        canPlan: true,
        ...extra,
    });
}

describe('BankLoneRow', () => {
    it('shows the text, the day with the account and the amount', () => {
        mount(booked);

        expect(
            screen.getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('05/11 · Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
        expect(screen.getByText('-72,40 €')).toBeInTheDocument();
    });

    it('a direct debit shows its commission under it and both amounts', () => {
        mount(debit);

        expect(
            screen.getByText('+ 0,40 € commission, 25/11'),
        ).toBeInTheDocument();
        expect(screen.getByText('-10,00 €')).toBeInTheDocument();
        expect(screen.getByText('+ 0,40 €')).toBeInTheDocument();
    });

    it('offers Create the expense and It belongs to an expense, and says which was tapped', async () => {
        const view = mount(booked);

        expect(
            screen.getByRole('link', { name: 'Create the expense' }),
        ).toHaveAttribute('href', '/budget/movements/create?bank=2');
        await userEvent.click(
            screen.getByRole('button', { name: 'It belongs to an expense' }),
        );
        expect(view.emitted('assign')).toEqual([[booked]]);
    });

    it('has the menu with It is a transfer and Ignore', async () => {
        const view = mount(booked);

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            screen
                .getAllByRole('menuitem')
                .map((item) => item.textContent?.trim()),
        ).toEqual(['It is a transfer', 'Ignore']);
        await userEvent.click(
            screen.getByRole('menuitem', { name: 'It is a transfer' }),
        );
        expect(view.emitted('transfer')).toEqual([[booked]]);

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(screen.getByRole('menuitem', { name: 'Ignore' }));
        expect(view.emitted('ignore')).toEqual([[booked]]);
    });

    it('has the same actions on the wide layout, in their column', async () => {
        screenWidth(true);
        const view = mount(booked);

        expect(screen.getByText('05/11')).toBeInTheDocument();
        expect(
            screen.getByText('Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Create the expense' }),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(screen.getByRole('menuitem', { name: 'Ignore' }));
        expect(view.emitted('ignore')).toEqual([[booked]]);
    });

    it('says which filters take it when two do', () => {
        mount({ ...booked, two_filters: ['Edison', 'Bollette luce'] });

        expect(screen.getByRole('note')).toHaveTextContent(
            '2 filters take it: Edison and Bollette luce. None of them sorts it: change one of them.',
        );
    });

    it('marks a pending transaction and offers nothing on it, nor on one in another currency', () => {
        const view = mount(pending);
        expect(screen.getByText('Pending')).toBeInTheDocument();
        expect(screen.queryByRole('button')).toBeNull();
        expect(screen.queryByRole('link')).toBeNull();
        view.unmount();

        mount(dollars);
        expect(screen.queryByRole('link')).toBeNull();
        expect(
            screen.getByRole('button', { name: 'More actions' }),
        ).toBeInTheDocument();
    });

    it('offers nothing to who cannot sort', () => {
        mount(booked, { canSort: false });

        expect(screen.queryByRole('button')).toBeNull();
        expect(screen.queryByRole('link')).toBeNull();
    });

    it('keeps a place for what the system proposes', () => {
        render(BankLoneRow, {
            props: { row: booked, canSort: true, canPlan: true },
            slots: { proposal: '<p>Maybe it is…</p>' },
        });

        expect(screen.getByText('Maybe it is…')).toBeInTheDocument();
    });
});
