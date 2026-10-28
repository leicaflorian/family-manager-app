import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmSidebar from './FmSidebar.vue';
import { areaCounts } from '@/test/fixtures';
import type { Section } from './navigation';
import { navigationItems } from './navigation';

/**
 * The sidebar of Marco, on the given page.
 */
function bar(active: Section, path = '/dashboard') {
    return render(FmSidebar, {
        props: {
            active,
            path,
            items: navigationItems(),
            areas: areaCounts,
            family: 'Bianchi',
            user: 'Marco Bianchi',
        },
    });
}

/**
 * The list of the areas under *Areas*.
 */
function areaList() {
    return screen.queryByRole('list', { name: 'Areas' });
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

    it('lists every area with its active cards under Areas', () => {
        bar('areas', '/areas');

        const list = within(areaList() as HTMLElement);
        expect(list.getByRole('link', { name: 'All areas' })).toHaveAttribute(
            'href',
            '/areas',
        );
        expect(list.getByRole('link', { name: 'Home 3' })).toHaveAttribute(
            'href',
            '/areas/home',
        );
        expect(list.getByRole('link', { name: 'Vehicles 1' })).toHaveAttribute(
            'href',
            '/areas/vehicles',
        );
        // An area without active cards shows no number
        expect(list.getByRole('link', { name: 'health' })).toHaveAttribute(
            'href',
            '/areas/health',
        );
        expect(list.getAllByRole('link')).toHaveLength(10);
    });

    it('marks the open area, or All areas on the page of all of them', () => {
        bar('areas', '/areas/vehicles');

        expect(
            screen.getByRole('link', { name: 'Vehicles 1' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'All areas' }),
        ).not.toHaveAttribute('aria-current');
        expect(screen.getByRole('link', { name: 'Areas' })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('keeps the areas closed outside Areas, and opens them with the arrow', async () => {
        bar('documents', '/documents');

        const toggle = screen.getByRole('button', { name: 'Show the areas' });
        expect(toggle).toHaveAttribute('aria-expanded', 'false');
        expect(areaList()).not.toBeInTheDocument();

        await userEvent.click(toggle);

        expect(toggle).toHaveAttribute('aria-expanded', 'true');
        expect(areaList()).toBeInTheDocument();
    });

    it('opens the areas on a card page, where Areas stays marked', () => {
        bar('areas', '/cards/vehicle/1');

        expect(
            screen.getByRole('button', { name: 'Show the areas' }),
        ).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('link', { name: 'Areas' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });
});
