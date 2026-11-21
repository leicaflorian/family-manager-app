import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankRow, fakeBankServer } from '@/test/bankMatch';
import { renderPage, settle } from '@/test/page';
import BankMatchField from './BankMatchField.vue';

const rows = [
    bankRow(5, '2026-09-05', -72.1),
    bankRow(6, '2026-10-05', -68.9),
    bankRow(7, '2026-11-05', -72.4),
];
let server: ReturnType<typeof fakeBankServer>;

afterEach(() => {
    server?.restore();
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('BankMatchField', () => {
    it('shows the movements the rule matches after the pause', async () => {
        server = fakeBankServer({
            '/budget/bank/matches': { status: 200, body: { count: 3, rows } },
        });
        renderPage(BankMatchField, {
            modelValue: 'SCUOLA',
            kind: 'expense',
            accountId: null,
            ai: true,
        });

        expect(screen.getByLabelText(/How it shows in the bank/)).toHaveValue(
            'SCUOLA',
        );
        expect(
            screen.getByText(
                'The text the bank writes in the list of movements for this payment.',
            ),
        ).toBeInTheDocument();
        expect(
            await screen.findByText(
                'It matches 3 movements: 05/09 72,10 €, 05/10 68,90 €, 05/11 72,40 €',
                undefined,
                { timeout: 2000 },
            ),
        ).toBeInTheDocument();
    });

    it('says when the rule matches no movement', async () => {
        server = fakeBankServer({
            '/budget/bank/matches': {
                status: 200,
                body: { count: 0, rows: [] },
            },
        });
        renderPage(BankMatchField, {
            modelValue: 'ENEL',
            kind: 'expense',
            accountId: null,
            ai: false,
        });

        expect(
            await screen.findByText(
                'It does not match any movement of the last 90 days.',
                undefined,
                { timeout: 2000 },
            ),
        ).toBeInTheDocument();
    });

    it('reads the message of a rule that does not work', async () => {
        server = fakeBankServer({
            '/budget/bank/matches': {
                status: 422,
                body: {
                    errors: {
                        pattern: ['This rule does not work.'],
                    },
                },
            },
        });
        renderPage(BankMatchField, {
            modelValue: '.*',
            kind: 'expense',
            accountId: null,
            ai: true,
        });

        expect(
            await screen.findByText(
                'This rule does not work. Try writing it with the AI.',
                undefined,
                { timeout: 2000 },
            ),
        ).toBeInTheDocument();
    });

    it('suggests the AI for a rule that does not work only when the reading is on', async () => {
        server = fakeBankServer({
            '/budget/bank/matches': {
                status: 422,
                body: { errors: { pattern: ['This rule does not work.'] } },
            },
        });
        const off = renderPage(BankMatchField, {
            modelValue: '.*',
            kind: 'expense',
            accountId: null,
            ai: false,
        });

        expect(
            await screen.findByText('This rule does not work.', undefined, {
                timeout: 2000,
            }),
        ).toBeInTheDocument();
        expect(screen.queryByText(/Try writing it with the AI/)).toBeNull();
        off.unmount();
    });

    it('has the AI button only when the reading is on', () => {
        server = fakeBankServer({});
        const first = renderPage(BankMatchField, {
            modelValue: null,
            kind: 'expense',
            accountId: null,
            ai: true,
        });
        expect(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        ).toBeInTheDocument();
        first.unmount();

        renderPage(BankMatchField, {
            modelValue: null,
            kind: 'expense',
            accountId: null,
            ai: false,
        });
        expect(
            screen.queryByRole('button', { name: 'Write it with the AI' }),
        ).not.toBeInTheDocument();
    });

    it('writes into the field the rule the sheet gives', async () => {
        server = fakeBankServer({
            '/budget/bank/examples': {
                status: 200,
                body: { rows: [bankRow(1, '2026-11-05', -45)] },
            },
            '/budget/bank/rule': {
                status: 200,
                body: {
                    pattern: 'SCUOLA.*DUE',
                    explanation: 'The school.',
                    count: 1,
                    rows: [bankRow(1, '2026-11-05', -45)],
                },
            },
            '/budget/bank/matches': {
                status: 200,
                body: { count: 1, rows: [bankRow(1, '2026-11-05', -45)] },
            },
        });
        const onUpdate = vi.fn();
        renderPage(BankMatchField, {
            modelValue: null,
            'onUpdate:modelValue': onUpdate,
            kind: 'expense',
            accountId: null,
            ai: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        );
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
        await settle();

        expect(onUpdate).toHaveBeenLastCalledWith('SCUOLA.*DUE');
    });

    it('opens the sheet by itself with the transaction of the example', async () => {
        server = fakeBankServer({
            '/budget/bank/examples': { status: 200, body: { rows: [] } },
        });
        renderPage(BankMatchField, {
            modelValue: null,
            kind: 'expense',
            accountId: null,
            ai: true,
            example: bankRow(9, '2026-11-01', -45, 'ADDEBITO SDD ALTRO'),
        });

        const dialog = await screen.findByRole('dialog', {
            name: 'Write it with the AI',
        });

        expect(
            await within(dialog).findByRole('checkbox', {
                name: /ADDEBITO SDD ALTRO/,
            }),
        ).toBeChecked();
    });

    it('does not open the sheet for an example when the reading is off', () => {
        server = fakeBankServer({});
        renderPage(BankMatchField, {
            modelValue: null,
            kind: 'expense',
            accountId: null,
            ai: false,
            example: bankRow(9, '2026-11-01', -45),
        });

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});
