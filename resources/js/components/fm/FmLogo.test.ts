import { render } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmLogo from './FmLogo.vue';

describe('FmLogo', () => {
    it('is the roof with the family on the light blue square', () => {
        const result = render(FmLogo, { props: { size: 36 } });
        const svg = result.container.querySelector('svg');

        expect(svg).toHaveAttribute('aria-hidden', 'true');
        expect(svg).toHaveAttribute('width', '36');
        expect(result.container.querySelector('rect')).toHaveAttribute(
            'fill',
            '#3399FF',
        );
    });

    it('has no square in white, on the blue panel', () => {
        const result = render(FmLogo, { props: { white: true } });

        expect(result.container.querySelector('rect')).toBeNull();
    });
});
