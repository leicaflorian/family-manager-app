import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmRiga from './FmRiga.vue';

describe('FmRiga', () => {
    it('con href è un link che copre tutta la riga', () => {
        render(FmRiga, {
            props: {
                titolo: 'Bolletta luce',
                sottotitolo: 'Luce - Edison · tra 3 giorni',
                href: '/documenti/1',
            },
            slots: { destra: '71,30 €' },
        });

        const link = screen.getByRole('link');
        expect(link).toHaveAttribute('href', '/documenti/1');
        expect(link).toHaveTextContent('Bolletta luce');
        expect(link).toHaveTextContent('Luce - Edison · tra 3 giorni');
        expect(link).toHaveTextContent('71,30 €');
    });

    it('senza href non è un link', () => {
        render(FmRiga, { props: { titolo: 'Marco Bianchi' } });

        expect(screen.queryByRole('link')).toBeNull();
        expect(screen.getByText('Marco Bianchi')).toBeInTheDocument();
    });

    it("il pallino ha il colore dell'area", () => {
        const risultato = render(FmRiga, {
            props: { titolo: 'Bolletta luce', area: 'casa' },
        });

        expect(risultato.container.querySelector('[data-pallino]')).toHaveClass(
            'bg-casa',
        );
    });

    it('il sottotitolo scaduto è rosso', () => {
        render(FmRiga, {
            props: {
                titolo: 'Tessera sanitaria',
                sottotitolo: 'Scaduta il 20/10/2026',
                tono: 'scaduto',
            },
        });

        expect(screen.getByText('Scaduta il 20/10/2026')).toHaveClass(
            'text-scaduto',
        );
    });
});
