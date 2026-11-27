import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, screenWidth } from '@/test/page';
import BankAllList from './BankAllList.vue';

afterEach(() => vi.unstubAllGlobals());

const base: App.Data.BankTransactionData = {
    id: 1,
    booked_on: '2026-11-24',
    value_on: '2026-11-24',
    text: 'DOMICILIAZIONE SATISPAY',
    counterparty: null,
    amount: -10,
    currency: 'EUR',
    account: 'Conto BancoPosta ·· 3307',
    pending: false,
    ignored: false,
    recurring: false,
    status: 'unsorted',
    link: null,
    provider_id: 'tx-1',
    read_at: '2026-11-25T07:00',
    bank_status: 'booked',
    fee: null,
    orphan_fee: false,
    transfer: null,
    account_id: 4,
    proposal: null,
};

const linked: App.Data.BankTransactionData = {
    ...base,
    id: 2,
    status: 'linked',
    link: {
        kind: 'expense',
        id: 7,
        title: 'Ricarica Satispay',
        url: '/budget/movements/expense/7',
        how: 'rule',
        filter_id: 3,
        filter_name: 'Satispay',
        undo: 'delete',
        undo_url: '/budget/bank/2/undo',
    },
};

const ignored: App.Data.BankTransactionData = {
    ...base,
    id: 3,
    text: 'PRELIEVO ATM',
    status: 'ignored',
    ignored: true,
};

const transferOut: App.Data.BankTransactionData = {
    ...base,
    id: 4,
    text: 'POSTAGIRO A LIBRETTO',
    amount: -200,
    status: 'transfer',
    transfer: { account: 'Buddy Bank ·· 5521', direction: 'to', paired: true },
};

const transferIn: App.Data.BankTransactionData = {
    ...transferOut,
    id: 5,
    text: 'POSTAGIRO DA LIBRETTO',
    amount: 500,
    transfer: {
        account: 'Libretto postale',
        direction: 'from',
        paired: false,
    },
};

describe('BankAllList', () => {
    for (const wide of [false, true]) {
        const where = wide ? 'wide' : 'on the phone';

        it(`a linked row shows the expense as a link and how it was linked (${where})`, async () => {
            screenWidth(wide);
            const view = renderPage(BankAllList, {
                rows: [linked],
                canSort: true,
            });
            const row = screen
                .getByText('DOMICILIAZIONE SATISPAY')
                .closest('li')!;

            expect(within(row).getByText('An expense')).toBeInTheDocument();
            expect(
                within(row).getByRole('link', { name: '«Ricarica Satispay»' }),
            ).toHaveAttribute('href', '/budget/movements/expense/7');
            expect(
                within(row).getByText(/from the filter Satispay/),
            ).toBeInTheDocument();

            await userEvent.click(
                within(row).getByRole('button', {
                    name: 'It was not this one',
                }),
            );
            expect(view.emitted('undo')?.[0]).toEqual([linked]);
        });

        it(`an ignored row has Put back among the movements (${where})`, async () => {
            screenWidth(wide);
            const view = renderPage(BankAllList, {
                rows: [ignored],
                canSort: true,
            });
            const row = screen.getByText('PRELIEVO ATM').closest('li')!;

            expect(within(row).getByText('Set aside')).toBeInTheDocument();
            await userEvent.click(
                within(row).getByRole('button', {
                    name: 'Put back among the movements',
                }),
            );
            expect(view.emitted('restore')?.[0]).toEqual([ignored]);
        });

        it(`a transfer says from or to which account and With the other half (${where})`, () => {
            screenWidth(wide);
            renderPage(BankAllList, {
                rows: [transferOut, transferIn],
                canSort: true,
            });
            const out = screen.getByText('POSTAGIRO A LIBRETTO').closest('li')!;
            const into = screen
                .getByText('POSTAGIRO DA LIBRETTO')
                .closest('li')!;

            expect(within(out).getByText('Transfer')).toBeInTheDocument();
            expect(
                within(out).getByText('Buddy Bank ·· 5521'),
            ).toBeInTheDocument();
            expect(out).toHaveTextContent(
                /to Buddy Bank ·· 5521 · with the other half/,
            );
            expect(into).toHaveTextContent(/from Libretto postale/);
            expect(into).not.toHaveTextContent('with the other half');
        });

        it(`a transfer has Put back among the movements (${where})`, async () => {
            screenWidth(wide);
            const view = renderPage(BankAllList, {
                rows: [transferOut],
                canSort: true,
            });

            await userEvent.click(
                screen.getByRole('button', {
                    name: 'Put back among the movements',
                }),
            );

            expect(view.emitted('restore')?.[0]).toEqual([transferOut]);
        });
    }

    it('opens the transaction as the bank gave it when its text is tapped', async () => {
        screenWidth(true);
        const view = renderPage(BankAllList, { rows: [linked], canSort: true });

        await userEvent.click(
            screen.getByRole('button', { name: 'DOMICILIAZIONE SATISPAY' }),
        );

        expect(view.emitted('open')?.[0]).toEqual([linked]);
    });

    it('keeps the actions to who can sort', () => {
        screenWidth(true);
        renderPage(BankAllList, { rows: [linked, ignored], canSort: false });

        expect(
            screen.queryByRole('button', { name: 'It was not this one' }),
        ).toBeNull();
        expect(
            screen.queryByRole('button', {
                name: 'Put back among the movements',
            }),
        ).toBeNull();
    });

    it('the last line says unlinked movements are kept 12 months', () => {
        renderPage(BankAllList, { rows: [base], canSort: true });

        expect(
            screen.getByText(
                'Movements not linked to an expense are kept 12 months.',
            ),
        ).toBeInTheDocument();
    });
});
