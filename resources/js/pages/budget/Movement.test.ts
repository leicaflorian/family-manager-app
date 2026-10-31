import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { danceMovement, movementOptions } from '@/test/fixtures';
import {
    choose,
    openMenu,
    renderPage,
    search,
    settle,
    shown,
} from '@/test/page';
import Movement from './Movement.vue';

const props = {
    movement: danceMovement,
    start: { kind: 'expense', planned_id: '4', date: '2026-10-20' },
    occurrences: [{ value: '2026-11-05', label: '05/11/2026', help: null }],
    options: movementOptions,
};

/** A new expense of 26/10/2026. */
const newExpense = {
    ...props,
    movement: null,
    occurrences: [],
    start: { kind: 'expense', planned_id: null, date: '2026-10-26' },
};

beforeEach(() => {
    // Choosing a planned item asks the page for its due dates
    vi.spyOn(router, 'reload').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
});

/** Writes in a text field, as the person does. */
async function write(label: string, text: string): Promise<void> {
    await fireEvent.update(screen.getByLabelText(label), text);
    await settle();
}

describe('Movement', () => {
    it('shows the movement and a link back to the month it belongs to', () => {
        renderPage(Movement, props);

        expect(screen.getByLabelText('Title')).toHaveValue('Retta di novembre');
        expect(screen.getByRole('link', { name: 'Movements' })).toHaveAttribute(
            'href',
            '/budget/movements?month=2026-10',
        );
    });

    it('opens an empty form for a new expense or income', () => {
        renderPage(Movement, {
            ...props,
            movement: null,
            occurrences: [],
            start: { kind: 'expense', planned_id: null, date: '2026-10-26' },
        });

        expect(
            screen.getByRole('heading', { name: 'New expense or income' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'An expense' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Movements' })).toHaveAttribute(
            'href',
            '/budget/movements?month=2026-10',
        );
    });

    it('starts a new expense from a card, with its category, goes back to the card and asks to come back to it', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Movement, {
            ...newExpense,
            start: {
                ...newExpense.start,
                card: 'health_event:2',
                category_id: '9',
            },
        });

        expect(
            screen.getByRole('link', {
                name: 'Health event · Visite di Giulia',
            }),
        ).toHaveAttribute('href', '/cards/health_event/2');
        expect(
            screen.getByRole('combobox', { name: 'Health event' }),
        ).toHaveValue('Health event · Visite di Giulia');

        await write('Title', 'Visita');
        await write('Amount', '80');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post.mock.calls[0]![1]).toMatchObject({
            category_id: '9',
            card: 'health_event:2',
            back_to_card: true,
        });

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(get).toHaveBeenCalledWith('/cards/health_event/2');
    });

    it('starts a new expense from a card with its holder and organization', async () => {
        renderPage(Movement, {
            ...newExpense,
            start: { ...newExpense.start, card: 'health_event:2' },
        });
        await settle();

        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Salute',
        );
        expect(screen.getByRole('combobox', { name: 'Holder' })).toHaveValue(
            'Giulia Bianchi',
        );
        expect(
            screen.getByRole('combobox', { name: 'Organization' }),
        ).toHaveValue('Studio Ferri');
    });

    it('names the card after the type of the category, offers only its cards and sends the one chosen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Movement, newExpense);

        expect(
            screen.getByRole('combobox', { name: 'Refers to' }),
        ).toBeInTheDocument();

        await choose('Category', 'Auto');

        expect(shown(await openMenu('Vehicle'))).toEqual([
            'None',
            'Vehicle · Panda',
        ]);

        const list = await search('Vehicle', 'Pa');
        await userEvent.click(
            within(list).getByRole('option', { name: 'Vehicle · Panda' }),
        );
        await settle();
        await write('Title', 'Parcheggio');
        await write('Amount', '2,50');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0]![1]).toMatchObject({
            category_id: '10',
            card: 'vehicle:1',
        });
    });

    it('empties the card when the category passes to another type', async () => {
        renderPage(Movement, newExpense);

        await choose('Category', 'Auto');
        await choose('Vehicle', 'Vehicle · Panda');
        await choose('Category', 'Bollette e utenze');

        expect(screen.getByRole('combobox', { name: 'Utility' })).toHaveValue(
            '',
        );
    });

    it('fills the empty category, holder and organization from the card chosen', async () => {
        renderPage(Movement, newExpense);

        await choose('Organization', 'Studio Ferri');
        await choose('Refers to', 'Enrollment · Danza Giulia');

        expect(screen.getByRole('combobox', { name: 'Category' })).toHaveValue(
            'Svago › Sport',
        );
        expect(screen.getByRole('combobox', { name: 'Holder' })).toHaveValue(
            'Giulia Bianchi',
        );
        expect(
            screen.getByRole('combobox', { name: 'Organization' }),
        ).toHaveValue('Studio Ferri');
        expect(
            screen.getByRole('combobox', { name: 'Enrollment' }),
        ).toHaveValue('Enrollment · Danza Giulia');
    });

    it('takes the card of the planned expense chosen', async () => {
        renderPage(Movement, newExpense);

        await choose('Planned expense', 'Bollo Panda');

        expect(screen.getByRole('combobox', { name: 'Vehicle' })).toHaveValue(
            'Vehicle · Panda',
        );
    });

    it('opens from a due date with the card of the planned expense, even of another type than the category, and sends it', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        // The mortgage of the Bianchi: category «Casa e affitto» (home), card its loan
        renderPage(Movement, {
            ...newExpense,
            start: { kind: 'expense', planned_id: '9', date: '2026-11-15' },
            occurrences: [
                { value: '2026-11-15', label: '15/11/2026', help: null },
            ],
            options: {
                ...movementOptions,
                categories: [
                    ...movementOptions.categories,
                    { value: '12', label: 'Casa e affitto', help: null },
                ],
                planned: [
                    ...movementOptions.planned,
                    {
                        kind: 'expense',
                        value: '9',
                        label: 'Mutuo casa',
                        category_id: '12',
                        card: 'loan:1',
                        organization_id: null,
                        financial_account_id: '1',
                        person_id: null,
                        employment_id: null,
                        type: null,
                    },
                ],
                cards: [
                    ...movementOptions.cards,
                    { value: 'home:1', label: 'Home · Casa', help: null },
                    { value: 'loan:1', label: 'Loan · Mutuo casa', help: null },
                ],
                category_card_types: {
                    ...movementOptions.category_card_types,
                    '12': 'home',
                },
                card_type_labels: {
                    ...movementOptions.card_type_labels,
                    home: 'Home',
                    loan: 'Loan',
                },
            },
        });
        await settle();

        expect(screen.getByRole('combobox', { name: 'Home' })).toHaveValue(
            'Loan · Mutuo casa',
        );

        await write('Amount', '612');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0]![1]).toMatchObject({
            planned_id: '9',
            category_id: '12',
            card: 'loan:1',
        });
    });

    it('has no card for an income', async () => {
        renderPage(Movement, newExpense);

        await userEvent.click(
            screen.getByRole('button', { name: 'An income' }),
        );
        await settle();

        expect(
            screen.queryByRole('combobox', { name: 'Refers to' }),
        ).not.toBeInTheDocument();
    });
});
