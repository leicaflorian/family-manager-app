import { router } from '@inertiajs/vue3';
import { screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import { danceDeadline } from '@/test/fixtures';
import FmChangeDeadlineSheet from './FmChangeDeadlineSheet.vue';

const props = {
    deadline: { ...danceDeadline, amount: 45, base_amount: null, notes: null },
    open: true,
    action: '/budget/planned/expense/4/2026-12-05/exceptions',
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmChangeDeadlineSheet', () => {
    it('shows the due date and the three choices', async () => {
        renderPage(FmChangeDeadlineSheet, props);

        expect(
            await screen.findByRole('dialog', { name: 'Change this due date' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Danza Giulia · due on 05\/12\/2026/),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Skip this due date')).not.toBeChecked();
        expect(screen.getByLabelText('Different amount')).not.toBeChecked();
        expect(screen.getByLabelText('Different date')).not.toBeChecked();
        expect(screen.getByText('Instead of 45,00 €')).toBeInTheDocument();
        expect(screen.queryByLabelText('New amount')).not.toBeInTheDocument();
    });

    it('opens the new amount and the new date when chosen', async () => {
        renderPage(FmChangeDeadlineSheet, props);
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByLabelText('Different amount'));
        await userEvent.click(screen.getByLabelText('Different date'));
        await settle();

        expect(screen.getByLabelText('New amount')).toBeInTheDocument();
        expect(screen.getByLabelText('New date')).toBeInTheDocument();
    });

    it('turns the other two choices off when the due date is skipped', async () => {
        renderPage(FmChangeDeadlineSheet, props);
        await screen.findByRole('dialog');
        await userEvent.click(screen.getByLabelText('Different amount'));
        await userEvent.click(screen.getByLabelText('Different date'));
        await settle();

        await userEvent.click(screen.getByLabelText('Skip this due date'));
        await settle();

        expect(screen.getByLabelText('Different amount')).not.toBeChecked();
        expect(screen.getByLabelText('Different date')).not.toBeChecked();
        expect(screen.queryByLabelText('New amount')).not.toBeInTheDocument();
    });

    it('sends the choices as the server reads them', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(FmChangeDeadlineSheet, props);
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByLabelText('Different amount'));
        await settle();
        await waitFor(() =>
            expect(screen.getByLabelText('New amount')).toHaveValue(
                '45,00\u00a0€',
            ),
        );
        await userEvent.type(screen.getByLabelText('Note'), 'Saggio di Natale');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0]![0]).toBe(props.action);
        expect(put.mock.calls[0]![1]).toMatchObject({
            skip: false,
            change_amount: true,
            amount: 45,
            change_date: false,
            notes: 'Saggio di Natale',
        });
    });

    it('starts from the changes the due date already has', async () => {
        renderPage(FmChangeDeadlineSheet, {
            ...props,
            deadline: danceDeadline,
        });
        await screen.findByRole('dialog');

        expect(screen.getByLabelText('Different amount')).toBeChecked();
        await waitFor(() =>
            expect(screen.getByLabelText('New amount')).toHaveValue(
                '60,00\u00a0€',
            ),
        );
        expect(screen.getByLabelText('Note')).toHaveValue(
            'Comprende la quota del saggio di Natale',
        );
    });
});
