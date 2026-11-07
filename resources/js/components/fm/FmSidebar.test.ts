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

    it('has only Upload when no file waits to be sorted', () => {
        bar('home', '/', 0);

        expect(screen.getByRole('link', { name: 'Upload' })).toHaveTextContent(
            /^\s*Upload\s*$/,
        );
        expect(
            screen.queryByRole('link', { name: /to sort/ }),
        ).not.toBeInTheDocument();
    });

    it('splits Upload with a part on the right that goes to To sort, with how many files wait', () => {
        const four = bar('home', '/', 4);
        expect(screen.getByRole('link', { name: 'Upload' })).toHaveAttribute(
            'href',
            '/upload',
        );
        const toSort = screen.getByRole('link', { name: '4 files to sort' });
        expect(toSort).toHaveAttribute('href', '/to-sort');
        expect(toSort).toHaveTextContent(/^\s*4\s*$/);
        four.unmount();

        const one = bar('home', '/', 1);
        expect(
            screen.getByRole('link', { name: '1 file to sort' }),
        ).toBeInTheDocument();
        one.unmount();

        bar('home', '/', 120);
        expect(
            screen.getByRole('link', { name: '120 files to sort' }),
        ).toHaveTextContent(/^\s*99\+\s*$/);
    });
    it('says what Upload and its part on the right do, in a tooltip', async () => {
        bar('home', '/', 4);

        await userEvent.tab();
        expect(screen.getByRole('link', { name: 'Upload' })).toHaveFocus();
        expect(await screen.findByRole('tooltip')).toHaveTextContent(
            'Upload a document from the computer',
        );

        await userEvent.tab();
        expect(
            screen.getByRole('link', { name: '4 files to sort' }),
        ).toHaveFocus();
        expect(await screen.findByRole('tooltip')).toHaveTextContent(
            '4 files to sort: go and read and import them',
        );
    });

    it('says one file in the singular in the tooltip', async () => {
        bar('home', '/', 1);

        await userEvent.tab();
        await userEvent.tab();

        expect(await screen.findByRole('tooltip')).toHaveTextContent(
            '1 file to sort: go and read and import it',
        );
    });
});
