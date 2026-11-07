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
    /\b(bg|text|border|fill|stroke|ring-offset|ring|outline|from|to|via|divide|shadow|placeholder|caret|accent|decoration)-(white|black)\b/g;

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
 * match can say its line. Quoted strings are skipped, so `'lang/*.json'` or
 * `accept="image/*"` do not open a comment; a `'` or `"` string ends at the end
 * of its line, so an apostrophe in a text of a template hides nothing more.
 *
 * @param source - The text of the file.
 * @returns The text with every comment blanked out.
 */
function withoutComments(source: string): string {
    const blank = (comment: string): string => comment.replace(/[^\n]/g, ' ');
    let result = '';
    let at = 0;

    /**
     * Where a piece that starts at `from` ends: just after `close`, or at
     * the end of the text.
     */
    const endOf = (close: string, from: number): number => {
        const found = source.indexOf(close, from);

        return found === -1 ? source.length : found + close.length;
    };

    while (at < source.length) {
        const char = source[at];
        const rest = source.slice(at, at + 4);
        let end: number;
        let comment = true;

        if (rest.startsWith('/*')) {
            end = endOf('*/', at + 2);
        } else if (rest === '<!--') {
            end = endOf('-->', at + 4);
        } else if (rest.startsWith('//') && source[at - 1] !== ':') {
            end = source.indexOf('\n', at);
            end = end === -1 ? source.length : end;
        } else if (char === "'" || char === '"' || char === '`') {
            comment = false;
            end = at + 1;

            while (end < source.length && source[end] !== char) {
                if (char !== '`' && source[end] === '\n') {
                    break;
                }

                end += source[end] === '\\' ? 2 : 1;
            }

            end = Math.min(end + 1, source.length);
        } else {
            result += char;
            at += 1;
            continue;
        }

        const piece = source.slice(at, end);
        result += comment ? blank(piece) : piece;
        at = end;
    }

    return result;
}

/**
 * Every colour written by hand in one file, as `path:line: match`.
 *
 * @param file - The path of the file, from `resources/js/`.
 * @param source - The text of the file.
 * @param allowed - The exceptions of the file.
 * @returns One entry per colour found outside the exceptions.
 */
function offendersIn(
    file: string,
    source: string,
    allowed: Exception[] = [],
): string[] {
    const found: string[] = [];

    withoutComments(source)
        .split('\n')
        .forEach((line, index) => {
            // `search` ignores `lastIndex`, so an exception written with /g
            // matches every line, not every other one
            const excepted = allowed.some(
                (entry) => line.search(entry.pattern) !== -1,
            );

            if (excepted) {
                return;
            }

            for (const pattern of [colourPattern, whiteBlackPattern]) {
                for (const match of line.matchAll(pattern)) {
                    found.push(`${file}:${index + 1}: ${match[0]}`);
                }
            }
        });

    return found;
}

/**
 * Every colour written by hand in the files of the pages.
 *
 * @returns One entry per colour found outside the exceptions.
 */
function offenders(): string[] {
    return Object.entries(files).flatMap(([path, source]) => {
        const file = path.replace(/^\.\.\//, '');

        return offendersIn(
            file,
            source,
            exceptions.filter((entry) => entry.file === file),
        );
    });
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

    it('still finds a colour after a string with /* and */ in it', () => {
        expect(
            offendersIn(
                'x.vue',
                "const a = 'lang/*.json';\nconst c = '#123456';\nconst b = '*/';\n" +
                    '<input accept="image/*" class="text-[#4A5565]" />',
            ),
        ).toEqual(['x.vue:2: #123456', 'x.vue:4: #4A5565']);
    });

    it('does not let an apostrophe of a text hide the next lines', () => {
        expect(
            offendersIn('x.vue', '<p>Don\'t</p>\n<p class="bg-[#E3E8EF]"></p>'),
        ).toEqual(['x.vue:2: #E3E8EF']);
    });

    it('finds the white and black of every colour utility', () => {
        expect(
            offendersIn(
                'x.vue',
                'divide-white ring-offset-black shadow-white placeholder-black caret-white accent-black decoration-white',
            ),
        ).toHaveLength(7);
    });

    it('applies an exception written with /g to every line', () => {
        const allowed: Exception[] = [
            { file: 'x.ts', pattern: /bg-black/g, reason: 'only a test case' },
        ];

        expect(
            offendersIn('x.ts', 'bg-black\nbg-black\nbg-black', allowed),
        ).toEqual([]);
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
