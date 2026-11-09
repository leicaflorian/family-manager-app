import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

/**
 * A text style of the design system (`text-meta`, `text-body-sm`, `text-label`…)
 * carries size, line height and weight together. A `font-*` weight or a
 * `leading-*` on the same element overrides them, so the text no longer
 * matches the boards: a weight that changes with the state uses the strong
 * style instead (`body-sm` → `label`, `meta` → `meta-strong`). The test reads
 * every `cn(…)` call and every `class="…"`, where the classes of one element
 * are written, and fails when a style sits beside a weight or a line height.
 */

/** The shape of the text styles in `tokens.json`. */
type Tokens = {
    type: { groups: { styles: { name: string }[] }[] };
};

const tokens = JSON.parse(
    readFileSync('resources/design/tokens.json', 'utf8'),
) as Tokens;

/** The names of the text styles, as Tailwind classes. */
const styles = new Set(
    tokens.type.groups.flatMap((group) =>
        group.styles.map((style) => `text-${style.name}`),
    ),
);

/** A class that changes the weight or the line height by hand. */
const overridePattern =
    /^(font-(thin|light|normal|medium|semibold|bold|extrabold|black)|leading-.+)$/;

// No type argument on the glob: see `.claude/learnings.md`, the Vitest file
// cache would keep an old list of files.
const files: Record<string, string> = import.meta.glob(
    [
        '../**/*.{vue,ts}',
        '!../**/*.test.ts',
        '!../test/**',
        '!../generated/**',
        '!../wayfinder/**',
        '!../actions/**',
        '!../routes/**',
        // FormKit sums its classes with its own rules (`$reset`), see AGENTS.md.
        '!../formkit.config.ts',
    ],
    { eager: true, query: '?raw', import: 'default' },
);

/**
 * The text of each `cn(…)` call of a file, up to its closing bracket.
 *
 * @param source - The text of the file.
 * @returns The arguments of every call, as written.
 */
function cnCalls(source: string): string[] {
    const calls: string[] = [];
    const opening = /\bcn\(/g;
    let match: RegExpExecArray | null;

    while ((match = opening.exec(source)) !== null) {
        let depth = 1;
        let at = match.index + match[0].length;
        let quote: string | null = null;

        while (at < source.length && depth > 0) {
            const char = source[at];

            if (quote !== null) {
                quote =
                    char === quote && source[at - 1] !== '\\' ? null : quote;
            } else if (char === "'" || char === '"' || char === '`') {
                quote = char;
            } else if (char === '(') {
                depth++;
            } else if (char === ')') {
                depth--;
            }

            at++;
        }

        calls.push(source.slice(match.index + match[0].length, at - 1));
    }

    return calls;
}

/**
 * The classes written in a piece of code: the words of its quoted strings.
 *
 * @param code - Arguments of a call, or the value of a `class`.
 * @returns Every class, once.
 */
function classesOf(code: string): Set<string> {
    const strings = code.match(/'[^']*'|"[^"]*"|`[^`]*`/g) ?? [];

    return new Set(
        strings.flatMap((string) => string.slice(1, -1).split(/\s+/)),
    );
}

/**
 * The places of a file where a text style sits beside a weight or a line
 * height.
 *
 * @param source - The text of the file.
 * @returns For each place, the style and the classes that override it.
 */
function clashes(source: string): string[] {
    const groups = [
        ...cnCalls(source),
        ...[...source.matchAll(/\sclass="([^"]*)"/g)].map(
            (match) => `"${match[1]}"`,
        ),
    ];

    return groups.flatMap((group) => {
        const classes = [...classesOf(group)];
        const style = classes.filter((name) => styles.has(name));
        const overrides = classes.filter((name) => overridePattern.test(name));

        return style.length > 0 && overrides.length > 0
            ? [`${style.join(' ')} + ${overrides.join(' ')}`]
            : [];
    });
}

describe('text styles of the design system', () => {
    it('reads the styles from the tokens', () => {
        expect(styles).toContain('text-body-sm');
        expect(styles).toContain('text-label');
    });

    it('finds a weight beside a style in the same call', () => {
        expect(
            clashes("cn('text-body-sm', on ? 'font-bold' : 'font-medium')"),
        ).toEqual(['text-body-sm + font-bold font-medium']);
        expect(clashes('<span class="text-meta leading-5">')).toEqual([
            'text-meta + leading-5',
        ]);
        expect(clashes("cn('text-body-sm', on ? 'text-label' : '')")).toEqual(
            [],
        );
    });

    it.each(Object.keys(files))('%s does not override a text style', (file) => {
        expect(clashes(files[file])).toEqual([]);
    });
});
