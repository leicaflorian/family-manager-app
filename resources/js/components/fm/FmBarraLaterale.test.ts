import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmBarraLaterale from './FmBarraLaterale.vue';
import type { Sezione } from './navigazione';
import { vociDellaBarra } from './navigazione';

/**
 * La barra laterale di Marco, sulla pagina indicata.
 */
function barra(attiva: Sezione) {
    return render(FmBarraLaterale, {
        props: {
            attiva,
            voci: vociDellaBarra(),
            famiglia: 'Bianchi',
            utente: 'Marco Bianchi',
        },
    });
}

describe('FmBarraLaterale', () => {
    it('ha Carica in alto e le sezioni, con quella in cui si è segnata', () => {
        barra('documenti');

        expect(screen.getByRole('link', { name: 'Carica' })).toHaveAttribute(
            'href',
            '/carica',
        );
        expect(screen.getByRole('link', { name: 'Documenti' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute(
            'aria-current',
        );
        expect(screen.getByText('Famiglia Bianchi')).toBeInTheDocument();
    });

    it('in fondo Il mio account apre Impostazioni ed Esci', async () => {
        barra('home');

        await userEvent.click(
            screen.getByRole('button', { name: /Marco Bianchi/ }),
        );

        expect(
            screen.getByRole('menuitem', { name: 'Impostazioni' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('menuitem', { name: 'Esci' }),
        ).toBeInTheDocument();
    });
});
