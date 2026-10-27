import { render } from '@testing-library/vue';
import { House } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmIcon from './FmIcon.vue';

describe('FmIcon', () => {
    it('is only decoration without a label', () => {
        const result = render(FmIcon, { props: { icon: House } });
        const svg = result.container.querySelector('svg');

        expect(svg).toHaveAttribute('aria-hidden', 'true');
        expect(svg).toHaveAttribute('width', '24');
        expect(svg).toHaveAttribute('stroke-width', '2');
    });

    it('is read by screen readers with a label', () => {
        const result = render(FmIcon, {
            props: { icon: House, label: 'Home', size: 20 },
        });

        expect(result.getByRole('img', { name: 'Home' })).toHaveAttribute(
            'width',
            '20',
        );
    });
});
