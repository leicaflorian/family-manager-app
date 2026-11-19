import { render, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { pandaRow } from '@/test/fixtures';
import FmCardRow from './FmCardRow.vue';

describe('FmCardRow', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    });
    afterEach(() => vi.useRealTimers());

    it('shows the name and the next date, and leads to the card', () => {
        render(FmCardRow, { props: { card: pandaRow } });

        const link = screen.getByRole('link', { name: /Fiat Panda/ });
        expect(link).toHaveAttribute('href', '/cards/vehicle/1');
        expect(screen.getByText('Inspection · in 75 days')).toBeInTheDocument();
    });

    it('writes an overdue date in red', () => {
        render(FmCardRow, {
            props: {
                card: { ...pandaRow, next_date: '2026-10-23', overdue: true },
            },
        });

        expect(screen.getByText('Inspection · 3 days ago')).toHaveClass(
            'text-overdue',
        );
    });

    it('writes in bold a date within 30 days', () => {
        render(FmCardRow, {
            props: { card: { ...pandaRow, next_date: '2026-11-07' } },
        });

        expect(screen.getByText('Inspection · in 12 days')).toHaveClass(
            'text-meta-strong',
            'text-ink',
        );
    });

    it('writes in bold a date that is 30 days away', () => {
        render(FmCardRow, {
            props: { card: { ...pandaRow, next_date: '2026-11-25' } },
        });

        expect(screen.getByText('Inspection · in 30 days')).toHaveClass(
            'text-meta-strong',
        );
    });

    it('writes a date further away in the normal style', () => {
        render(FmCardRow, {
            props: { card: { ...pandaRow, next_date: '2026-11-26' } },
        });

        expect(screen.getByText('Inspection · in 31 days')).toHaveClass(
            'text-meta',
            'text-ink-soft',
        );
    });

    it('does not write in bold the date of a closed card', () => {
        render(FmCardRow, {
            props: {
                card: { ...pandaRow, next_date: '2026-11-07', closed: true },
            },
        });

        expect(screen.getByText('Inspection · in 12 days')).toHaveClass(
            'text-ink-soft',
        );
    });

    it('shows the subtitle when there is no date', () => {
        render(FmCardRow, {
            props: { card: { ...pandaRow, next_label: null, next_date: null } },
        });

        expect(screen.getByText('GH 482 TK')).toBeInTheDocument();
    });

    it('greys out a closed card', () => {
        render(FmCardRow, { props: { card: { ...pandaRow, closed: true } } });

        expect(screen.getByRole('link', { name: /Fiat Panda/ })).toHaveClass(
            'opacity-60',
        );
    });

    it('shows on the right the amount due, red when late', () => {
        render(FmCardRow, {
            props: { card: { ...pandaRow, due_amount: 71.3, due_late: true } },
        });

        const amount = screen.getByText('71,30 €');
        expect(amount).toHaveClass('text-overdue');
    });

    it('shows the amount due in the colour of the text when it is not late', () => {
        render(FmCardRow, {
            props: { card: { ...pandaRow, due_amount: 84.2 } },
        });

        expect(screen.getByText('84,20 €')).not.toHaveClass('text-overdue');
    });
});
