import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmPannelloDalBasso from './FmPannelloDalBasso.vue';

/**
 * Il pannello con un campo e un pulsante, per provare il focus.
 */
async function apri(open = true) {
    const risultato = render(FmPannelloDalBasso, {
        props: { open, titolo: 'Filtra i documenti' },
        slots: {
            default:
                '<input aria-label="Di chi" /><button type="button">Mostra 4 documenti</button>',
        },
    });

    // Il pannello va nel body con un portale, che si monta subito dopo.
    if (open) {
        await screen.findByRole('dialog');
    }

    return risultato;
}

describe('FmPannelloDalBasso', () => {
    it('chiuso non si vede', async () => {
        await apri(false);

        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('si apre con open e mostra il titolo', async () => {
        await apri();

        expect(
            screen.getByRole('dialog', { name: 'Filtra i documenti' }),
        ).toBeInTheDocument();
    });

    it('si chiude con Esc', async () => {
        const risultato = await apri();

        await userEvent.keyboard('{Escape}');

        expect(risultato.emitted('update:open')).toEqual([[false]]);
    });

    it('si chiude con la maniglia', async () => {
        const risultato = await apri();

        await userEvent.click(
            screen.getByRole('button', { name: 'Chiudi il pannello' }),
        );

        expect(risultato.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('si chiude con la ×', async () => {
        const risultato = await apri();

        await userEvent.click(screen.getByRole('button', { name: 'Chiudi' }));

        expect(risultato.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('tiene il focus dentro finché è aperto', async () => {
        await apri();
        const pannello = screen.getByRole('dialog');

        for (let i = 0; i < 5; i++) {
            await userEvent.tab();
            expect(pannello).toContainElement(
                document.activeElement as HTMLElement,
            );
        }
    });
});
