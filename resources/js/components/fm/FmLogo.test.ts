import { render } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmLogo from './FmLogo.vue';

describe('FmLogo', () => {
    it('è il tetto con la famiglia sul quadrato azzurro', () => {
        const risultato = render(FmLogo, { props: { dimensione: 36 } });
        const svg = risultato.container.querySelector('svg');

        expect(svg).toHaveAttribute('aria-hidden', 'true');
        expect(svg).toHaveAttribute('width', '36');
        expect(risultato.container.querySelector('rect')).toHaveAttribute(
            'fill',
            '#3399FF',
        );
    });

    it('bianco, sul pannello azzurro, non ha il quadrato', () => {
        const risultato = render(FmLogo, { props: { bianco: true } });

        expect(risultato.container.querySelector('rect')).toBeNull();
    });
});
