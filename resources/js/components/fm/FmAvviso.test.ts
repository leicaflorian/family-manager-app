import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmAvviso from './FmAvviso.vue';

describe('FmAvviso', () => {
    it('è un messaggio che il lettore di schermo annuncia', () => {
        render(FmAvviso, {
            slots: { default: 'Ti ricorderò di pagarla 3 giorni prima.' },
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Ti ricorderò di pagarla 3 giorni prima.',
        );
    });

    it('nel tono ok è verde, in quello di attenzione arancio', () => {
        const risultato = render(FmAvviso, {
            props: { tono: 'ok' },
            slots: { default: 'Fatto' },
        });
        expect(screen.getByRole('status')).toHaveClass('text-[#14533F]');
        risultato.unmount();

        render(FmAvviso, {
            props: { tono: 'attenzione' },
            slots: { default: 'Manca il libretto' },
        });
        expect(screen.getByRole('status')).toHaveClass('text-attenzione');
    });
});
