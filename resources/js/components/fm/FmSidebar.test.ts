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
function bar(active: Section, path = '/dashboard', uploadCount?: number) {
    return render(FmSidebar, {
        props: {
            active,
            path,
            items: navigationItems(),
            areas: areaCounts,
            family: 'Bianchi',
            user: 'Marco Bianchi',
            uploadCount,
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

    it('has Agenda between Home and Areas, marked on its page', () => {
        bar('agenda', '/agenda');

        const nav = screen.getByRole('navigation', { name: 'Sections' });
        const names = within(nav)
            .getAllByRole('link')
            .map((link) => link.textContent?.trim())
            .filter((name) => ['Home', 'Agenda', 'Areas'].includes(name ?? ''));
        expect(names).toEqual(['Home', 'Agenda', 'Areas']);
        expect(screen.getByRole('link', { name: 'Agenda' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('has Budget between Documents and Family, marked on its pages', () => {
        bar('budget', '/budget/comparison');

        const nav = screen.getByRole('navigation', { name: 'Sections' });
        const names = within(nav)
            .getAllByRole('link')
            .map((link) => link.textContent?.trim())
            .filter((name) =>
                ['Documents', 'Budget', 'Family'].includes(name ?? ''),
            );
        expect(names).toEqual(['Documents', 'Budget', 'Family']);
        expect(screen.getByRole('link', { name: 'Budget' })).toHaveAttribute(
            'href',
            '/budget',
        );
        expect(screen.getByRole('link', { name: 'Budget' })).toHaveAttribute(
            'aria-current',
            'page',
        );
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

    it('follows the areas it receives: the order of the family, without the hidden ones', () => {
        const ordered = [
            areaCounts[1]!,
            areaCounts[0]!,
            ...areaCounts.slice(2).filter((area) => area.area !== 'work'),
        ];
        render(FmSidebar, {
            props: {
                active: 'areas',
                path: '/areas',
                items: navigationItems(),
                areas: ordered,
                family: 'Bianchi',
                user: 'Marco Bianchi',
            },
        });

        const links = within(areaList() as HTMLElement).getAllByRole('link');
        expect(links).toHaveLength(9);
        expect(links[1]).toHaveAttribute('href', '/areas/vehicles');
        expect(links[2]).toHaveAttribute('href', '/areas/home');
        expect(links.map((link) => link.getAttribute('href'))).not.toContain(
            '/areas/work',
        );
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

    it('has Upload as one link that only uploads, even while files wait', () => {
        bar('home', '/', 4);

        const upload = screen.getByRole('link', { name: 'Upload' });
        expect(upload).toHaveAttribute('href', '/upload');
        expect(upload).toHaveTextContent(/^\s*Upload\s*$/);
        expect(
            screen.queryByRole('link', { name: /files to sort/ }),
        ).not.toBeInTheDocument();
    });

    it('has To sort right under Home, with how many files wait', () => {
        const four = bar('home', '/', 4);
        const nav = screen.getByRole('navigation', { name: 'Sections' });
        const names = within(nav)
            .getAllByRole('link')
            .slice(0, 3)
            .map((link) => link.textContent?.replace(/\s+/g, ' ').trim());
        expect(names).toEqual(['Home', 'To sort 4', 'Agenda']);
        expect(
            within(nav).getByRole('link', { name: 'To sort 4' }),
        ).toHaveAttribute('href', '/to-sort');
        four.unmount();

        bar('home', '/', 120);
        expect(
            screen.getByRole('link', { name: 'To sort 99+' }),
        ).toBeInTheDocument();
    });

    it('keeps To sort without a number when no file waits', () => {
        bar('home', '/', 0);

        expect(screen.getByRole('link', { name: 'To sort' })).toHaveTextContent(
            /^\s*To sort\s*$/,
        );
    });

    it('marks To sort on its page', () => {
        bar('toSort', '/to-sort', 2);

        expect(screen.getByRole('link', { name: 'To sort 2' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('takes the width of the place it is in, when the page asks for it', () => {
        render(FmSidebar, {
            props: {
                active: 'home',
                path: '/dashboard',
                items: navigationItems(),
                areas: areaCounts,
                family: 'Bianchi',
                user: 'Marco Bianchi',
            },
            attrs: { class: 'w-full' },
        });

        const sidebar = screen.getByRole('complementary');
        expect(sidebar).toHaveClass('w-full');
        expect(sidebar).not.toHaveClass('w-[260px]');
    });
});
