import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { choose, renderPage, withTrail } from '@/test/page';
import ExpenseCategories from './ExpenseCategories.vue';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';

// Shaped like what ExpenseCategoryController sends
const category = (
    id: number,
    name: string,
    card_type: string | null,
    card_label: string | null,
    children: App.Data.ExpenseCategoryData[] = [],
    parent_id: number | null = null,
): App.Data.ExpenseCategoryData => ({
    id,
    name,
    parent_id,
    icon: parent_id ? null : 'car',
    color: '#0797B9',
    card_type,
    card_label,
    children,
});

const categories = [
    category(7, 'Auto', 'vehicle', 'Vehicle', [
        category(20, 'Parcheggi', 'vehicle', 'Vehicle', [], 7),
    ]),
    category(9, 'Spesa', null, null),
];

const cardTypes: App.Data.OptionData[] = [
    { value: 'home', label: 'Home', help: null },
    { value: 'vehicle', label: 'Vehicle', help: null },
    { value: 'health_event', label: 'Health event', help: null },
];

describe('Expense categories', () => {
    it('writes the trail Settings > Expense categories into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(
            ExpenseCategories,
            { categories, cardTypes, canEdit: true },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Expense categories' },
        ]);
    });

    it('shows the tree with the card of each category', () => {
        renderPage(ExpenseCategories, { categories, cardTypes, canEdit: true });

        expect(
            screen.getByRole('heading', { name: 'Expense categories' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Auto')).toBeInTheDocument();
        expect(screen.getByText('Vehicle')).toBeInTheDocument();
    });

    it('chooses the linked card of a main category in the panel', async () => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
        renderPage(ExpenseCategories, { categories, cardTypes, canEdit: true });

        await fireEvent.click(screen.getByRole('button', { name: /^Auto/ }));

        const sheet = await screen.findByRole('dialog');
        expect(within(sheet).getByText('Linked card')).toBeInTheDocument();
        await choose('Linked card', 'Health event');
        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Save' }),
        );

        expect(patch).toHaveBeenCalledWith(
            '/settings/expense-categories/7',
            expect.objectContaining({ card_type: 'health_event' }),
            expect.anything(),
        );
    });

    it('empties the linked card with No card', async () => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
        renderPage(ExpenseCategories, { categories, cardTypes, canEdit: true });

        await fireEvent.click(screen.getByRole('button', { name: /^Auto/ }));
        const sheet = await screen.findByRole('dialog');
        await choose('Linked card', 'No card');
        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Save' }),
        );

        expect(patch).toHaveBeenCalledWith(
            '/settings/expense-categories/7',
            expect.objectContaining({ card_type: null }),
            expect.anything(),
        );
    });

    it('does not open a panel for a subcategory', async () => {
        renderPage(ExpenseCategories, { categories, cardTypes, canEdit: true });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Open Auto' }),
        );

        await fireEvent.click(screen.getByText('Parcheggi'));

        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('shows the tree to a family member without the panel', () => {
        renderPage(ExpenseCategories, {
            categories,
            cardTypes,
            canEdit: false,
        });

        expect(screen.getByText('Auto')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /^Auto/ })).toBeNull();
    });
});

describe('Changes not saved', () => {
    it('asks before closing the panel with the × once the card changes', async () => {
        renderPage(ExpenseCategories, { categories, cardTypes, canEdit: true });
        await fireEvent.click(screen.getByRole('button', { name: /^Auto/ }));
        const sheet = await screen.findByRole('dialog');
        await choose('Linked card', 'Health event');

        await fireEvent.click(
            within(sheet).getByRole('button', { name: 'Close' }),
        );

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
    });
});
