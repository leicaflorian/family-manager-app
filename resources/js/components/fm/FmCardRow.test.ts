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
