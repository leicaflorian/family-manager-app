import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { bankTransactions } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankAssignSheet from './BankAssignSheet.vue';

const transaction = bankTransactions[1]!;

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
        organization: 'Enel Energia',
        due_on: '2026-10-30',
        late: true,
        amount: 84.2,
        estimated: true,
    },
    {
        kind: 'expense',
        id: 7,
        title: 'Internet - Fastweb',
        organization: 'Fastweb',
        due_on: '2026-11-03',
        late: false,
        amount: 29.9,
        estimated: false,
    },
    {
        kind: 'expense',
        id: 8,
        title: 'Palestra',
        organization: null,
        due_on: '2026-11-15',
        late: false,
        amount: 40,
        estimated: false,
    },
];

/** Opens the sheet for the Edison transaction. */
function open(
    extra: Record<string, unknown> = {},
): ReturnType<typeof renderPage> {
    return renderPage(BankAssignSheet, {
        open: true,
        transaction,
        choices,
        ...extra,
    });
}

describe('BankAssignSheet', () => {
    it('shows the transaction and the planned expenses with their due date and amount', async () => {
        open();

        const dialog = screen.getByRole('dialog', {
            name: 'It belongs to a planned expense',
        });
        expect(
            within(dialog).getByText(
                'ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789',
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText('05/11/2026 · Conto BancoPosta ·· 3307'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByRole('radio', { name: /Luce/ }),
        ).toBeChecked();
        expect(
            within(dialog).getByText('Edison Energia · Due on 05/11/2026'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText('Enel Energia · Due on 30/10/2026, late'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText('Due on 15/11/2026'),
        ).toBeInTheDocument();
        expect(within(dialog).getByText(/^~72,00/)).toBeInTheDocument();
        expect(within(dialog).getByText(/^29,90/)).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                'The due date closest to 05/11/2026 is marked as paid. Afterwards you can write the rule, so next time it pays by itself.',
            ),
        ).toBeInTheDocument();
    });

    it('narrows the list by name or organization while typing', async () => {
        open();

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a planned expense' }),
            'fast',
        );

        expect(
            screen.getByRole('radio', { name: /Internet - Fastweb/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('radio', { name: /Luce/ }),
        ).not.toBeInTheDocument();

        await userEvent.clear(
            screen.getByRole('searchbox', { name: 'Search a planned expense' }),
        );
        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a planned expense' }),
            'zzz',
        );
        expect(
            screen.getByText('No planned expense found.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Mark as paid' }),
        ).toBeDisabled();
    });

    it('gives the planned expense chosen when Mark as paid is pressed', async () => {
        const view = open();

        await userEvent.click(screen.getByRole('radio', { name: /Gas/ }));
        await userEvent.click(
            screen.getByRole('button', { name: 'Mark as paid' }),
        );

        expect(view.emitted().assign).toEqual([[choices[1]]]);
    });

    it('speaks of an income for a credit', async () => {
        open({
            transaction: { ...transaction, amount: 35 },
            choices: [{ ...choices[0]!, kind: 'income' }],
        });

        expect(
            screen.getByRole('dialog', {
                name: 'It belongs to a planned income',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('searchbox', { name: 'Search a planned income' }),
        ).toBeInTheDocument();
    });

    it('waits for the list while the page asks for it', async () => {
        open({ choices: undefined });

        expect(screen.getByText('Loading…')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Mark as paid' }),
        ).toBeDisabled();
    });

    it('shows the message of the server under the list', async () => {
        open({ error: 'This planned expense has no due dates to pay.' });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'This planned expense has no due dates to pay.',
        );
    });

    it('closes with Cancel', async () => {
        const view = open();

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(view.emitted()['update:open']).toEqual([[false]]);
    });

    it('has the visible label of the board over a box with its own placeholder', async () => {
        open();

        expect(
            screen.getByText('Search a planned expense', { selector: 'label' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('searchbox', { name: 'Search a planned expense' }),
        ).toHaveAttribute('placeholder', 'Search by name or organization');
    });
});
