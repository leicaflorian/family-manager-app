import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, screenWidth } from '@/test/page';
import FmBackLink from './FmBackLink.vue';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('FmBackLink', () => {
    it('is the link back to the page it comes from, with its name', () => {
        renderPage(FmBackLink, { href: '/areas/home', label: 'Home' });

        expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute(
            'href',
            '/areas/home',
        );
    });

    it('is not drawn on the computer, where the top bar has Back and the trail', () => {
        screenWidth(1280);
        renderPage(FmBackLink, { href: '/areas/home', label: 'Home' });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });

    it.each([820, 390])('is drawn at %i px wide', (width) => {
        screenWidth(width);
        renderPage(FmBackLink, { href: '/areas/home', label: 'Home' });

        expect(screen.getByRole('link', { name: 'Home' })).toBeInTheDocument();
    });
});
