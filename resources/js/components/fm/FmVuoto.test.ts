import { render, screen } from '@testing-library/vue';
import { Shapes } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmVuoto from './FmVuoto.vue';

describe('FmVuoto', () => {
    it('dice perché la pagina è vuota e cosa si può fare', () => {
        render(FmVuoto, {
            props: {
                icona: Shapes,
                titolo: 'Ancora niente',
                testo: 'Comincia da un documento.',
            },
            slots: { default: '<a href="/carica">Carica</a>' },
        });

        expect(
            screen.getByRole('heading', { name: 'Ancora niente' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Comincia da un documento.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Carica' }),
        ).toBeInTheDocument();
    });
});
