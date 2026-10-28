import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { areaCounts } from '@/test/fixtures';
import AppLayout from './AppLayout.vue';

const page = vi.hoisted(() => ({ url: '/documents?person=3' }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        url: page.url,
        props: {
            auth: { user: { name: 'Marco Bianchi' } },
            family: { name: 'Bianchi' },
            areas: areaCounts,
        },
    }),
}));

describe('AppLayout', () => {
    it('has the sidebar for the computer and the bottom bar for the phone, with the section of the page', () => {
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        const [sidebar, bottom] = screen.getAllByRole('navigation', {
            name: 'Sections',
        });
        expect(
            within(sidebar).getByRole('link', { name: 'Documents' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(bottom).getByRole('link', { name: 'Documents' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getByText('Bianchi family')).toBeInTheDocument();
        expect(screen.getByRole('main')).toHaveTextContent('Documents');
    });

    it('lists the areas in the sidebar, with the one of the page marked', () => {
        page.url = '/areas/vehicles?status=active';
        render(AppLayout, { slots: { default: '<h1>Vehicles</h1>' } });

        expect(
            screen.getByRole('link', { name: 'Vehicles 1' }),
        ).toHaveAttribute('aria-current', 'page');
        page.url = '/documents?person=3';
    });
});
