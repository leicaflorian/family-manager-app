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

            const link = screen.getByRole('link');
            expect(link).toHaveAttribute(
                'href',
                '/budget/planned/expense/3/occurrences/2026-10-30',
            );
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

        it('puts a dash where a deadline has no amount', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[4] } });

            expect(screen.getByRole('link')).toHaveTextContent('—');
        });

        it('highlights tomorrow', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[1] } });

            expect(screen.getByText('tomorrow')).toHaveClass('bg-action-soft');
        });

        it('shows a passed deadline in red with its month, the date and how long ago', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[0] } });

            const link = screen.getByRole('link');
            expect(within(link).getByText('sep')).toHaveClass('text-overdue');
            expect(
                within(link).getByText('Document expiry · 30/09/2026'),
            ).toBeInTheDocument();
            expect(screen.getByText('26 days ago')).toHaveClass('text-overdue');
        });

        it('tells when the notice of its reminder goes out', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[4] } });

            expect(screen.getByText('Notice on 03/11')).toBeInTheDocument();
        });

        it('tells that the reminder is done', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[8] } });

            expect(screen.getByText('Reminder done')).toBeInTheDocument();
        });

        it('tells that the reminder is late', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[0] } });

            expect(screen.getByText('Reminder late')).toBeInTheDocument();
        });

        it('has no reminder label without a reminder', () => {
            render(FmAgendaRow, { props: { entry: entry() } });

            expect(screen.queryByText('Reminder done')).toBeNull();
            expect(screen.queryByText(/Notice on/)).toBeNull();
            expect(screen.queryByText('Reminder late')).toBeNull();
        });

        it('has the buttons to update the date and to renew, outside the link, when asked', async () => {
            const view = render(FmAgendaRow, {
                props: { entry: agendaEntries[0], actions: true },
            });

            const update = screen.getByRole('button', {
                name: 'Update the date',
            });
            const renewed = screen.getByRole('button', { name: 'Renewed' });
            expect(screen.getByRole('link')).not.toContainElement(update);

            await userEvent.click(update);
            await userEvent.click(renewed);

            expect(view.emitted()['update-date']).toHaveLength(1);
            expect(view.emitted().renewed).toHaveLength(1);
        });

        it('puts the second button under the first when both do not fit in the card', () => {
            render(FmAgendaRow, {
                props: { entry: agendaEntries[0], actions: true },
            });

            // On a phone the two labels are wider than the card: the buttons
            // wrap and each grows to the whole line, instead of going out.
            const update = screen.getByRole('button', {
                name: 'Update the date',
            });
            expect(update.parentElement).toHaveClass('flex-wrap');
            expect(update).toHaveClass('grow');
            expect(screen.getByRole('button', { name: 'Renewed' })).toHaveClass(
                'grow',
            );
        });

        it('keeps the buttons side by side on the computer, under the row when the row needs the room', () => {
            render(FmAgendaRow, {
                props: { entry: agendaEntries[0], actions: true },
            });

            // The link keeps room for its columns and the title; the buttons
            // never shrink: when both do not fit, the buttons go to a new line.
            const buttons = screen.getByRole('button', {
                name: 'Update the date',
            }).parentElement;
            expect(buttons).toHaveClass('lg:shrink-0', 'lg:flex-nowrap');
            expect(buttons?.parentElement).toHaveClass('lg:flex-wrap');
            expect(screen.getByRole('link')).toHaveClass('lg:min-w-[34rem]');
        });

        it('has no buttons for a deadline the person may not change', () => {
            render(FmAgendaRow, {
                props: {
                    entry: { ...agendaEntries[0], update_url: null },
                    actions: true,
                },
            });

            expect(screen.queryByRole('button')).toBeNull();
        });

        it('has no buttons when not asked', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[0] } });

            expect(screen.queryByRole('button')).toBeNull();
        });
    });
});
