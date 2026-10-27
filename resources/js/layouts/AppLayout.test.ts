import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import AppLayout from './AppLayout.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        url: '/documents?person=3',
        props: {
            auth: { user: { name: 'Marco Bianchi' } },
            family: { name: 'Bianchi' },
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
});
