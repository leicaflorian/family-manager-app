import { render } from '@testing-library/vue';
import { House } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmIcona from './FmIcona.vue';

describe('FmIcona', () => {
    it('senza etichetta è solo decorazione', () => {
        const risultato = render(FmIcona, { props: { icona: House } });
        const svg = risultato.container.querySelector('svg');

        expect(svg).toHaveAttribute('aria-hidden', 'true');
        expect(svg).toHaveAttribute('width', '24');
        expect(svg).toHaveAttribute('stroke-width', '2');
    });

    it('con etichetta la legge chi usa un lettore di schermo', () => {
        const risultato = render(FmIcona, {
            props: { icona: House, etichetta: 'Casa', dimensione: 20 },
        });

        expect(risultato.getByRole('img', { name: 'Casa' })).toHaveAttribute(
            'width',
            '20',
        );
    });
});
