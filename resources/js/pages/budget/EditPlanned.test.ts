import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    danceRow,
    newPlannedValues,
    plannedExpenseOptions,
} from '@/test/fixtures';
import { choose, openMenu, renderPage, settle, shown } from '@/test/page';
import EditPlanned from './EditPlanned.vue';

const props = {
    kind: 'expense',
    planned: null,
    values: newPlannedValues,
    options: plannedExpenseOptions,
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Edit a planned expense', () => {
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
