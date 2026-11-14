import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmAgendaPanel from './FmAgendaPanel.vue';

/** The reminder of an entry, as the server sends it. */
function reminder(
    change: Partial<App.Data.AgendaReminderData> = {},
): App.Data.AgendaReminderData {
    return {
        state: 'open' as App.Enums.AgendaReminderState,
        next_notice: null,
        id: 7,
        notice_days: ['2026-10-31', '2026-11-13'],
        recipients: ['Marco', 'Laura'],
        done_on: null,
        done_by: null,
        can_edit: true,
        can_delete: true,
        ...change,
    };
}

/** An entry of the Agenda; a deadline of a card unless changed. */
function entry(
    change: Partial<App.Data.AgendaEntryData> = {},
): App.Data.AgendaEntryData {
    return {
        key: 'deadline:policies.expiry_date:4:2026-11-14',
        kind: 'deadline',
        date: '2026-11-14',
        title: 'Policy renewal',
        subtitle: 'Polizza auto - Allianz',
        name: 'Polizza auto - Allianz',
        detail: 'Policy renewal',
        area: 'vehicles',
        person_id: 1,
        person: 'Marco',
        amount: null,
        estimated: false,
        reminder_done: false,
        reminder: reminder(),
        href: '/cards/4',
        update_url: '/agenda/deadlines/policies.expiry_date/4',
        notes: null,
        ...change,
    };
}

describe('FmAgendaPanel', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-11-02T10:00:00+01:00'));
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    describe('a deadline', () => {
        it('shows the name, the date, the person, the notices and the card', () => {
            renderPage(FmAgendaPanel, { entry: entry() });

            expect(
                screen.getByRole('heading', { name: 'Polizza auto - Allianz' }),
            ).toBeInTheDocument();
            expect(screen.getByText('Policy renewal')).toBeInTheDocument();
            expect(screen.getByText(/14\/11\/2026/)).toBeInTheDocument();
            expect(screen.getByText(/in 12 days/)).toBeInTheDocument();
            expect(screen.getByText('Marco')).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Notice on 31/10 and on 13/11 · to Marco and Laura',
                ),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: 'Open the card' }),
            ).toHaveAttribute('href', '/cards/4');
        });

        it('has Done, Postpone, Update the date and Renewed, even with a far date', async () => {
            const view = renderPage(FmAgendaPanel, {
                entry: entry({ date: '2027-03-01' }),
            });
            const user = userEvent.setup();

            expect(
                screen.getByRole('button', { name: 'Done' }),
            ).toBeInTheDocument();
            await user.click(screen.getByRole('button', { name: 'Postpone' }));
            await user.click(
                screen.getByRole('button', { name: 'Update the date' }),
            );
            await user.click(screen.getByRole('button', { name: 'Renewed' }));

            expect(view.emitted()).toMatchObject({
                postpone: [[]],
                updateDate: [[]],
                renewed: [[]],
            });
        });

        it('without the address to update the card, it cannot update the date nor renew', () => {
            renderPage(FmAgendaPanel, { entry: entry({ update_url: null }) });

            expect(
                screen.queryByRole('button', { name: 'Update the date' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Renewed' }),
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Done' }),
            ).toBeInTheDocument();
        });

        it('without an automatic reminder, it has no Reminder, Done nor Postpone', () => {
            renderPage(FmAgendaPanel, { entry: entry({ reminder: null }) });

            expect(screen.queryByText('Reminder')).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Done' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Postpone' }),
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Renewed' }),
            ).toBeInTheDocument();
        });

        it('Done is sent to the server and closes the panel', async () => {
            const post = vi
                .spyOn(router, 'post')
                .mockImplementation((_url, _data, options) => {
                    options?.onSuccess?.({} as never);
                });
            const view = renderPage(FmAgendaPanel, { entry: entry() });

            await userEvent.click(screen.getByRole('button', { name: 'Done' }));

            expect(post).toHaveBeenCalledWith(
                '/reminders/7/done',
                {},
                expect.objectContaining({
                    preserveScroll: true,
                    preserveUrl: true,
                }),
            );
            expect(view.emitted().close).toHaveLength(1);
        });
    });

    describe('a payment', () => {
        const payment = entry({
            key: 'payment:3:2026-10-28',
            kind: 'payment',
            date: '2026-10-28',
            name: 'Gas - Hera',
            detail: 'Bolletta',
            amount: 84.2,
            reminder: null,
            update_url: null,
            href: '/budget/planned/expense/3/occurrences/2026-10-28',
        });

        it('shows the amount, I paid it and a link to the due date, without Done', async () => {
            const view = renderPage(FmAgendaPanel, { entry: payment });

            expect(screen.getByText(/84,20/)).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: 'Open the due date' }),
            ).toHaveAttribute(
                'href',
                '/budget/planned/expense/3/occurrences/2026-10-28',
            );
            expect(
                screen.queryByRole('button', { name: 'Done' }),
            ).not.toBeInTheDocument();
            await userEvent.click(
                screen.getByRole('button', { name: 'I paid it' }),
            );

            expect(view.emitted().pay).toHaveLength(1);
        });

        it('shows an estimated amount with the tilde', () => {
            renderPage(FmAgendaPanel, {
                entry: { ...payment, estimated: true },
            });

            expect(screen.getByText(/~84,20/)).toBeInTheDocument();
        });
    });

    describe('a task', () => {
        const task = entry({
            key: 'task:9',
            kind: 'task',
            name: 'Call the plumber',
            detail: 'Task',
            notes: 'Ask about the water heater too',
            update_url: null,
            href: '/reminders/9/edit',
            reminder: reminder({ id: 9 }),
        });

        it('shows the notes, Done, Postpone and a menu with Edit and Delete', async () => {
            renderPage(FmAgendaPanel, { entry: task });
            const user = userEvent.setup();

            expect(
                screen.getByText('Ask about the water heater too'),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Done' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Postpone' }),
            ).toBeInTheDocument();
            await user.click(
                screen.getByRole('button', { name: 'More actions' }),
            );
            const menu = await screen.findByRole('menu');

            expect(
                within(menu).getByRole('menuitem', { name: 'Edit' }),
            ).toHaveAttribute('href', '/reminders/9/edit');
            expect(
                within(menu).getByRole('menuitem', { name: 'Delete' }),
            ).toBeInTheDocument();
        });

        it('has no Delete when the person cannot delete', async () => {
            renderPage(FmAgendaPanel, {
                entry: {
                    ...task,
                    reminder: reminder({ id: 9, can_delete: false }),
                },
            });
            await userEvent.click(
                screen.getByRole('button', { name: 'More actions' }),
            );
            const menu = await screen.findByRole('menu');

            expect(
                within(menu).queryByRole('menuitem', { name: 'Delete' }),
            ).not.toBeInTheDocument();
        });

        it('Delete asks first, then deletes the reminder', async () => {
            const destroy = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => {});
            renderPage(FmAgendaPanel, { entry: task });
            const user = userEvent.setup();

            await user.click(
                screen.getByRole('button', { name: 'More actions' }),
            );
            await user.click(
                await screen.findByRole('menuitem', { name: 'Delete' }),
            );
            expect(destroy).not.toHaveBeenCalled();
            await user.click(
                await screen.findByRole('button', { name: 'Delete the task' }),
            );

            expect(destroy).toHaveBeenCalledWith(
                '/reminders/9',
                expect.anything(),
            );
        });
    });

    describe('done', () => {
        const closed = entry({
            reminder: reminder({
                state: 'done' as App.Enums.AgendaReminderState,
                done_on: '2026-11-01',
                done_by: 'Laura',
            }),
        });

        it('says when and by whom, and has Reopen instead of Done and Postpone', async () => {
            const reopen = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => {});
            renderPage(FmAgendaPanel, { entry: closed, done: true });

            expect(
                screen.getByText('Done on 01/11 · Laura'),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Done' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Postpone' }),
            ).not.toBeInTheDocument();
            await userEvent.click(
                screen.getByRole('button', { name: 'Reopen' }),
            );

            expect(reopen).toHaveBeenCalledWith(
                '/reminders/7/done',
                expect.anything(),
            );
        });
    });

    it('the close button emits close', async () => {
        const view = renderPage(FmAgendaPanel, { entry: entry() });

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(view.emitted().close).toHaveLength(1);
    });
});
