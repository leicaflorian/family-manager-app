import { describe, expect, it } from 'vite-plus/test';

/**
 * Every colour of the pages comes from the design tokens (`tokens.css`): no
 * hex, `rgb()`, `hsl()` or `oklch()` written by hand, and no `white` or `black`
 * class, which would stay the same in the dark theme.
 */

/** A file allowed to write a colour, with the reason. */
type Exception = {
    /** The path of the file, from `resources/js/`. */
    file: string;
    /** What it may write. */
    pattern: RegExp;
    /** Why it needs it. */
    reason: string;
};

const exceptions: Exception[] = [];

/** A colour written by hand. */
const colourPattern = /#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(|oklch\(/g;

/** A Tailwind class with a colour that does not change with the theme. */
const whiteBlackPattern =
    /\b(bg|text|border|fill|stroke|ring|outline|from|to|via)-(white|black)\b/g;

// No type argument on the glob: see `.claude/learnings.md`, the Vitest file
// cache would keep an old list of files.
const files: Record<string, string> = import.meta.glob(
    [
        '../**/*.{vue,ts}',
        '!../**/*.test.ts',
        '!../**/*.examples.ts',
        '!../test/**',
        '!../catalog/**',
        '!../generated/**',
        '!../wayfinder/**',
        '!../actions/**',
        '!../routes/**',
        '!./tokens.ts',
    ],
    { eager: true, query: '?raw', import: 'default' },
);

/**
 * The text of a file without its comments, with the same lines, so that a
 * match can say its line.
 *
 * @param source - The text of the file.
 * @returns The text with every comment blanked out.
 */
function withoutComments(source: string): string {
    const blank = (comment: string): string => comment.replace(/[^\n]/g, ' ');

    return source
        .replace(/\/\*[\s\S]*?\*\//g, blank)
        .replace(/<!--[\s\S]*?-->/g, blank)
        .replace(
            /(^|[^:'"`])\/\/.*$/gm,
            (match, before: string) =>
                before + blank(match.slice(before.length)),
        );
}

/**
 * Every colour written by hand in the files, as `path:line: match`.
 *
 * @returns One entry per colour found outside the exceptions.
 */
function offenders(): string[] {
    const found: string[] = [];

    for (const [path, source] of Object.entries(files)) {
        const file = path.replace(/^\.\.\//, '');
        const allowed = exceptions.filter((entry) => entry.file === file);

        withoutComments(source)
            .split('\n')
            .forEach((line, index) => {
                for (const pattern of [colourPattern, whiteBlackPattern]) {
                    for (const match of line.matchAll(pattern)) {
                        const excepted = allowed.some((entry) =>
                            entry.pattern.test(line),
                        );

                        if (!excepted) {
                            found.push(`${file}:${index + 1}: ${match[0]}`);
                        }
                    }
                }
            });
    }

    return found;
}

describe('colours of the pages', () => {
    it('reads the files of the pages', () => {
        expect(Object.keys(files)).toContain('../app.ts');
        expect(Object.keys(files)).toContain('../components/fm/FmButton.vue');
        expect(Object.keys(files)).not.toContain('./tokens.ts');
    });

    it('ignores colours in comments', () => {
        const text = withoutComments(
            "a /* #fff */ b\n<!-- rgb( -->\nc // #000\n'https://x' // d",
        );

        expect(text.split('\n').map((line) => line.trim())).toEqual([
            'a            b',
            '',
            'c',
            "'https://x'",
        ]);
    });

    it('finds no colour written by hand outside the design tokens', () => {
        expect(offenders()).toEqual([]);
    });

    it('gives a reason for every exception', () => {
        for (const entry of exceptions) {
            expect(entry.reason.length).toBeGreaterThan(10);
        }
    });
});
