import { render, screen } from '@testing-library/vue';
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
        it('shows the day, its weekday, the card, the person and how far it is', () => {
            render(FmAgendaRow, { props: { entry: entry() } });

            const link = screen.getByRole('link');
            expect(link).toHaveTextContent('30');
            expect(link).toHaveTextContent('Fri');
            expect(link).toHaveTextContent('Bolletta gas');
            expect(link).toHaveTextContent('Gas - Enel');
            expect(link).toHaveTextContent('in 4 days');
            expect(link).toHaveTextContent('84,20 €');
            expect(screen.getByTitle('Marco')).toHaveTextContent('M');
        });

        it('writes an income with a plus', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[1] } });

            expect(screen.getByText('+1.850,00 €')).toBeInTheDocument();
        });

        it('shows a passed deadline in red with how long ago', () => {
            render(FmAgendaRow, { props: { entry: agendaEntries[0] } });

            expect(screen.getByText('26 days ago')).toHaveClass('text-overdue');
        });

        it('tells that the reminder is done', () => {
            render(FmAgendaRow, {
                props: { entry: entry({ reminder_done: true }) },
            });

            expect(screen.getByText('Reminder done')).toBeInTheDocument();
        });

        it('has no reminder line when it is not done', () => {
            render(FmAgendaRow, { props: { entry: entry() } });

            expect(screen.queryByText('Reminder done')).toBeNull();
        });
    });
});
