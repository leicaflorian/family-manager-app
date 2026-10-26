import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import AppLayout from './AppLayout.vue';

vi.mock('@inertiajs/vue3', async (originale) => ({
    ...(await originale<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        url: '/documenti?persona=3',
        props: {
            auth: { user: { name: 'Marco Bianchi' } },
            famiglia: { name: 'Bianchi' },
        },
    }),
}));

describe('AppLayout', () => {
    it('ha la barra laterale per il computer e quella in basso per il telefono, con la sezione della pagina', () => {
        render(AppLayout, { slots: { default: '<h1>Documenti</h1>' } });

        const [laterale, sotto] = screen.getAllByRole('navigation', {
            name: 'Sezioni',
        });
        expect(
            within(laterale).getByRole('link', { name: 'Documenti' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(sotto).getByRole('link', { name: 'Documenti' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getByText('Famiglia Bianchi')).toBeInTheDocument();
        expect(screen.getByRole('main')).toHaveTextContent('Documenti');
    });
});
