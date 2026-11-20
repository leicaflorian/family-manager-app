import { render, screen } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { config } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { formPlugins } from '@/lib/formPlugins';
import { trackChanges } from '@/lib/unsavedChanges';
import { areaCounts } from '@/test/fixtures';
import { screenWidth } from '@/test/page';
import ScanLayout from './ScanLayout.vue';

const page = vi.hoisted(() => ({
    component: 'scan/Scan',
    linked: false,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        url: '/scan',
        get component() {
            return page.component;
        },
        get props() {
            return {
                name: 'Fameja.app',
                auth: { user: { name: 'Marco Bianchi' } },
                family: { name: 'Bianchi' },
                areas: areaCounts,
                to_sort_count: 0,
                impersonating: null,
                unread_notices: 0,
                linked_phone: page.linked,
            };
        },
    }),
}));

// On the tablet the layout of the app keeps the panel *Report a problem or an idea*, a FormKit form
config.global.plugins = [...config.global.plugins, ...formPlugins];

afterEach(() => {
    vi.unstubAllGlobals();
    page.component = 'scan/Scan';
    page.linked = false;
});

describe('ScanLayout', () => {
    it('shows the page without the bar of the sections', () => {
        render(ScanLayout, { slots: { default: '<h1>New document</h1>' } });

        expect(
            screen.getByRole('heading', { name: 'New document' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
    });

    it('on the tablet keeps the top bar of the app over the scan', () => {
        screenWidth(820);
        render(ScanLayout, { slots: { default: '<h1>New document</h1>' } });

        expect(
            screen.getByRole('combobox', { name: 'Search in Fameja.app' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'New document' }),
        ).toBeInTheDocument();
    });

    it.each([
        ['on the phone', 390, 'scan/Scan', false],
        ['on the computer', 1280, 'scan/Scan', false],
        ['on a linked phone', 820, 'scan/Scan', true],
        ['for the scanner of the house', 820, 'scan/Scanner', false],
    ])('has no top bar of the app %s', (_, width, component, linked) => {
        screenWidth(width);
        page.component = component;
        page.linked = linked;
        render(ScanLayout, { slots: { default: '<h1>New document</h1>' } });

        expect(
            screen.queryByRole('combobox', { name: 'Search in Fameja.app' }),
        ).not.toBeInTheDocument();
    });

    it('asks before leaving with pages not uploaded', async () => {
        const stop = trackChanges(() => true);
        const view = render(ScanLayout, { slots: { default: '<p>Scan</p>' } });

        router.visit('/to-sort');

        expect(
            await screen.findByRole('dialog', {
                name: 'Leave without saving?',
            }),
        ).toBeInTheDocument();

        stop();
        view.unmount();
    });
});
