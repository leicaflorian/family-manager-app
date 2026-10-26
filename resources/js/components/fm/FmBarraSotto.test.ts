import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmBarraSotto from './FmBarraSotto.vue';
import type { Sezione } from './navigazione';
import { vociDellaBarra } from './navigazione';

/**
 * La barra con le voci vere dell'app, sulla pagina indicata.
 */
function barra(attiva: Sezione) {
    return render(FmBarraSotto, { props: { attiva, voci: vociDellaBarra() } });
}

describe('FmBarraSotto', () => {
    it('segna la voce della pagina in cui si è', () => {
        barra('aree');

        expect(screen.getByRole('link', { name: 'Aree' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('il + porta a Carica', () => {
        barra('home');

        expect(
            screen.getByRole('link', { name: 'Carica un documento' }),
        ).toHaveAttribute('href', '/carica');
    });

    it('ha Documenti accanto al +', () => {
        barra('home');

        expect(screen.getByRole('link', { name: 'Documenti' })).toHaveAttribute(
            'href',
            '/documenti',
        );
    });

    it('Altro apre Famiglia, Impostazioni ed Esci', async () => {
        barra('home');

        await userEvent.click(screen.getByRole('button', { name: 'Altro' }));

        const pannello = screen.getByRole('dialog', { name: 'Altro' });
        expect(pannello).toHaveTextContent('Famiglia');
        expect(pannello).toHaveTextContent('Impostazioni');
        expect(
            screen.getByRole('button', { name: 'Esci' }),
        ).toBeInTheDocument();
    });

    it('su una pagina di Altro è Altro a essere segnato', () => {
        barra('famiglia');

        expect(screen.getByRole('button', { name: 'Altro' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });
});
