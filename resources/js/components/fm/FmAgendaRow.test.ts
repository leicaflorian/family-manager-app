import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { agendaEntries } from '@/test/fixtures';
import FmAgendaRow from './FmAgendaRow.vue';

/** A payment of the Bianchi on the board, with the fields the tests change. */
function entry(
    change: Partial<App.Data.AgendaEntryData> = {},
): App.Data.AgendaEntryData {
    return {
        ...agendaEntries[2],
        subtitle: 'Gas - Enel',
        href: '/budget/planned/expense/3/occurrences/2026-10-30',
        ...change,
    };
}

describe('FmAgendaRow', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('in the Home', () => {
        it('leads to the entry with its card and how far it is', () => {
            render(FmAgendaRow, {
                props: { entry: entry(), layout: 'home' },
            });

            const link = screen.getByRole('link');
            expect(link).toHaveAttribute(
                'href',
                '/budget/planned/expense/3/occurrences/2026-10-30',
            );
            expect(link).toHaveTextContent('Bolletta gas');
            expect(link).toHaveTextContent('Gas - Enel · in 4 days');
            expect(link).toHaveTextContent('84,20 €');
        });

        it('says how late it is, in red, with the date', () => {
            render(FmAgendaRow, {
                props: {
                    entry: entry({
                        date: '2026-10-23',
                        title: 'Bolletta luce di settembre',
                        amount: 71.3,
                        estimated: true,
                    }),
                    layout: 'home',
                },
            });

            expect(screen.getByText('Late by 3 days · 23/10/2026')).toHaveClass(
                'text-overdue',
            );
            expect(screen.getByText('~71,30 €')).toBeInTheDocument();
        });

        it('stays a link to the entry, not a button that opens the panel', () => {
            render(FmAgendaRow, {
                props: { entry: entry(), layout: 'home' },
            });

            expect(screen.getByRole('link')).toHaveAttribute(
                'href',
                '/budget/planned/expense/3/occurrences/2026-10-30',
            );
            expect(screen.queryByRole('button')).toBeNull();
        });

        it('shows the dot of the area', () => {
            const { container } = render(FmAgendaRow, {
                props: { entry: entry({ area: 'insurance' }), layout: 'home' },
            });

            expect(container.querySelector('[data-dot]')).toHaveClass(
                'bg-insurance',
            );
        });
    });

    describe('in the Agenda', () => {
        it('shows the day, its weekday, the thing, what happens, the person and how far it is', () => {
            render(FmAgendaRow, { props: { entry: entry() } });

            const link = screen.getByRole('button');
            expect(link).toHaveTextContent('30');
            expect(link).toHaveTextContent('Fri');
            expect(within(link).getByText('Gas - Enel')).toHaveClass(
                'text-row',
            );
            expect(link).toHaveTextContent('Bolletta gas');
            expect(link).toHaveTextContent('in 4 days');
            expect(link).toHaveTextContent('84,20 €');
            expect(screen.getAllByText('M').length).toBeGreaterThan(0);
            expect(screen.getByText('Marco')).toBeInTheDocument();
        });

        it('shows the initials of the person when the page gives them', () => {
            render(FmAgendaRow, {
                props: {
                    entry: entry(),
                    personAvatar: { initials: 'MB', photo: null },
                },
            });

            expect(screen.getAllByText('MB').length).toBeGreaterThan(0);
            expect(screen.getByText('Marco')).toBeInTheDocument();
        });

        it('shows the photo of the person when there is one', () => {
            const { container } = render(FmAgendaRow, {
                props: {
                    entry: entry(),
                    personAvatar: {
                        initials: 'MB',
                        photo: '/photos/marco.jpg',
                    },
                },
            });

            expect(container.querySelector('img')).toHaveAttribute(
                'src',
                '/photos/marco.jpg',
            );
        });

        it('writes an income with a plus', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[1] } });

            expect(screen.getByText('+1.850,00 €')).toBeInTheDocument();
        });

        it('puts no dash where a deadline has no amount', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[4] } });

            expect(screen.getByRole('button')).not.toHaveTextContent('—');
        });

        it('highlights tomorrow', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[1] } });

            expect(screen.getByText('tomorrow')).toHaveClass('bg-action-soft');
        });

        it('shows a passed deadline in red with its month, what happens and how long ago', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[0] } });

            const link = screen.getByRole('button');
            expect(within(link).getByText('sep')).toHaveClass('text-overdue');
            expect(
                within(link).getByText('Document expiry'),
            ).toBeInTheDocument();
            expect(screen.getByText('26 days ago')).toHaveClass('text-overdue');
        });

        it('has a bell next to the name when the reminder has notices', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[4] } });

            expect(
                screen.getByRole('img', { name: 'Notice set' }),
            ).toBeInTheDocument();
            expect(screen.queryByText(/Notice on/)).toBeNull();
            expect(screen.queryByText('Reminder late')).toBeNull();
        });

        it('has no bell without notices, without a reminder or once done', async () => {
            const reminder = agendaEntries[4].reminder;
            const bare = { ...reminder!, notice_days: [] };
            const view = render(FmAgendaRow, {
                props: { entry: { ...agendaEntries[4], reminder: bare } },
            });
            expect(
                screen.queryByRole('img', { name: 'Notice set' }),
            ).toBeNull();

            await view.rerender({ entry: entry() });
            expect(
                screen.queryByRole('img', { name: 'Notice set' }),
            ).toBeNull();

            await view.rerender({ entry: agendaEntries[4], done: true });
            expect(
                screen.queryByRole('img', { name: 'Notice set' }),
            ).toBeNull();
        });

        it('says who closed it and when, without the amount, in the done list', () => {
            render(FmAgendaRow, {
                props: { entry: agendaEntries[8], done: true },
            });

            const row = screen.getByRole('button');
            expect(row).toHaveTextContent('Done on 05/11 · Laura');
            expect(row).not.toHaveTextContent('days');
            expect(row).not.toHaveTextContent('—');
        });

        it('has no dangling dot when nobody is known to have closed it', () => {
            render(FmAgendaRow, {
                props: {
                    entry: entry({
                        reminder: {
                            ...agendaEntries[8].reminder!,
                            done_by: null,
                        },
                    }),
                    done: true,
                },
            });

            const row = screen.getByRole('button');
            expect(row).toHaveTextContent('Done on 05/11');
            expect(row.textContent).not.toContain('·');
        });

        it('does not repeat the late date in the second line of the agenda', () => {
            render(FmAgendaRow, {
                props: {
                    entry: entry({
                        date: '2026-10-20',
                        detail: 'Document expiry',
                    }),
                },
            });

            const row = screen.getByRole('button');
            expect(row).toHaveTextContent('Document expiry');
            expect(row).not.toHaveTextContent('Document expiry · 20/10/2026');
        });

        it('tells the day of the closing on the left in the done list', () => {
            render(FmAgendaRow, {
                props: { entry: agendaEntries[8], done: true },
            });

            const row = screen.getByRole('button');
            expect(row).toHaveTextContent('5');
            expect(row).toHaveTextContent('nov');
        });

        it('opens the panel when the row is tapped, with no link', async () => {
            const view = render(FmAgendaRow, { props: { entry: entry() } });

            expect(screen.queryByRole('link')).toBeNull();
            await userEvent.click(screen.getByRole('button'));

            expect(view.emitted().open).toHaveLength(1);
        });

        it('has Reopen beside a done row with reopen, apart from the row', async () => {
            const remove = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => undefined);
            const view = render(FmAgendaRow, {
                props: { entry: agendaEntries[8], done: true, reopen: true },
            });

            const button = screen.getByRole('button', {
                name: "Reopen: Scade la carta d'identità",
            });
            expect(button).toHaveTextContent('Reopen');
            expect(
                screen.getByRole('button', { name: /Done on 05\/11/ }),
            ).not.toContainElement(button);

            await userEvent.click(button);

            expect(remove).toHaveBeenCalledWith(
                '/reminders/3/done',
                expect.anything(),
            );
            expect(view.emitted().open).toBeUndefined();
            remove.mockRestore();
        });

        it('has no Reopen without reopen, outside the done list or without a reminder', async () => {
            const view = render(FmAgendaRow, {
                props: { entry: agendaEntries[8], done: true },
            });
            expect(
                screen.queryByRole('button', { name: /^Reopen/ }),
            ).toBeNull();

            await view.rerender({
                entry: agendaEntries[8],
                done: false,
                reopen: true,
            });
            expect(
                screen.queryByRole('button', { name: /^Reopen/ }),
            ).toBeNull();

            await view.rerender({
                entry: { ...agendaEntries[8], reminder: null },
                done: true,
                reopen: true,
            });
            expect(
                screen.queryByRole('button', { name: /^Reopen/ }),
            ).toBeNull();
        });
    });
});
