import { screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankRow, fakeBankServer } from '@/test/bankMatch';
import { renderPage, settle } from '@/test/page';
import BankRuleSheet from './BankRuleSheet.vue';

const school = bankRow(1, '2026-11-05', -45);
const shop = bankRow(2, '2026-11-04', -54.3, 'PAGAMENTO POS ESSELUNGA BOLOGNA');
const proposal = {
    pattern: 'SCUOLA PASSO A DUE',
    explanation: 'It recognizes the payments to the school.',
    count: 3,
    rows: [
        bankRow(5, '2026-09-05', -45),
        bankRow(6, '2026-10-05', -45),
        school,
    ],
};

let server: ReturnType<typeof fakeBankServer>;

function serve(
    rule: { status: number; body: unknown } = { status: 200, body: proposal },
): void {
    server = fakeBankServer({
        '/budget/bank/examples': {
            status: 200,
            body: { rows: [school, shop] },
        },
        '/budget/bank/rule': rule,
    });
}

afterEach(() => {
    server.restore();
    vi.restoreAllMocks();
});

describe('BankRuleSheet', () => {
    it('lists the transactions to take examples from and writes the rule of the one chosen', async () => {
        serve();
        renderPage(BankRuleSheet, {
            open: true,
            kind: 'expense',
            accountId: 7,
        });

        const dialog = await screen.findByRole('dialog', {
            name: 'Write it with the AI',
        });
        const choice = await within(dialog).findByRole('checkbox', {
            name: /SCUOLA PASSO A DUE/,
        });
        expect(
            within(dialog).getByRole('checkbox', { name: /ESSELUNGA/ }),
        ).not.toBeChecked();
        expect(
            within(dialog).getByText(/05\/11 · Carta Laura ·· 7731/),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByRole('button', { name: 'Write the rule' }),
        ).toBeDisabled();
        expect(server.request.mock.calls[0]![0].url).toContain('kind=expense');
        expect(server.request.mock.calls[0]![0].url).toContain('account_id=7');

        await userEvent.click(choice);
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Write the rule' }),
        );

        const rule = server.request.mock.calls.find(
            ([config]) => config.method === 'post',
        )![0];
        expect(rule.data).toEqual({
            transactions: [1],
            kind: 'expense',
            account_id: 7,
        });
        expect(
            await within(dialog).findByText('SCUOLA PASSO A DUE'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                'It recognizes the payments to the school.',
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                'It matches 3 movements: 05/09 45,00 €, 05/10 45,00 €, 05/11 45,00 €',
            ),
        ).toBeInTheDocument();
    });

    it('sends the pasted text as examples, one line each', async () => {
        serve();
        renderPage(BankRuleSheet, {
            open: true,
            kind: 'income',
            accountId: null,
        });

        const dialog = await screen.findByRole('dialog');
        await userEvent.click(
            await within(dialog).findByRole('button', {
                name: 'Paste the text',
            }),
        );
        await userEvent.type(
            await within(dialog).findByRole('textbox', {
                name: 'The text of the bank',
            }),
            'ONE TEXT{Enter}OTHER TEXT',
        );
        await settle();
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Write the rule' }),
        );

        const rule = server.request.mock.calls.find(
            ([config]) => config.method === 'post',
        )![0];
        expect(rule.data).toEqual({
            examples: ['ONE TEXT', 'OTHER TEXT'],
            kind: 'income',
        });
    });

    it('gives the rule with Use this rule and closes', async () => {
        serve();
        const onUse = vi.fn();
        const onOpen = vi.fn();
        renderPage(BankRuleSheet, {
            open: true,
            kind: 'expense',
            accountId: null,
            onUse,
            'onUpdate:open': onOpen,
        });

        const dialog = await screen.findByRole('dialog');
        await userEvent.click(
            await within(dialog).findByRole('checkbox', { name: /SCUOLA/ }),
        );
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Write the rule' }),
        );
        await userEvent.click(
            await within(dialog).findByRole('button', {
                name: 'Use this rule',
            }),
        );

        expect(onUse).toHaveBeenCalledWith('SCUOLA PASSO A DUE');
        expect(onOpen).toHaveBeenCalledWith(false);
    });

    it('writes the rule again with Write again', async () => {
        serve();
        renderPage(BankRuleSheet, {
            open: true,
            kind: 'expense',
            accountId: null,
        });

        const dialog = await screen.findByRole('dialog');
        await userEvent.click(
            await within(dialog).findByRole('checkbox', { name: /SCUOLA/ }),
        );
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Write the rule' }),
        );
        await userEvent.click(
            await within(dialog).findByRole('button', { name: 'Write again' }),
        );

        await waitFor(() =>
            expect(
                server.request.mock.calls.filter(
                    ([config]) => config.method === 'post',
                ),
            ).toHaveLength(2),
        );
    });

    it('reads the error of the reading in the sheet', async () => {
        serve({
            status: 422,
            body: {
                errors: {
                    examples: ['The AI service is busy, try again in a while.'],
                },
            },
        });
        renderPage(BankRuleSheet, {
            open: true,
            kind: 'expense',
            accountId: null,
        });

        const dialog = await screen.findByRole('dialog');
        await userEvent.click(
            await within(dialog).findByRole('checkbox', { name: /SCUOLA/ }),
        );
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Write the rule' }),
        );

        expect(await within(dialog).findByRole('alert')).toHaveTextContent(
            'The AI service is busy, try again in a while.',
        );
        expect(
            within(dialog).queryByRole('button', { name: 'Use this rule' }),
        ).not.toBeInTheDocument();
    });

    it('opens with the transaction of the example already chosen, even when it is not in the list', async () => {
        serve();
        const linked = bankRow(9, '2026-11-01', -45, 'ADDEBITO SDD ALTRO');
        renderPage(BankRuleSheet, {
            open: true,
            kind: 'expense',
            accountId: null,
            example: linked,
        });

        const dialog = await screen.findByRole('dialog');

        expect(
            await within(dialog).findByRole('checkbox', {
                name: /ADDEBITO SDD ALTRO/,
            }),
        ).toBeChecked();
        expect(
            await within(dialog).findByRole('checkbox', { name: /SCUOLA/ }),
        ).not.toBeChecked();
    });
});
