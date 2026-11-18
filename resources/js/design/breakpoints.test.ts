import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';
import { COMPUTER_QUERY } from '@/composables/useLayoutMode';
import { WIDE_QUERY } from '@/composables/useWideLayout';

/**
 * Where the pages and the layouts change: the classes `wide:` (the columns
 * inside a page, as `useWideLayout()`) and `computer:` (the computer layout of
 * `useLayoutMode()`). Their conditions are written in `app.css`, and this test
 * keeps them equal to those of the composables, so there is still one
 * condition each. `lg:` (1024 px, without the height) is not used in pages and
 * layouts any more.
 */

/**
 * The condition of a custom variant of `app.css`.
 *
 * @param name - The name of the variant.
 * @returns What is between the brackets of `@media`, or null without the variant.
 */
function variantQuery(name: string): string | null {
    const match = new RegExp(
        `@custom-variant ${name} \\(@media (.+?)\\);`,
    ).exec(appCss);

    return match?.[1] ?? null;
}

// Read from the disk: Vitest does not give the text of a stylesheet
const appCss = readFileSync('resources/css/app.css', 'utf8');

// No type argument on the glob: see `.claude/learnings.md`.
const files: Record<string, string> = import.meta.glob(
    ['../pages/**/*.vue', '../layouts/**/*.vue'],
    { query: '?raw', import: 'default', eager: true },
);

describe('Breakpoints', () => {
    it('writes wide: with the condition of useWideLayout()', () => {
        expect(variantQuery('wide')).toBe(WIDE_QUERY);
    });

    it('writes computer: with the condition of the computer layout', () => {
        expect(variantQuery('computer')).toBe(COMPUTER_QUERY);
    });

    it('has no lg: in the pages and the layouts', () => {
        const withLg = Object.entries(files)
            .filter(([, source]) => /(^|[\s'"`:])(max-)?lg:/m.test(source))
            .map(([path]) => path);

        expect(withLg).toEqual([]);
    });
});
