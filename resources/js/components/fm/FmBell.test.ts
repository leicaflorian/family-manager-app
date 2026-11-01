import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmBell from './FmBell.vue';

describe('FmBell', () => {
    it('leads to the notices', () => {
        render(FmBell, { props: { count: 0 } });

        expect(screen.getByRole('link', { name: 'Notices' })).toHaveAttribute(
            'href',
            '/notices',
        );
    });

    it('has no number when there is nothing new', () => {
        render(FmBell, { props: { count: 0 } });

        expect(screen.queryByText('0')).not.toBeInTheDocument();
    });

    it('shows how many notices are new, and says it', () => {
        render(FmBell, { props: { count: 3 } });

        expect(screen.getByText('3')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Notices: 3 new' }),
        ).toBeInTheDocument();
    });

    it('stops counting at 99', () => {
        render(FmBell, { props: { count: 120 } });

        expect(screen.getByText('99+')).toBeInTheDocument();
    });
});
