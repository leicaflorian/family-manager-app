import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmPager from './FmPager.vue';

describe('FmPager', () => {
    it('links to the previous and the next page', () => {
        renderPage(FmPager, {
            previous: '/documents?page=1',
            next: '/documents?page=3',
        });

        const pages = screen.getByRole('navigation', { name: 'Pages' });
        expect(pages).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Previous' })).toHaveAttribute(
            'href',
            '/documents?page=1',
        );
        expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute(
            'href',
            '/documents?page=3',
        );
    });

    it('on the first page shows only Next', () => {
        renderPage(FmPager, { previous: null, next: '/documents?page=2' });

        expect(
            screen.queryByRole('link', { name: 'Previous' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Next' })).toBeInTheDocument();
    });

    it('shows nothing when there is a single page', () => {
        renderPage(FmPager, { previous: null, next: null });

        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
    });
});
