import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';

/**
 * The size of buttons and fields comes from two CSS variables: 44 px with a
 * radius of 10 px on the page, 52 px with 14 px inside a panel from below
 * (`[data-sheet]`). The classes `h-control` and `rounded-control` read them.
 */
const css = readFileSync(
    resolve(__dirname, '../../css/app.css'),
    'utf8',
).replace(/\s+/g, ' ');

describe('control size', () => {
    it('is 44 px with a radius of 10 px by default', () => {
        expect(css).toMatch(
            /:root \{[^}]*--control-height: var\(--spacing-control-md\);[^}]*--control-radius: var\(--radius-md\);/,
        );
    });

    it('is 52 px with a radius of 14 px in a panel from below', () => {
        expect(css).toMatch(
            /\[data-sheet\] \{[^}]*--control-height: var\(--spacing-control-lg\);[^}]*--control-radius: var\(--radius-xl\);/,
        );
    });

    it('gives the classes of Tailwind that read the variables', () => {
        expect(css).toMatch(
            /@theme inline \{[^}]*--spacing-control: var\(--control-height\);[^}]*--radius-control: var\(--control-radius\);/,
        );
    });
});
