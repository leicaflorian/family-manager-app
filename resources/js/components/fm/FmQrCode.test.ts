import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmQrCode from './FmQrCode.vue';

/** A drawing like the one the server makes. */
const svg =
    '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><path fill="#000000" d="M0 0h10v10H0z"/></svg>';

describe('FmQrCode', () => {
    it('shows the drawing as an image with a name', () => {
        render(FmQrCode, { props: { svg, label: 'Code to link the phone' } });

        const image = screen.getByRole('img', {
            name: 'Code to link the phone',
        });

        expect(image.querySelector('svg path')).not.toBeNull();
    });

    it('shows nothing of what is not an SVG', () => {
        render(FmQrCode, {
            props: { svg: '<p>hello</p>', label: 'Code' },
        });

        expect(screen.getByRole('img', { name: 'Code' })).toBeEmptyDOMElement();
    });

    it('drops scripts, handlers and foreign content from the drawing', () => {
        render(FmQrCode, {
            props: {
                svg: '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><foreignObject><div>x</div></foreignObject><a href="javascript:alert(1)"><text>x</text></a><path onclick="alert(1)" d="M0 0h1v1H0z"/></svg>',
                label: 'Code',
            },
        });

        const image = screen.getByRole('img', { name: 'Code' });

        expect(image.querySelector('script')).toBeNull();
        expect(image.querySelector('foreignObject')).toBeNull();
        expect(image.innerHTML).not.toMatch(/onload|onclick|javascript:/);
        expect(image.querySelector('path')).not.toBeNull();
    });
});
