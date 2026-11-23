import { render, screen, within } from '@testing-library/vue';
import { computed, h } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { screenWidth } from '@/test/page';
import FmSettingsPage from './FmSettingsPage.vue';
import FmTitle from './FmTitle.vue';

const bankFeed = vi.hoisted(() => ({
    share: null as { unsorted: number } | null,
}));

// What the server shares with every page is not part of the frame: the test sets it here
vi.mock('@/composables/useBankFeed', () => ({
    useBankFeed: () => computed(() => bankFeed.share),
}));

afterEach(() => {
    vi.unstubAllGlobals();
    bankFeed.share = null;
});

/**
 * Draws a settings page with a title and some content.
 *
 * @param current - the section the page is
 */
function renderSettings(
    current: 'notices' | 'mail' | 'banks' = 'notices',
): void {
    render(FmSettingsPage, {
        props: { current },
        slots: {
            title: '<h1>Notices</h1>',
            default: '<p>The rules</p>',
        },
    });
}

describe('FmSettingsPage', () => {
    it('shows the title and the content of the page', () => {
        renderSettings();

        expect(
            screen.getByRole('heading', { name: 'Notices' }),
        ).toBeInTheDocument();
        expect(screen.getByText('The rules')).toBeInTheDocument();
    });

    it('has no sections of the settings on the phone, where the bottom bar is', () => {
        screenWidth(390);
        renderSettings();

        expect(
            screen.queryByRole('navigation', { name: 'Settings sections' }),
        ).not.toBeInTheDocument();
    });

    it('lists the sections of the settings beside the page in the wide layout, in the groups of the settings', () => {
        screenWidth(1280);
        renderSettings();

        const nav = screen.getByRole('navigation', {
            name: 'Settings sections',
        });

        expect(
            within(nav)
                .getAllByRole('link')
                .map((link) => link.textContent?.trim()),
        ).toEqual([
            'My account',
            'Calendar',
            'Family name',
            'Notices',
            'Address for the bills',
            'Document categories',
            'Expense categories',
            'Areas',
            'Support',
            `What's new ${__APP_BUILD__}`,
        ]);
        expect(
            within(within(nav).getByRole('group', { name: 'The family' }))
                .getAllByRole('link')
                .map((link) => link.textContent?.trim()),
        ).toEqual([
            'Family name',
            'Notices',
            'Address for the bills',
            'Document categories',
            'Expense categories',
            'Areas',
            'Support',
        ]);
        expect(
            within(within(nav).getByRole('group', { name: 'The app' }))
                .getAllByRole('link')
                .map((link) => link.textContent?.trim()),
        ).toEqual([`What's new ${__APP_BUILD__}`]);
        expect(
            within(nav).getByRole('link', { name: 'Calendar' }),
        ).toHaveAttribute('href', '/settings/calendar');
        expect(
            within(nav).getByRole('link', { name: 'Family name' }),
        ).toHaveAttribute('href', '/settings/family');
    });

    it('lists Linked banks after the address for the bills while the link to the bank is on', () => {
        screenWidth(1280);
        bankFeed.share = { unsorted: 0 };
        renderSettings('banks');

        const nav = screen.getByRole('navigation', {
            name: 'Settings sections',
        });
        const links = within(
            within(nav).getByRole('group', { name: 'The family' }),
        ).getAllByRole('link');

        expect(
            links.map((link) => link.textContent?.trim()).slice(0, 3),
        ).toEqual(['Family name', 'Notices', 'Address for the bills']);
        expect(links[3]?.textContent?.trim()).toBe('Linked banks');
        expect(
            within(nav).getByRole('link', { name: 'Linked banks' }),
        ).toHaveAttribute('aria-current', 'page');
    });

    it('puts Settings on top in the wide layout, and the title of the page above its content as a section', () => {
        screenWidth(1280);
        render(FmSettingsPage, {
            props: { current: 'notices' },
            slots: {
                title: () =>
                    h(FmTitle, {
                        title: 'Notices',
                        subtitle: 'How many days before',
                    }),
                default: () => h('p', 'The rules'),
            },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Settings' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { level: 2, name: 'Notices' }),
        ).toBeInTheDocument();
        expect(screen.getByText('How many days before')).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Settings' }),
        ).not.toBeInTheDocument();
    });

    it('keeps the title of the page as the title on the phone', () => {
        screenWidth(390);
        render(FmSettingsPage, {
            props: { current: 'notices' },
            slots: {
                title: () => h(FmTitle, { title: 'Notices' }),
                default: () => h('p', 'The rules'),
            },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Notices' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: 'Settings' }),
        ).not.toBeInTheDocument();
    });

    it('has its own title at 32 px only on the computer', () => {
        screenWidth(1280);
        render(FmSettingsPage, {
            props: { current: 'notices' },
            slots: { default: '<p>The rules</p>' },
        });

        const heading = screen.getByRole('heading', { name: 'Settings' });
        expect(heading).toHaveClass('text-title-mobile', 'computer:text-title');
        expect(heading.className).not.toContain('lg:');
    });

    it('marks the section of the page as the current one', () => {
        screenWidth(1280);
        renderSettings('mail');

        const nav = screen.getByRole('navigation', {
            name: 'Settings sections',
        });

        expect(
            within(nav).getByRole('link', { name: 'Address for the bills' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(nav).getByRole('link', { name: 'Notices' }),
        ).not.toHaveAttribute('aria-current');
    });
});
