import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { danceMovement, movementOptions } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Movement from './Movement.vue';

const props = {
    movement: danceMovement,
    start: { kind: 'expense', planned_id: '4', date: '2026-10-20' },
    occurrences: [{ value: '2026-11-05', label: '05/11/2026', help: null }],
    options: movementOptions,
};

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
            screen.getByRole('button', { name: 'Expense' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Movements' })).toHaveAttribute(
            'href',
            '/budget/movements?month=2026-10',
        );
    });
});
