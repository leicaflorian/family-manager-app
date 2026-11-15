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
import { render } from '@testing-library/vue';
import { Toaster } from 'vue-sonner';
import { accountOptions, danceDeadline } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
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
        account: null,
        planned: null,
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

        it('once done, says so for a moment with Undo, which reopens it', async () => {
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, options) => {
                    options?.onSuccess?.({} as never);
                },
            );
            const reopen = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => {});
            render(Toaster);
            renderPage(FmAgendaPanel, { entry: entry() });

            await userEvent.click(screen.getByRole('button', { name: 'Done' }));

            expect(
                await screen.findByText('Done: Polizza auto - Allianz'),
            ).toBeInTheDocument();
            await userEvent.click(
                await screen.findByRole('button', { name: 'Undo' }),
            );

            expect(reopen).toHaveBeenCalledWith(
                '/reminders/7/done',
                expect.anything(),
            );
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
            href: 'http://localhost/budget/planned/expense/3/occurrences/2026-10-28?pay=1',
            account: 'Conto BPER',
            planned: 'Gas - Hera, every 2 months',
        });

        it('shows the amount, I paid it and a link to the due date, without Done', async () => {
            const view = renderPage(FmAgendaPanel, { entry: payment });

            expect(screen.getByText(/84,20/)).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: 'Open the due date' }),
            ).toHaveAttribute(
                'href',
                'http://localhost/budget/planned/expense/3/occurrences/2026-10-28',
            );
            expect(screen.getByText('Bank account')).toBeInTheDocument();
            expect(screen.getByText('Conto BPER')).toBeInTheDocument();
            expect(screen.getByText('Planned expense')).toBeInTheDocument();
            expect(
                screen.getByText('Gas - Hera, every 2 months'),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Done' }),
            ).not.toBeInTheDocument();
            // Until its due date arrives, I paid it waits
            expect(
                screen.getByRole('button', { name: 'I paid it' }),
            ).toBeDisabled();
            expect(view.emitted().pay).toBeUndefined();
        });

        /** The due date of the gas bill, as the server sends it for the panel. */
        const gasDeadline: App.Data.DeadlineData = {
            ...danceDeadline,
            planned_id: 3,
            title: 'Gas - Hera',
            date: '2026-10-28',
            original_date: '2026-10-28',
            amount: 84.2,
            base_amount: null,
            account_id: 1,
            account: 'Conto BPER',
        };

        const paying = {
            entry: payment,
            payment: gasDeadline,
            accounts: accountOptions,
            payAction:
                '/budget/planned/expense/3/occurrences/2026-10-28/payment?from_agenda=1',
        };

        it('holds the form of the payment, filled, and I paid it records it in one tap', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(FmAgendaPanel, paying);

            expect(await screen.findByLabelText('How much')).toHaveValue(
                '84,20\u00a0€',
            );
            await userEvent.click(
                screen.getByRole('button', { name: 'I paid it' }),
            );
            await settle();

            expect(post).toHaveBeenCalledWith(
                paying.payAction,
                expect.objectContaining({
                    amount: 84.2,
                    financial_account_id: '1',
                }),
                expect.anything(),
            );
            expect(
                screen.getByRole('link', { name: 'Open the due date' }),
            ).toBeInTheDocument();
            // A light line divides the form from the details, as the boards draw it
            expect(
                screen.getByLabelText('How much').closest('form'),
            ).toHaveClass('border-t', 'border-divider');
        });

        it('tells the page once the payment is recorded', async () => {
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, options) => {
                    options?.onSuccess?.({} as never);
                },
            );
            const view = renderPage(FmAgendaPanel, paying);

            await userEvent.click(
                await screen.findByRole('button', { name: 'I paid it' }),
            );
            await settle();

            expect(view.emitted().paid).toHaveLength(1);
        });

        it('names the button of an income It arrived', async () => {
            renderPage(FmAgendaPanel, {
                ...paying,
                entry: { ...payment, kind: 'income' },
                payment: { ...gasDeadline, kind: 'income' },
            });

            expect(
                await screen.findByRole('button', { name: 'It arrived' }),
            ).toBeInTheDocument();
        });

        it('has no form in the Done list', () => {
            renderPage(FmAgendaPanel, { ...paying, done: true });

            expect(screen.queryByLabelText('How much')).not.toBeInTheDocument();
        });

        it('once paid, says so and leads to its due date, where it is changed', () => {
            renderPage(FmAgendaPanel, { entry: payment, done: true });

            expect(screen.getByText('Paid')).toBeInTheDocument();
            expect(screen.queryByText(/Done on/)).not.toBeInTheDocument();
            expect(screen.queryByText('deadline done')).not.toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: 'Open the due date' }),
            ).toHaveAttribute(
                'href',
                'http://localhost/budget/planned/expense/3/occurrences/2026-10-28',
            );
            expect(
                screen.queryByRole('button', { name: 'I paid it' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Reopen' }),
            ).not.toBeInTheDocument();
        });

        it('that also pays a deadline has no Done nor Postpone', () => {
            renderPage(FmAgendaPanel, {
                entry: { ...payment, reminder: reminder() },
            });

            expect(
                screen.getByRole('button', { name: 'I paid it' }),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Done' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Postpone' }),
            ).not.toBeInTheDocument();
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
            subtitle: 'Casa di via Roma',
            notes: 'Ask about the water heater too',
            update_url: null,
            href: '/reminders/9/edit',
            reminder: reminder({ id: 9 }),
        });

        it('shows the notes, Done, Postpone and a menu with Edit and Delete', async () => {
            renderPage(FmAgendaPanel, { entry: task });
            const user = userEvent.setup();

            expect(screen.getByText('Referred to')).toBeInTheDocument();
            expect(screen.getByText('Casa di via Roma')).toBeInTheDocument();
            expect(
                screen.getByText('Ask about the water heater too'),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Done' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Postpone' }),
            ).toBeInTheDocument();
            await user.click(screen.getByRole('button', { name: 'More' }));
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
            await userEvent.click(screen.getByRole('button', { name: 'More' }));
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

            await user.click(screen.getByRole('button', { name: 'More' }));
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

        it('says only when without a known name', () => {
            renderPage(FmAgendaPanel, {
                entry: entry({
                    reminder: reminder({
                        state: 'done' as App.Enums.AgendaReminderState,
                        done_on: '2026-11-01',
                        done_by: null,
                    }),
                }),
                done: true,
            });

            expect(screen.getByText('Done on 01/11')).toBeInTheDocument();
        });

        it('says when and by whom, and has Reopen instead of Done and Postpone', async () => {
            const reopen = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => {});
            renderPage(FmAgendaPanel, { entry: closed, done: true });

            expect(
                screen.getByText('Done on 01/11 · Laura'),
            ).toBeInTheDocument();
            expect(screen.getByText('deadline done')).toBeInTheDocument();
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

        it('says that a task is done with its own words', () => {
            renderPage(FmAgendaPanel, {
                entry: { ...closed, kind: 'task', key: 'task:7' },
                done: true,
            });

            expect(screen.getByText('task done')).toBeInTheDocument();
        });

        it('closes once reopened', async () => {
            vi.spyOn(router, 'delete').mockImplementation((_url, options) => {
                options?.onSuccess?.({} as never);
            });
            const view = renderPage(FmAgendaPanel, {
                entry: closed,
                done: true,
            });

            await userEvent.click(
                screen.getByRole('button', { name: 'Reopen' }),
            );

            expect(view.emitted().close).toHaveLength(1);
        });
    });

    it('the close button emits close', async () => {
        const view = renderPage(FmAgendaPanel, { entry: entry() });

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(view.emitted().close).toHaveLength(1);
    });
});
