import { fireEvent, render, screen } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { ReminderItem } from '@/lib/reminders';
import FmReminderRow from './FmReminderRow.vue';

const reminder: ReminderItem = {
    id: 1,
    title: 'Call the plumber for the bathroom quote',
    due_date: '2026-10-31',
    done: false,
    manual: true,
    area: 'home',
    subject: { label: 'House in via Roma', href: '/cards/home/1' },
    person: { name: 'Laura', initials: 'LB', photo: null },
    next_notice_on: '2026-10-30',
    notices_sent: 0,
    missing_planned_expense: false,
    href: '/reminders/1/edit',
};

describe('FmReminderRow', () => {
    beforeEach(() => {
        // Today is the day of the board, in the time of Rome
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
        vi.spyOn(router, 'visit').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('shows what to do, the thing, the person and how far it is', () => {
        render(FmReminderRow, { props: { reminder } });

        expect(
            screen.getByRole('link', {
                name: 'Call the plumber for the bathroom quote',
            }),
        ).toHaveAttribute('href', '/reminders/1/edit');
        expect(
            screen.getByRole('link', { name: 'House in via Roma' }),
        ).toHaveAttribute('href', '/cards/home/1');
        expect(screen.getByText('Laura')).toBeInTheDocument();
        expect(screen.getByText('in 5 days')).toBeInTheDocument();
    });

    it('says the next notice', () => {
        render(FmReminderRow, { props: { reminder } });

        expect(
            screen.getByText(/^First notice on \d\d\/\d\d$/),
        ).toBeInTheDocument();
    });

    it('tells a reminder written by hand from an automatic one', () => {
        const first = render(FmReminderRow, { props: { reminder } });
        expect(
            screen.getByRole('img', { name: 'Written by hand' }),
        ).toBeInTheDocument();
        first.unmount();

        render(FmReminderRow, {
            props: { reminder: { ...reminder, manual: false } },
        });
        expect(
            screen.queryByRole('img', { name: 'Written by hand' }),
        ).not.toBeInTheDocument();
    });

    it('says in red how late it is and that no other notice is left', () => {
        render(FmReminderRow, {
            props: {
                reminder: {
                    ...reminder,
                    due_date: '2026-09-30',
                    next_notice_on: null,
                    notices_sent: 3,
                },
            },
        });

        expect(screen.getByText('overdue by 26 days')).toHaveClass(
            'text-overdue',
        );
        expect(screen.getByText('No other notice')).toBeInTheDocument();
    });

    it('says when the planned expense is missing', () => {
        render(FmReminderRow, {
            props: { reminder: { ...reminder, missing_planned_expense: true } },
        });

        expect(
            screen.getByText('The planned expense is missing'),
        ).toBeInTheDocument();
    });

    it('sends done from the circle', async () => {
        const view = render(FmReminderRow, { props: { reminder } });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Mark as done' }),
        );

        expect(view.emitted()).toHaveProperty('done');
    });

    it('sends open when the title is tapped', async () => {
        const view = render(FmReminderRow, { props: { reminder } });

        await fireEvent.click(
            screen.getByRole('link', {
                name: 'Call the plumber for the bathroom quote',
            }),
        );

        expect(view.emitted()).toHaveProperty('open');
    });

    it('offers to reopen a reminder that is done, and not to mark it', async () => {
        const view = render(FmReminderRow, {
            props: { reminder: { ...reminder, done: true } },
        });

        expect(
            screen.queryByRole('button', { name: 'Mark as done' }),
        ).not.toBeInTheDocument();
        await fireEvent.click(screen.getByRole('button', { name: 'Reopen' }));

        expect(view.emitted()).toHaveProperty('reopen');
    });
});
