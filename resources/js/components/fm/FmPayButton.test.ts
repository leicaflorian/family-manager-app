import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { danceDeadline, lateDeadline } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import FmPayButton from './FmPayButton.vue';

describe('FmPayButton', () => {
    it('opens the oldest open due date with the panel of I paid it', () => {
        renderPage(FmPayButton, {
            deadlines: [lateDeadline],
            label: 'Record payment',
        });

        expect(
            screen.getByRole('link', { name: 'Record payment' }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/7/occurrences/2026-10-23?pay=1',
        );
    });

    it('asks which planned expense when there are several', async () => {
        renderPage(FmPayButton, {
            deadlines: [lateDeadline, danceDeadline],
            label: 'Record payment',
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Record payment' }),
        );

        expect(
            await screen.findByRole('menuitem', { name: 'Luce' }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/7/occurrences/2026-10-23?pay=1',
        );
        expect(
            screen.getByRole('menuitem', { name: 'Retta danza' }),
        ).toHaveAttribute(
            'href',
            '/budget/planned/expense/4/occurrences/2026-12-05?pay=1',
        );
    });

    it('shows nothing without an open due date', () => {
        renderPage(FmPayButton, { deadlines: [], label: 'Record payment' });

        expect(screen.queryByText('Record payment')).not.toBeInTheDocument();
    });
});
