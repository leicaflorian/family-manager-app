import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { bankTransactions } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import BankTransferSheet from './BankTransferSheet.vue';

/** A payment, on the account named «Conto BancoPosta ·· 3307». */
const debit = bankTransactions[1]!;

/** A credit on the same account. */
const credit: App.Data.BankTransactionData = { ...debit, id: 9, amount: 500 };

const accounts = [
    { id: 4, name: 'Conto BancoPosta ·· 3307', connected: true },
    { id: 5, name: 'Libretto postale', connected: false },
    { id: 6, name: 'Buddy Bank ·· 5521', connected: true },
];

/** Opens the sheet for a transaction. */
async function open(
    transaction: App.Data.BankTransactionData,
): Promise<ReturnType<typeof renderPage>> {
    const view = renderPage(BankTransferSheet, {
        open: true,
        transaction,
        accounts,
    });
    await settle();

    return view;
}

describe('BankTransferSheet', () => {
    it('asks «From which account does it come?» for a credit', async () => {
        await open(credit);

        expect(
            screen.getByRole('group', {
                name: 'From which account does it come?',
            }),
        ).toBeInTheDocument();
    });

    it('asks «To which account does it go?» for a debit', async () => {
        await open(debit);

        expect(
            screen.getByRole('group', { name: 'To which account does it go?' }),
        ).toBeInTheDocument();
    });

    it("lists the family's other accounts, not the one of the transaction", async () => {
        await open(credit);
        const group = screen.getByRole('group');

        expect(within(group).getAllByRole('radio')).toHaveLength(2);
        expect(
            within(group).queryByText('Conto BancoPosta ·· 3307'),
        ).not.toBeInTheDocument();
        expect(within(group).getByText('Libretto postale')).toBeInTheDocument();
        expect(
            within(group).getByText('Not linked to the bank'),
        ).toBeInTheDocument();
        expect(
            within(group).getByText('Linked to the bank'),
        ).toBeInTheDocument();
        expect(
            within(group).getByRole('radio', { name: /Libretto postale/ }),
        ).toBeChecked();
    });

    it('Add an account leads to the accounts of the family', async () => {
        await open(credit);

        expect(
            screen.getByRole('link', { name: 'Add an account' }),
        ).toHaveAttribute('href', '/cards/create?card=financial_account');
    });

    it('the note says it does not count in the budget', async () => {
        await open(credit);

        expect(
            screen.getByText(
                'A transfer is not an expense or an income: it does not count in the Budget.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/the two halves are sorted together/),
        ).toBeInTheDocument();
    });

    it('posts the account chosen', async () => {
        const view = await open(credit);

        await userEvent.click(
            screen.getByRole('radio', { name: /Buddy Bank/ }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Mark as a transfer' }),
        );

        expect(view.emitted().choose).toEqual([[6]]);
    });

    it('shows what the server refused', async () => {
        renderPage(BankTransferSheet, {
            open: true,
            transaction: credit,
            accounts,
            error: 'Choose another account.',
        });
        await settle();

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Choose another account.',
        );
    });
});
