import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmRiquadro from './FmRiquadro.vue';

describe('FmRiquadro', () => {
    it('mostra il titolo come intestazione e il contenuto', () => {
        render(FmRiquadro, {
            props: { titolo: 'Le persone' },
            slots: { default: '<p>Marco</p>' },
        });

        expect(
            screen.getByRole('heading', { name: 'Le persone' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Marco')).toBeInTheDocument();
    });

    it('come elenco non ha margini dentro, perché le righe hanno i loro', () => {
        const risultato = render(FmRiquadro, {
            props: { elenco: true },
            slots: { default: '<p>Riga</p>' },
        });

        expect(
            risultato.container.querySelector('[data-riquadro]'),
        ).not.toHaveClass('p-5');
    });
});
