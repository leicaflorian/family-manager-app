import { render, screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { screenWidth } from '@/test/page';
import FmSettingsPage from './FmSettingsPage.vue';

afterEach(() => {
    vi.unstubAllGlobals();
});

/**
 * Draws a settings page with a title and some content.
 *
 * @param current - the section the page is
 */
function renderSettings(current: 'notices' | 'mail' = 'notices'): void {
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

    it('lists the sections of the settings beside the page in the wide layout', () => {
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
            'Notices',
            'Address for the bills',
            'Calendar',
            'Document categories',
            'Expense categories',
            'Areas',
            'Support',
        ]);
        expect(
            within(nav).getByRole('link', { name: 'Calendar' }),
        ).toHaveAttribute('href', '/settings/calendar');
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
