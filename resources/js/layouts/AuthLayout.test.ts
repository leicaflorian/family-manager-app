import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import AuthLayout from './AuthLayout.vue';

describe('AuthLayout', () => {
    it("mostra il titolo della pagina, il pannello azzurro e l'altra strada", () => {
        render(AuthLayout, {
            props: {
                titolo: 'Bentornato',
                sottotitolo: 'Entra nella tua famiglia.',
                pannello: {
                    titolo: 'Tutto ciò che riguarda la famiglia, in un posto solo.',
                    testo: 'Le scadenze di tutti.',
                },
                altraStrada: {
                    testo: 'Non hai ancora Family Manager?',
                    link: 'Iscrivi la tua famiglia',
                    href: '/register',
                },
            },
            slots: { default: '<form aria-label="Accedi"></form>' },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Bentornato' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Entra nella tua famiglia.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Tutto ciò che riguarda la famiglia, in un posto solo.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Iscrivi la tua famiglia' }),
        ).toHaveAttribute('href', '/register');
        expect(
            screen.getByRole('form', { name: 'Accedi' }),
        ).toBeInTheDocument();
    });

    it('i punti del pannello sono un elenco', () => {
        render(AuthLayout, {
            props: {
                titolo: 'Iscrivi la tua famiglia',
                pannello: {
                    titolo: 'Comincia oggi.',
                    testo: 'Non serve la carta.',
                    punti: ['Le scadenze', 'I documenti'],
                },
            },
        });

        expect(
            screen.getAllByRole('listitem').map((li) => li.textContent?.trim()),
        ).toEqual(['Le scadenze', 'I documenti']);
    });
});
