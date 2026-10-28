import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmNewTabLink from './FmNewTabLink.vue';

describe('FmNewTabLink', () => {
    it('opens the address in a new tab, named by its text', () => {
        render(FmNewTabLink, {
            props: { href: '/organizations/3', text: 'Studio Ferri' },
        });

        const link = screen.getByRole('link', { name: 'Studio Ferri' });
        expect(link).toHaveAttribute('href', '/organizations/3');
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noopener');
        expect(link).toHaveAttribute('title', 'Open Studio Ferri in a new tab');
    });

    it('shows the icon of a new tab beside the text', () => {
        render(FmNewTabLink, {
            props: { href: '/people/1', text: 'Laura Bianchi' },
        });

        expect(
            screen.getByRole('link').querySelector('svg[aria-hidden="true"]'),
        ).not.toBeNull();
    });
});
