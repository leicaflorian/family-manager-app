import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmPulsante from './FmPulsante.vue';

describe('FmPulsante', () => {
    it('rende un pulsante con il testo', () => {
        render(FmPulsante, { slots: { default: 'Va bene, salva' } });

        expect(
            screen.getByRole('button', { name: 'Va bene, salva' }),
        ).toHaveAttribute('type', 'button');
    });

    it('disattivato non manda il clic', async () => {
        const risultato = render(FmPulsante, {
            props: { disabled: true },
            slots: { default: 'Salva' },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Salva' }));

        expect(screen.getByRole('button')).toBeDisabled();
        expect(risultato.emitted('click')).toBeUndefined();
    });

    it('la variante pericolo è rossa', () => {
        render(FmPulsante, {
            props: { variante: 'pericolo' },
            slots: { default: 'Elimina' },
        });

        expect(screen.getByRole('button', { name: 'Elimina' })).toHaveClass(
            'text-scaduto',
        );
    });

    it('la variante pieno è blu', () => {
        render(FmPulsante, { slots: { default: 'Salva' } });

        expect(screen.getByRole('button')).toHaveClass('bg-azione');
    });

    it('con href è un link', () => {
        render(FmPulsante, {
            props: { href: '/carica' },
            slots: { default: 'Carica' },
        });

        expect(screen.getByRole('link', { name: 'Carica' })).toHaveAttribute(
            'href',
            '/carica',
        );
    });
});
