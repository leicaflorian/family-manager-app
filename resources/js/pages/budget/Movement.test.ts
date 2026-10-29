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

    it('takes the card of the planned expense chosen', async () => {
        renderPage(Movement, newExpense);

        await choose('Planned expense', 'Bollo Panda');

        expect(screen.getByRole('combobox', { name: 'Vehicle' })).toHaveValue(
            'Vehicle · Panda',
        );
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
