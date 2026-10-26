import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmFiltroAttivo from './FmFiltroAttivo.vue';

describe('FmFiltroAttivo', () => {
    it('mostra il filtro e con la × lo toglie', async () => {
        const risultato = render(FmFiltroAttivo, {
            props: { etichetta: 'Laura' },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Togli il filtro Laura' }),
        );

        expect(screen.getByText('Laura')).toBeInTheDocument();
        expect(risultato.emitted('togli')).toHaveLength(1);
    });
});
