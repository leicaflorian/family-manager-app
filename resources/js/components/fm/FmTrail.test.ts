import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmTrail from './FmTrail.vue';

const three = [
    { label: 'Home', href: '/' },
    { label: 'Documents', href: '/documents' },
    { label: 'Water bill' },
];

describe('FmTrail', () => {
    it('links every piece but the last, which is the current page', () => {
        renderPage(FmTrail, { pieces: three });

        expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute(
            'href',
            '/',
        );
        expect(screen.getByRole('link', { name: 'Documents' })).toHaveAttribute(
            'href',
            '/documents',
        );
        const current = screen.getByText('Water bill');
        expect(current).toHaveAttribute('aria-current', 'page');
        expect(current.closest('a')).toBeNull();
        expect(screen.getAllByRole('link')).toHaveLength(2);
    });

    it('is a navigation called "Where you are"', () => {
        renderPage(FmTrail, { pieces: three });

        expect(
            screen.getByRole('navigation', { name: 'Where you are' }),
        ).toBeInTheDocument();
    });

    it('shows only the current piece when there is one piece', () => {
        renderPage(FmTrail, { pieces: [{ label: 'Documents' }] });

        const nav = screen.getByRole('navigation', { name: 'Where you are' });
        expect(within(nav).queryAllByRole('link')).toHaveLength(0);
        expect(within(nav).getByText('Documents')).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('shows nothing with no pieces', () => {
        renderPage(FmTrail, { pieces: [] });

        expect(screen.queryByRole('navigation')).toBeNull();
    });

    it('truncates the last piece and keeps the others whole', () => {
        renderPage(FmTrail, { pieces: three });

        expect(screen.getByText('Water bill')).toHaveClass('truncate');
        expect(screen.getByRole('link', { name: 'Home' })).toHaveClass(
            'shrink-0',
        );
        expect(screen.getByRole('link', { name: 'Documents' })).toHaveClass(
            'shrink-0',
        );
    });
});
