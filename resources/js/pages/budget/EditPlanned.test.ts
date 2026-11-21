import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    danceRow,
    newPlannedValues,
    plannedExpenseOptions,
} from '@/test/fixtures';
import {
    choose,
    openMenu,
    renderPage,
    withTrail,
    settle,
    shown,
    tapCancel,
    screenWidth,
} from '@/test/page';
import EditPlanned from './EditPlanned.vue';
import { hasChanges } from '@/lib/unsavedChanges';
import { bankRow, fakeBankServer } from '@/test/bankMatch';

const props = {
    kind: 'expense',
    planned: null,
    values: newPlannedValues,
    options: plannedExpenseOptions,
};

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Edit a planned expense', () => {
    it('writes the trail of a new and of a changed planned expense into the top bar', () => {
        const created = withTrail();
        const first = renderPage(EditPlanned, props, created.provide);

        expect(created.state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Planned expenses and incomes', href: '/budget/planned' },
            { label: 'New planned expense' },
        ]);
        first.unmount();

        const changed = withTrail();
        renderPage(
            EditPlanned,
            { ...props, planned: danceRow },
            changed.provide,
        );

        expect(changed.state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Planned expenses and incomes', href: '/budget/planned' },
            { label: 'Retta danza', href: '/budget/planned/expense/4' },
            { label: 'Edit Retta danza' },
        ]);
    });

    it('shows the three groups, with the card in *Where*', () => {
        renderPage(EditPlanned, props);

        expect(
            screen.getByRole('heading', {
                name: 'New planned expense',
                level: 1,
            }),
        ).toBeInTheDocument();
        for (const group of ['What', 'When', 'Where']) {
            expect(
                screen.getByRole('group', { name: group }),
            ).toBeInTheDocument();
        }
        expect(
            screen.queryByRole('group', { name: 'Refers to' }),
        ).not.toBeInTheDocument();
        expect(
            within(screen.getByRole('group', { name: 'Where' })).getByRole(
                'combobox',
                { name: 'Refers to' },
            ),
        ).toBeInTheDocument();
    });

    it('starts from the card of a card page, with its category and the field named after its type', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            values: {
                ...newPlannedValues,
                title: 'Luce - Edison',
                amount: 84.2,
                start_date: '2026-11-15',
                category_id: '3',
                card: 'utility:3',
            },
        });

        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Bollette e utenze',
        );
        expect(screen.getByRole('combobox', { name: 'Utility' })).toHaveValue(
            'Utility · Luce - Edison',
        );
        expect(shown(await openMenu('Utility'))).toEqual([
            'None',
            'Utility · Luce - Edison',
        ]);

        await choose('Category', 'Casa e affitto');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/budget/planned/expense',
            expect.objectContaining({ category_id: '1', card: null }),
            expect.anything(),
        );
    });

    it('sends the card and the starting values, and a switch that was not touched as off', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            values: {
                ...newPlannedValues,
                title: 'Netflix',
                amount: 13.99,
                start_date: '2026-11-04',
                organization_id: '5',
                financial_account_id: '2',
                person_id: '3',
                card: 'subscription:7',
            },
        });

        await choose('Category', 'Svago');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/budget/planned/expense',
            expect.objectContaining({
                title: 'Netflix',
                amount: '13.99',
                start_date: '2026-11-04',
                organization_id: '5',
                financial_account_id: '2',
                person_id: '3',
                card: 'subscription:7',
                category_id: '2',
                is_recurring: true,
                is_estimated: false,
                interval: 1,
                interval_unit: 'month',
            }),
            expect.anything(),
        );
    });

    it('fills the empty category, holder and organization from the card chosen', async () => {
        renderPage(EditPlanned, {
            ...props,
            values: { ...newPlannedValues, person_id: '3' },
        });

        await choose('Refers to', 'Utility · Luce - Edison');

        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Bollette e utenze',
        );
        expect(screen.getByRole('combobox', { name: 'Holder' })).toHaveValue(
            'Laura Bianchi',
        );
        expect(screen.getByRole('combobox', { name: 'Utility' })).toHaveValue(
            'Utility · Luce - Edison',
        );
    });

    it('asks for the title, the amount and the start before sending', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPlanned, props);

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).not.toHaveBeenCalled();
        expect(screen.getAllByText('This field is required.')).toHaveLength(3);
    });

    it('changes a planned expense with PUT, starting from its values', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            planned: danceRow,
            values: {
                ...newPlannedValues,
                title: 'Retta danza',
                amount: 45,
                start_date: '2025-09-05',
                financial_account_id: '2',
                person_id: '4',
                card: null,
            },
        });

        expect(
            screen.getByRole('heading', { name: 'Edit Retta danza', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Title')).toHaveValue('Retta danza');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledWith(
            '/budget/planned/expense/4',
            expect.objectContaining({
                title: 'Retta danza',
                amount: '45',
                is_estimated: false,
            }),
            expect.anything(),
        );
    });

    it('sends the commission of a planned expense, added to its amount', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            planned: danceRow,
            values: {
                ...newPlannedValues,
                title: 'Affitto',
                amount: 650,
                fee_amount: 1,
                start_date: '2025-09-05',
            },
        });

        expect(
            await screen.findByLabelText('Commission (optional)'),
        ).toHaveValue('1\u00a0€');
        expect(
            screen.getByText('It is added to the amount.'),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledWith(
            '/budget/planned/expense/4',
            expect.objectContaining({ amount: '650', fee_amount: '1' }),
            expect.anything(),
        );
    });

    it('hides how often until it repeats', async () => {
        renderPage(EditPlanned, props);

        expect(
            screen.getByRole('button', { name: 'Months' }),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('switch', { name: 'Repeats' }));
        await settle();

        expect(
            screen.queryByRole('button', { name: 'Months' }),
        ).not.toBeInTheDocument();
    });

    it('asks for the type of an income and offers its employments', () => {
        renderPage(EditPlanned, {
            ...props,
            kind: 'income',
            values: {
                ...newPlannedValues,
                type: 'salary',
                employment_id: null,
                fee_amount: undefined,
            },
            options: {
                ...plannedExpenseOptions,
                categories: [],
                cards: [],
                employments: [
                    { value: '3', label: 'Marco - Studio Rinaldi', help: null },
                ],
                income_types: [
                    { value: 'salary', label: 'Salary', help: null },
                ],
            },
        });

        expect(
            screen.getByRole('heading', {
                name: 'New planned income',
                level: 1,
            }),
        ).toBeInTheDocument();
        expect(
            screen.queryByLabelText('Commission (optional)'),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Salary' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(
            screen.queryByRole('combobox', { name: 'Category' }),
        ).not.toBeInTheDocument();
        expect(
            within(screen.getByRole('group', { name: 'Refers to' })).getByRole(
                'combobox',
                { name: 'Employment' },
            ),
        ).toBeInTheDocument();
    });
});

describe('How it shows in the bank', () => {
    const bank = { on: true, ai: true };
    let server: ReturnType<typeof fakeBankServer>;

    afterEach(() => {
        server?.restore();
        window.history.replaceState(null, '', '/');
    });

    it('is in *Where* only while the link to the bank is on, and with the AI button only while the reading is on', () => {
        server = fakeBankServer({});
        const without = renderPage(EditPlanned, props);
        expect(
            screen.queryByLabelText(/How it shows in the bank/),
        ).not.toBeInTheDocument();
        without.unmount();

        const noAi = renderPage(EditPlanned, {
            ...props,
            bank: { on: true, ai: false },
        });
        expect(
            within(screen.getByRole('group', { name: 'Where' })).getByLabelText(
                /How it shows in the bank/,
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Write it with the AI' }),
        ).not.toBeInTheDocument();
        noAi.unmount();

        renderPage(EditPlanned, { ...props, bank });
        expect(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        ).toBeInTheDocument();
    });

    it('sends the rule with Save, and counts it as a change not saved', async () => {
        server = fakeBankServer({
            '/budget/bank/matches': {
                status: 200,
                body: { count: 0, rows: [] },
            },
        });
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            bank,
            values: {
                ...newPlannedValues,
                title: 'Retta',
                amount: 45,
                start_date: '2026-11-05',
            },
        });
        expect(hasChanges()).toBe(false);

        await userEvent.type(
            screen.getByLabelText(/How it shows in the bank/),
            'SCUOLA.*DUE',
        );
        await settle();
        expect(hasChanges()).toBe(true);
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/budget/planned/expense',
            expect.objectContaining({ bank_match: 'SCUOLA.*DUE' }),
            expect.anything(),
        );
    });

    it('looks at the movements that go the way of the form and at the account chosen', async () => {
        server = fakeBankServer({
            '/budget/bank/matches': {
                status: 200,
                body: {
                    count: 1,
                    rows: [bankRow(1, '2026-11-05', 1500, 'STIPENDIO')],
                },
            },
        });
        renderPage(EditPlanned, {
            ...props,
            kind: 'income',
            bank,
            values: {
                ...newPlannedValues,
                type: 'salary',
                financial_account_id: '2',
                bank_match: 'STIPENDIO',
            },
        });

        expect(
            await screen.findByText(
                'It matches 1 movement: 05/11 1.500,00 €',
                undefined,
                { timeout: 2000 },
            ),
        ).toBeInTheDocument();
        const url = String(server.request.mock.calls[0]![0].url);
        expect(url).toContain('kind=income');
        expect(url).toContain('account_id=2');
    });

    it('opens the panel with the movement of rule_from and takes the parameter out of the address', async () => {
        server = fakeBankServer({
            '/budget/bank/examples': { status: 200, body: { rows: [] } },
        });
        const replace = vi
            .spyOn(router, 'replace')
            .mockImplementation(() => {});
        window.history.replaceState(
            null,
            '',
            '/budget/planned/expense/create?rule_from=9',
        );

        renderPage(EditPlanned, {
            ...props,
            bank,
            rule_example: bankRow(9, '2026-11-01', -45, 'ADDEBITO SDD ALTRO'),
        });

        const dialog = await screen.findByRole('dialog', {
            name: 'Write it with the AI',
        });
        expect(
            await within(dialog).findByRole('checkbox', {
                name: /ADDEBITO SDD ALTRO/,
            }),
        ).toBeChecked();
        expect(replace).toHaveBeenCalledWith(
            expect.objectContaining({
                url: '/budget/planned/expense/create',
                preserveState: true,
            }),
        );
    });
});

describe('Changes not saved', () => {
    it('has them once a field changes, and gives them up with Cancel', async () => {
        renderPage(EditPlanned, props);
        expect(hasChanges()).toBe(false);

        await choose('Category', 'Svago');
        await settle();
        expect(hasChanges()).toBe(true);

        await tapCancel();
        expect(hasChanges()).toBe(false);
    });
});

describe('EditPlanned on the computer', () => {
    it('has no close button above the title on the computer, and has it on the tablet and the phone', () => {
        for (const [width, shown] of [
            [1280, false],
            [820, true],
            [390, true],
        ] as const) {
            screenWidth(width);
            const view = renderPage(EditPlanned, props);

            expect(screen.queryByRole('link', { name: 'Close' }) !== null).toBe(
                shown,
            );
            view.unmount();
        }
    });
});
