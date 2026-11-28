import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    bankFilters,
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
            { label: 'Recurring', href: '/budget/planned' },
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
            { label: 'Recurring', href: '/budget/planned' },
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

    it('offers *Pays the deadline* for a vehicle only, with the value of the preset', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            values: {
                ...newPlannedValues,
                title: 'Inspection',
                amount: 80,
                start_date: '2027-03-21',
                category_id: '10',
                card: 'vehicle:1',
                deadline_field: 'vehicles.inspection_due_date',
            },
        });

        await settle();
        expect(
            screen.getByRole('button', { name: 'Pays the deadline' }),
        ).toHaveTextContent('Inspection');
        expect(
            screen.getByText(
                'The payment closes the reminder of this deadline. You update the date on the card, and the expense follows it.',
            ),
        ).toBeInTheDocument();
        const list = await openMenu('Pays the deadline');

        expect(shown(list)).toEqual(['Road tax', 'Inspection', 'No deadline']);
        await userEvent.click(
            within(list).getByRole('option', { name: 'Road tax' }),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/budget/planned/expense',
            expect.objectContaining({
                deadline_field: 'vehicles.road_tax_due_date',
            }),
            expect.anything(),
        );
    });

    it('has no *Pays the deadline* without a card, or with a card of another type', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            values: {
                ...newPlannedValues,
                title: 'Luce',
                amount: 84.2,
                start_date: '2026-11-15',
                category_id: '3',
                card: 'utility:3',
                deadline_field: 'vehicles.road_tax_due_date',
            },
        });

        expect(
            screen.queryByRole('button', { name: 'Pays the deadline' }),
        ).not.toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/budget/planned/expense',
            expect.objectContaining({ deadline_field: null }),
            expect.anything(),
        );
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
    const luce = { ...bankFilters[0]!, taken: 3, missing: null };

    it('shows the box only while the link to the bank is on, with the filter and Edit the filter', () => {
        const without = renderPage(EditPlanned, props);
        expect(
            screen.queryByText('How it shows in the bank'),
        ).not.toBeInTheDocument();
        without.unmount();

        renderPage(EditPlanned, {
            ...props,
            planned: danceRow,
            bank: {
                on: true,
                filter: luce,
                create_url: '/budget/bank/filters/create',
            },
        });
        const where = within(screen.getByRole('group', { name: 'Where' }));

        expect(where.getByText('How it shows in the bank')).toBeInTheDocument();
        expect(where.getByText('Luce')).toBeInTheDocument();
        expect(
            where.getByRole('link', { name: 'Edit the filter' }),
        ).toBeInTheDocument();
    });

    it('offers Create the filter for a saved item without one, and says it is made after saving for a new item', () => {
        const saved = renderPage(EditPlanned, {
            ...props,
            planned: danceRow,
            bank: {
                on: true,
                filter: null,
                create_url: '/budget/bank/filters/create?planned=expense:5',
            },
        });
        expect(
            screen.getByRole('link', { name: 'Create the filter' }),
        ).toBeInTheDocument();
        saved.unmount();

        renderPage(EditPlanned, {
            ...props,
            bank: { on: true, filter: null, create_url: null },
        });
        expect(
            screen.getByText('The filter is made after saving.'),
        ).toBeInTheDocument();
    });

    it('no longer sends bank_match', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPlanned, {
            ...props,
            bank: { on: true, filter: null, create_url: null },
            values: {
                ...newPlannedValues,
                title: 'Retta',
                amount: 45,
                start_date: '2026-11-05',
            },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]![1]).not.toHaveProperty('bank_match');
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
    it('closes with Back or Cancel: no Close button in the title on any layout', () => {
        for (const width of [1280, 820, 390]) {
            screenWidth(width);
            const view = renderPage(EditPlanned, props);

            expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(
                1,
            );
            expect(
                screen.queryByRole('link', { name: 'Close' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Close' }),
            ).not.toBeInTheDocument();
            view.unmount();
        }
    });
});
