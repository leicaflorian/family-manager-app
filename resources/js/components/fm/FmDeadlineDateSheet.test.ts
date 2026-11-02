import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import { agendaEntries } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import FmDeadlineDateSheet from './FmDeadlineDateSheet.vue';

/** The expired health card of Giulia, 30/09/2026, which the person may change. */
const healthCard = agendaEntries[0];

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmDeadlineDateSheet', () => {
    it('corrects the date starting from the one the card has', async () => {
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });

        expect(
            await screen.findByRole('dialog', { name: 'Update the date' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Tessera sanitaria di Giulia'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Document expiry · 30/09/2026'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Correct date')).toHaveValue('30/09/2026');
    });

    it('proposes the same day a year later for a renewal', async () => {
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: true,
            open: true,
        });

        expect(
            await screen.findByRole('dialog', { name: 'Renewed' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('New expiry date')).toHaveValue(
            '30/09/2027',
        );
        expect(
            screen.getByText(
                'The reminder is marked done, and a new one waits for the new date.',
            ),
        ).toBeInTheDocument();
    });

    it('sends the date and whether it is a renewal to the address of the entry', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: true,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/agenda/deadlines/documents.expiry_date/17',
            { date: '2027-09-30', renewed: true },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('a correction is not a renewal', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put.mock.calls[0][1]).toEqual({
            date: '2026-09-30',
            renewed: false,
        });
    });

    it('shows the message of the server under the date', async () => {
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                date: 'The date is not valid.',
            });
            (options as VisitOptions).onFinish?.(
                {} as Parameters<NonNullable<VisitOptions['onFinish']>>[0],
            );
        });
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(screen.getByText('The date is not valid.')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('closes after saving', async () => {
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onSuccess?.(
                {} as Parameters<NonNullable<VisitOptions['onSuccess']>>[0],
            );
        });
        const view = renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted()['update:open']).toEqual([[false]]);
    });

    it('asks before closing with the × when the date changed', async () => {
        const view = renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');
        const field = screen.getByLabelText('Correct date');
        await userEvent.clear(field);
        await userEvent.type(field, '15/10/2026');
        await settle();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        await settle();
        expect(view.emitted()['update:open']).toBeUndefined();
    });
});
