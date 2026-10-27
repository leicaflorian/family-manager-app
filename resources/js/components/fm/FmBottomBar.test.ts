import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmBottomBar from './FmBottomBar.vue';
import type { Section } from './navigation';
import { navigationItems } from './navigation';

/**
 * The bar with the real items of the app, on the given page.
 */
function bar(active: Section) {
    return render(FmBottomBar, { props: { active, items: navigationItems() } });
}

describe('FmBottomBar', () => {
    it('marks the item of the page that is open', () => {
        bar('areas');

        expect(screen.getByRole('link', { name: 'Areas' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('leads to Upload with the +', () => {
        bar('home');

        expect(
            screen.getByRole('link', { name: 'Upload a document' }),
        ).toHaveAttribute('href', '/upload');
    });

    it('has Documents next to the +', () => {
        bar('home');

        expect(screen.getByRole('link', { name: 'Documents' })).toHaveAttribute(
            'href',
            '/documents',
        );
    });

    it('opens Family, Settings and Log out from More', async () => {
        bar('home');

        await userEvent.click(screen.getByRole('button', { name: 'More' }));

        const sheet = screen.getByRole('dialog', { name: 'More' });
        expect(sheet).toHaveTextContent('Family');
        expect(sheet).toHaveTextContent('Settings');
        expect(
            screen.getByRole('button', { name: 'Log out' }),
        ).toBeInTheDocument();
    });

    it('marks More on a page of More', () => {
        bar('family');

        expect(screen.getByRole('button', { name: 'More' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });
});
