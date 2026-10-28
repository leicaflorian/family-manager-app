import { render, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import FmDateTile from './FmDateTile.vue';

describe('FmDateTile', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-26T10:00:00+01:00'));
    });
    afterEach(() => vi.useRealTimers());

    it('says how long ago an expiring date passed, in red', () => {
        render(FmDateTile, {
            props: {
                date: {
                    label: 'Inspection',
                    date: '2026-10-23',
                    expires: true,
                },
            },
        });

        expect(screen.getByText('Inspection')).toBeInTheDocument();
        expect(screen.getByText('23/10/2026')).toBeInTheDocument();
        expect(screen.getByText('Expired 3 days ago')).toHaveClass(
            'text-overdue',
        );
    });

    it('writes a past date that does not expire plainly', () => {
        render(FmDateTile, {
            props: {
                date: { label: 'Date', date: '2026-10-23', expires: false },
            },
        });

        expect(screen.getByText('3 days ago')).not.toHaveClass('text-overdue');
    });

    it('writes a date ahead with the days left', () => {
        render(FmDateTile, {
            props: {
                date: { label: 'Date', date: '2026-11-21', expires: true },
            },
        });

        expect(screen.getByText('in 26 days')).toBeInTheDocument();
    });
});
