import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, screenWidth } from '@/test/page';
import BankRawPanel from './BankRawPanel.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ url: '/budget/bank', props: { name: 'Fameja.app' } }),
}));

afterEach(() => vi.unstubAllGlobals());

const row: App.Data.BankTransactionData = {
    id: 9,
    booked_on: '2026-11-17',
    value_on: '2026-11-18',
    text: 'BONIFICO SEPA Da INPS ASSEGNO UNICO',
    counterparty: 'INPS',
    amount: 407.6,
    currency: 'EUR',
    account: 'Conto BancoPosta ·· 3307',
    pending: false,
    ignored: false,
    recurring: false,
    status: 'linked',
    link: {
        kind: 'income',
        id: 4,
        title: 'Assegno Unico novembre 2026',
        url: '/budget/movements/income/4',
        how: 'duplicate',
        filter_id: null,
        filter_name: null,
        undo: 'delete',
        undo_url: '/budget/bank/9/undo',
    },
    provider_id: '2026111700731845529',
    read_at: '2026-11-27T07:00',
    bank_status: 'booked',
    transfer: null,
    account_id: 4,
};

describe('BankRawPanel', () => {
    it('shows the text, who pays, both dates, the account, the bank status, the bank code and when it was read', () => {
        screenWidth(true);
        renderPage(BankRawPanel, { row });

        const panel = screen.getByRole('dialog', { name: 'Bank transaction' });
        expect(
            within(panel).getByText('BONIFICO SEPA Da INPS ASSEGNO UNICO'),
        ).toBeInTheDocument();
        const value = (term: string): string | null =>
            within(panel).getByText(term).nextElementSibling!.textContent;
        expect(value('Who pays')).toBe('INPS');
        expect(value('Booking date')).toBe('17/11/2026');
        expect(value('Value date')).toBe('18/11/2026');
        expect(value('Account')).toContain('Conto BancoPosta ·· 3307');
        expect(value('Status at the bank')).toBe('Booked');
        expect(value('Bank code')).toBe('2026111700731845529');
        expect(value('Read')).toBe('27/11/2026 at 07:00');
        expect(value('Amount')).toContain('407,60');
        expect(value('Amount')).toContain('EUR');
        expect(within(panel).getByText('An income')).toBeInTheDocument();
    });

    it('is a bottom sheet on the phone and a side panel when wide', async () => {
        screenWidth(false);
        const phone = renderPage(BankRawPanel, { row });
        expect(phone.container.querySelector('[role="dialog"]')).toBeNull();
        expect(
            await screen.findByRole('dialog', { name: 'Bank transaction' }),
        ).toBeInTheDocument();
        phone.unmount();

        screenWidth(true);
        const wide = renderPage(BankRawPanel, { row });
        expect(wide.container.querySelector('[role="dialog"]')).not.toBeNull();
    });

    it('asks to close and to take the link back', async () => {
        screenWidth(true);
        const view = renderPage(BankRawPanel, { row });

        await userEvent.click(
            screen.getByRole('button', { name: 'It was not this one' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(view.emitted('undo')?.[0]).toEqual([row]);
        expect(view.emitted('close')).toHaveLength(1);
    });

    it.each([
        ['without a counterparty', { counterparty: null }, 'Who is paid'],
        ['without a value date', { value_on: null }, 'Value date'],
        ['without both', { counterparty: null, value_on: null }, 'Who is paid'],
    ])('keeps the rows in their order %s', (_name, change, missing) => {
        screenWidth(true);
        renderPage(BankRawPanel, { row: { ...row, ...change } });

        const terms = [...screen.getAllByRole('term')].map(
            (term) => term.textContent,
        );
        expect(terms).not.toContain(missing);
        const order = [
            'Who pays',
            'Booking date',
            'Value date',
            'Amount',
            'Account',
            'Status at the bank',
            'Bank code',
            'Read',
        ].filter((term) => terms.includes(term));
        expect(terms).toEqual(order);
    });
});
