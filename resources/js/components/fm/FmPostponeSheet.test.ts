import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import type { ReminderItem } from '@/lib/reminders';
import { toReminderItem } from '@/lib/reminders';
import { healthCardReminder } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import FmPostponeSheet from './FmPostponeSheet.vue';

/** The health card of Giulia, late since 30/09/2026. */
const reminder: ReminderItem = toReminderItem(healthCardReminder);

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-02T10:00:00+01:00'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('FmPostponeSheet', () => {
    it('names the reminder and proposes tomorrow', async () => {
        renderPage(FmPostponeSheet, { reminder, open: true });

        expect(
            await screen.findByRole('dialog', { name: 'Postpone' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Renew the health card')).toBeInTheDocument();
        expect(
            screen.getByText('Tessera sanitaria di Giulia · 30/09/2026'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Postpone to')).toHaveValue('03/11/2026');
    });

    it('sends the day to the address of the reminder', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmPostponeSheet, { reminder, open: true });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/reminders/1/postpone',
            { to: '2026-11-03' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the message of the server under the day, and stays open', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                to: 'Choose a day after today.',
            });
            (options as VisitOptions).onFinish?.(
                {} as Parameters<NonNullable<VisitOptions['onFinish']>>[0],
            );
        });
        renderPage(FmPostponeSheet, { reminder, open: true });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(
            screen.getByText('Choose a day after today.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('closes after saving', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onSuccess?.(
                {} as Parameters<NonNullable<VisitOptions['onSuccess']>>[0],
            );
        });
        const view = renderPage(FmPostponeSheet, { reminder, open: true });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted()['update:open']).toEqual([[false]]);
    });

    it('asks before closing with the × when the day changed', async () => {
        const view = renderPage(FmPostponeSheet, { reminder, open: true });
        await screen.findByRole('dialog');
        const field = screen.getByLabelText('Postpone to');
        await userEvent.clear(field);
        await userEvent.type(field, '15/11/2026');
        await settle();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        await settle();
        expect(view.emitted()['update:open']).toBeUndefined();
    });
});
