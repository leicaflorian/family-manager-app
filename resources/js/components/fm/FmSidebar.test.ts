import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmSidebar from './FmSidebar.vue';
import type { Section } from './navigation';
import { navigationItems } from './navigation';

/**
 * The sidebar of Marco, on the given page.
 */
function bar(active: Section) {
    return render(FmSidebar, {
        props: {
            active,
            items: navigationItems(),
            family: 'Bianchi',
            user: 'Marco Bianchi',
        },
    });
}

describe('FmSidebar', () => {
    it('has Upload at the top and the sections, with the open one marked', () => {
        bar('documents');

        expect(screen.getByRole('link', { name: 'Upload' })).toHaveAttribute(
            'href',
            '/upload',
        );
        expect(screen.getByRole('link', { name: 'Documents' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute(
            'aria-current',
        );
        expect(screen.getByText('Bianchi family')).toBeInTheDocument();
    });

    it('opens Settings and Log out from My account at the bottom', async () => {
        bar('home');

        await userEvent.click(
            screen.getByRole('button', { name: /Marco Bianchi/ }),
        );

        expect(
            screen.getByRole('menuitem', { name: 'Settings' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('menuitem', { name: 'Log out' }),
        ).toBeInTheDocument();
    });
});
