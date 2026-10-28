import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmBackLink from './FmBackLink.vue';

describe('FmBackLink', () => {
    it('is the link back to the page it comes from, with its name', () => {
        renderPage(FmBackLink, { href: '/areas/home', label: 'Home' });

        expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute(
            'href',
            '/areas/home',
        );
    });
});
