import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { ReminderItem } from '@/lib/reminders';
import { renderPage } from '@/test/page';
import FmReminderRow from './FmReminderRow.vue';

/** A reminder written by hand for Laura, due on Wednesday, as on the board. */
const reminder: ReminderItem = {
    id: 1,
    title: 'Call the plumber for the bathroom quote',
    due_date: '2026-11-04',
    postponed_from: null,
    done: false,
    manual: true,
    area: 'home',
    subject: { label: 'House in via Roma', href: '/cards/home/1' },
    person: { name: 'Laura', initials: 'LB', photo: null },
    next_notice_on: '2026-11-03',
    missing_planned_expense: false,
    href: '/reminders/1/edit',
    edit_href: '/reminders/1/edit',
    card_href: null,
    can_delete: true,
};

/** An automatic reminder of a document, late since 20/10. */
const late: ReminderItem = {
    ...reminder,
    id: 2,
    title: 'Renew the health card',
    due_date: '2026-10-20',
    manual: false,
    area: 'general',
    subject: { label: 'Health card of Giulia', href: '/documents/4' },
    person: { name: 'Giulia', initials: 'GB', photo: null },
    next_notice_on: null,
    href: '/documents/4',
    edit_href: null,
    card_href: '/documents/4',
    can_delete: false,
};

beforeEach(() => {
    // Today is the day of the board, Monday 02/11, in the time of Rome
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-02T10:00:00+01:00'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('FmReminderRow', () => {
    it('shows what to do, the person, the day with its weekday and how far it is', () => {
        renderPage(FmReminderRow, { reminder });

        expect(
            screen.getByRole('link', {
                name: 'Call the plumber for the bathroom quote',
            }),
        ).toHaveAttribute('href', '/reminders/1/edit');
        expect(screen.getByText('Laura')).toBeInTheDocument();
        expect(screen.getByText('Wed 04/11')).toBeInTheDocument();
        expect(screen.getByText('in 2 days')).not.toHaveClass('text-overdue');
    });

    it('says the next notice in a blue label, or that no other notice is left', () => {
        const first = renderPage(FmReminderRow, { reminder });
        expect(screen.getByText('Notice on 03/11')).toHaveClass(
            'bg-action-soft',
        );
        first.unmount();

        renderPage(FmReminderRow, { reminder: late });
        expect(screen.getByText('No other notice')).toBeInTheDocument();
    });

    it('says in red the whole date and how long ago, once late', () => {
        renderPage(FmReminderRow, { reminder: late });

        expect(screen.getByText('20/10/2026')).toHaveClass('text-overdue');
        expect(screen.getByText('13 days ago')).toHaveClass('text-overdue');
    });

    it('shows the thing it refers to, or Written by hand for one written by hand about nothing', () => {
        const first = renderPage(FmReminderRow, { reminder: late });
        expect(
            screen.getByRole('link', { name: 'Health card of Giulia' }),
        ).toHaveAttribute('href', '/documents/4');
        expect(screen.queryByText('Written by hand')).toBeNull();
        first.unmount();

        const second = renderPage(FmReminderRow, { reminder });
        expect(
            screen.getByRole('link', { name: 'House in via Roma' }),
        ).toHaveAttribute('href', '/cards/home/1');
        expect(screen.queryByText('Written by hand')).toBeNull();
        second.unmount();

        renderPage(FmReminderRow, { reminder: { ...reminder, subject: null } });
        expect(screen.getByText('Written by hand')).toBeInTheDocument();
    });

    it('says the deadline a postponed reminder comes from', () => {
        renderPage(FmReminderRow, {
            reminder: { ...late, postponed_from: '2026-10-20' },
        });

        expect(
            screen.getByText('From the deadline of 20/10'),
        ).toBeInTheDocument();
    });

    it('says when the planned expense is missing', () => {
        renderPage(FmReminderRow, {
            reminder: { ...late, missing_planned_expense: true },
        });

        expect(
            screen.getByText('The planned expense is missing'),
        ).toBeInTheDocument();
    });

    it('is for everyone without a person', () => {
        renderPage(FmReminderRow, { reminder: { ...reminder, person: null } });

        expect(screen.getByText('Everyone')).toBeInTheDocument();
    });

    it('marks it done with the switch', async () => {
        const view = renderPage(FmReminderRow, { reminder });

        const toggle = screen.getByRole('switch', {
            name: 'Done: Call the plumber for the bathroom quote',
        });
        expect(toggle).not.toBeChecked();
        await userEvent.click(toggle);

        expect(view.emitted()).toHaveProperty('done');
    });

    it('keeps the switch on while the server marks it done', () => {
        renderPage(FmReminderRow, { reminder, pending: true });

        const toggle = screen.getByRole('switch');
        expect(toggle).toBeChecked();
        expect(toggle).toBeDisabled();
    });

    it('postpones a late one from Postpone: tomorrow, a week, a month, or a day to choose', async () => {
        const view = renderPage(FmReminderRow, { reminder: late });

        expect(
            screen.queryByRole('button', { name: /More actions/ }),
        ).toBeNull();
        await userEvent.click(screen.getByRole('button', { name: 'Postpone' }));
        const menu = await screen.findByRole('menu');
        expect(
            within(menu)
                .getAllByRole('menuitem')
                .map((item) => item.textContent?.trim()),
        ).toEqual(['Tomorrow', 'In a week', 'In a month', 'Choose a date…']);
        await userEvent.click(
            within(menu).getByRole('menuitem', { name: 'In a week' }),
        );

        expect(view.emitted().postpone).toEqual([['2026-11-09']]);

        await userEvent.click(screen.getByRole('button', { name: 'Postpone' }));
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Choose a date…' }),
        );
        expect(view.emitted()).toHaveProperty('choose-date');
    });

    it('has the ⋯ menu on one still to come: postpone, edit and delete', async () => {
        const view = renderPage(FmReminderRow, { reminder });

        expect(screen.queryByRole('button', { name: 'Postpone' })).toBeNull();
        await userEvent.click(
            screen.getByRole('button', {
                name: 'More actions on Call the plumber for the bathroom quote',
            }),
        );
        const menu = await screen.findByRole('menu');
        expect(
            within(menu)
                .getAllByRole('menuitem')
                .map((item) => item.textContent?.trim()),
        ).toEqual([
            'Tomorrow',
            'In a week',
            'In a month',
            'Choose a date…',
            'Edit',
            'Delete',
        ]);
        expect(
            within(menu).getByRole('menuitem', { name: 'Edit' }),
        ).toHaveAttribute('href', '/reminders/1/edit');
        await userEvent.click(
            within(menu).getByRole('menuitem', { name: 'Tomorrow' }),
        );
        expect(view.emitted().postpone).toEqual([['2026-11-03']]);

        await userEvent.click(
            screen.getByRole('button', { name: /More actions/ }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Delete' }),
        );
        expect(view.emitted()).toHaveProperty('delete');
    });

    it('opens the card of an automatic reminder from the ⋯ menu, without edit or delete', async () => {
        renderPage(FmReminderRow, {
            reminder: { ...late, due_date: '2026-11-20' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: /More actions/ }),
        );
        const menu = await screen.findByRole('menu');

        expect(
            within(menu).getByRole('menuitem', { name: 'Open the card' }),
        ).toHaveAttribute('href', '/documents/4');
        expect(within(menu).queryByRole('menuitem', { name: 'Edit' })).toBe(
            null,
        );
        expect(
            within(menu).queryByRole('menuitem', { name: 'Delete' }),
        ).toBeNull();
    });

    it('offers to reopen a reminder that is done, without the switch or the menus', async () => {
        const view = renderPage(FmReminderRow, {
            reminder: { ...reminder, done: true },
        });

        expect(screen.queryByRole('switch')).toBeNull();
        expect(screen.queryByRole('button', { name: /More actions/ })).toBe(
            null,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Reopen' }));

        expect(view.emitted()).toHaveProperty('reopen');
    });
});
