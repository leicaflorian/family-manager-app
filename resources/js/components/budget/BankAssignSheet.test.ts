import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankTransactions } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import BankAssignSheet from './BankAssignSheet.vue';

const transaction = bankTransactions[1]!;

const choices: App.Data.BankPlannedChoiceData[] = [
    {
        kind: 'expense',
        id: 5,
        title: 'Luce',
        organization: 'Edison Energia',
        due_on: '2026-11-05',
        occurrence: '2026-11-05',
        late: false,
        amount: 72,
        estimated: true,
        paid_on: null,
        ended: false,
    },
    {
        kind: 'expense',
        id: 6,
        title: 'Gas',
        organization: 'Enel Energia',
        due_on: '2026-10-30',
        occurrence: '2026-10-30',
        late: true,
        amount: 84.2,
        estimated: true,
        paid_on: null,
        ended: false,
    },
    {
        kind: 'expense',
        id: 7,
        title: 'Internet - Fastweb',
        organization: 'Fastweb',
        due_on: '2026-11-03',
        occurrence: '2026-11-03',
        late: false,
        amount: 29.9,
        estimated: false,
        paid_on: null,
        ended: false,
    },
    {
        kind: 'expense',
        id: 8,
        title: 'Palestra',
        organization: null,
        due_on: '2026-11-15',
        occurrence: '2026-11-15',
        late: false,
        amount: 40,
        estimated: false,
        paid_on: null,
        ended: false,
    },
];

const recorded: App.Data.BankRecordedChoiceData[] = [
    {
        kind: 'expense',
        id: 31,
        title: 'Luce settembre',
        organization: 'Edison Energia',
        date: '2026-09-05',
        amount: 70.1,
    },
];

/** What the server answers to a search. */
type Answer = {
    planned: App.Data.BankPlannedChoiceData[];
    recorded: App.Data.BankRecordedChoiceData[];
};

/**
 * Finds `fetch` with a server that answers every search with `answer`.
 *
 * @param answer - the two lists
 */
function stubServer(
    answer: Answer = { planned: choices, recorded },
): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn(() =>
        Promise.resolve({ ok: true, json: () => Promise.resolve(answer) }),
    );
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

/** Opens the sheet for the Edison transaction. */
function open(
    extra: Record<string, unknown> = {},
): ReturnType<typeof renderPage> {
    return renderPage(BankAssignSheet, {
        open: true,
        transaction,
        ...extra,
    });
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('BankAssignSheet', () => {
    it('asks the server while the person types and shows two groups', async () => {
        const fetchMock = stubServer();
        open();
        await settle();

        const dialog = screen.getByRole('dialog', {
            name: 'It belongs to an expense',
        });
        expect(
            await within(dialog).findByText('Fixed expenses'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText('Recorded expenses'),
        ).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0]![0])).toContain(
            `/budget/bank/${transaction.id}/candidates`,
        );
        expect(
            within(dialog).getByText('Edison Energia · due on 05/11/2026'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText('Edison Energia · 05/09/2026'),
        ).toBeInTheDocument();
        expect(within(dialog).getByText(/^~72,00/)).toBeInTheDocument();

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search an expense' }),
            'gas',
        );
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        expect(String(fetchMock.mock.calls[1]![0])).toContain('q=gas');
    });

    it('does not let an older answer overwrite a newer one', async () => {
        const waiting: Array<(answer: Answer) => void> = [];
        vi.stubGlobal(
            'fetch',
            vi.fn(
                () =>
                    new Promise((resolve) => {
                        waiting.push((answer) =>
                            resolve({
                                ok: true,
                                json: () => Promise.resolve(answer),
                            }),
                        );
                    }),
            ),
        );
        open();
        await settle();
        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search an expense' }),
            'g',
        );
        await vi.waitFor(() => expect(waiting).toHaveLength(2));

        waiting[1]!({ planned: [choices[1]!], recorded: [] });
        expect(await screen.findByText('Gas')).toBeInTheDocument();
        waiting[0]!({ planned: [choices[0]!], recorded: [] });
        await settle();

        expect(screen.getByText('Gas')).toBeInTheDocument();
        expect(screen.queryByText('Luce')).not.toBeInTheDocument();
    });

    it('the first row is chosen', async () => {
        stubServer();
        open();
        await settle();

        expect(
            await screen.findByRole('radio', { name: /^Luce(?! settembre)/ }),
        ).toBeChecked();
        expect(screen.getByRole('radio', { name: /Gas/ })).not.toBeChecked();
        expect(
            screen.getByRole('button', { name: 'Mark as paid' }),
        ).toBeEnabled();
    });

    it('a planned income already paid says «già segnata il 18/11/2026»', async () => {
        stubServer({
            planned: [
                {
                    ...choices[0]!,
                    kind: 'income',
                    title: 'Assegno Unico',
                    organization: 'INPS',
                    paid_on: '2026-11-18',
                },
            ],
            recorded: [],
        });
        open({ transaction: { ...transaction, amount: 407.6 } });
        await settle();

        expect(
            await screen.findByText('INPS · already marked on 18/11/2026'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Link the transaction' }),
        ).toBeInTheDocument();
    });

    it('says an ended planned item has ended and which due date it would settle', async () => {
        stubServer({
            planned: [{ ...choices[0]!, ended: true }],
            recorded: [],
        });
        open();
        await settle();

        expect(
            await screen.findByText(
                'Edison Energia · ended · due on 05/11/2026',
            ),
        ).toBeInTheDocument();
    });

    it('the line under the list says what the choice does', async () => {
        stubServer({
            planned: [
                {
                    ...choices[0]!,
                    kind: 'income',
                    paid_on: '2026-11-18',
                },
                { ...choices[1]!, kind: 'income', due_on: '2026-08-01' },
            ],
            recorded: [],
        });
        open({ transaction: { ...transaction, amount: 35 } });
        await settle();

        expect(
            await screen.findByText(
                'Links the transaction to the income already marked, without creating another one.',
            ),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('radio', { name: /Gas/ }));

        expect(
            screen.getByText(
                'The due date of 01/08/2026 is marked as received.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Mark as received' }),
        ).toBeInTheDocument();
    });

    it("the title is «È di una spesa» for a debit and «È di un'entrata» for a credit", async () => {
        stubServer({ planned: [], recorded: [] });
        open({ transaction: { ...transaction, amount: 35 } });
        await settle();

        expect(
            screen.getByRole('dialog', { name: 'It belongs to an income' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('searchbox', { name: 'Search an income' }),
        ).toBeInTheDocument();
    });

    it('shows the empty text when nothing is found', async () => {
        stubServer({ planned: [], recorded: [] });
        open();
        await settle();

        expect(
            await screen.findByText('No expense found.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Mark as paid' }),
        ).toBeDisabled();
    });

    it('waits for the first answer', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => new Promise(() => {})),
        );
        open();
        await settle();

        expect(screen.getByText('Loading…')).toBeInTheDocument();
    });

    it('gives the planned expense chosen, with the date of the due date as the rules give it', async () => {
        stubServer({
            planned: [
                {
                    ...choices[1]!,
                    due_on: '2026-11-02',
                    occurrence: '2026-10-30',
                },
            ],
            recorded,
        });
        const view = open();
        await settle();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Mark as paid' }),
        );

        expect(view.emitted().assign).toEqual([
            [{ kind: 'expense', planned_id: 6, occurrence: '2026-10-30' }],
        ]);
    });

    it('sends the movement chosen', async () => {
        stubServer();
        const view = open();
        await settle();

        await userEvent.click(
            await screen.findByRole('radio', { name: /Luce settembre/ }),
        );
        expect(
            screen.getByText(
                'Links the transaction to the expense already recorded, without creating another one.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Link the transaction' }),
        );

        expect(view.emitted().assign).toEqual([
            [{ kind: 'expense', movement_id: 31 }],
        ]);
    });

    it('shows the message of the server under the list', async () => {
        stubServer();
        open({ error: 'This planned expense has no due dates to pay.' });
        await settle();

        expect(screen.getByRole('alert')).toHaveTextContent(
            'This planned expense has no due dates to pay.',
        );
    });

    it('closes with Cancel', async () => {
        stubServer();
        const view = open();
        await settle();

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(view.emitted()['update:open']).toEqual([[false]]);
    });

    it('has the visible label of the board over a box with its own placeholder', async () => {
        stubServer();
        open();
        await settle();

        expect(
            screen.getByText('Search an expense', { selector: 'label' }),
        ).toHaveClass('text-row');
        expect(await screen.findByText('Luce')).toHaveClass('text-body-strong');
        expect(
            screen.getByRole('searchbox', { name: 'Search an expense' }),
        ).toHaveAttribute(
            'placeholder',
            'Search by name, organization or amount',
        );
    });
});
