import { describe, expect, it } from 'vite-plus/test';
import { classesOf, classGroups } from '@/test/classes';

/**
 * The white surfaces on the background of the page: a card, a list, a number
 * tile, a wide panel (`bg-card` with the radius of a card or of a sheet) take
 * `shadow-card`, the lightest shadow of the design system, so they do not get
 * lost on the background. A surface with another shadow (`shadow-float`,
 * `shadow-menu`, `shadow-dialog`) keeps it, and a dashed box (the empty place
 * of something to add) stays flat. The test reads every `cn(…)` call and
 * every tag of a template (its `class` and `:class` together) and fails when
 * such a surface has no shadow.
 */

/** The radius of a card or of a sheet, also from the computer up. */
const surfaceRadius = /^(lg:)?rounded-(card|card-lg|sheet)$/;

/** A white background, also from the computer up. */
const whiteBackground = /^(lg:)?bg-card$/;

/** Any shadow, also from a breakpoint up. */
const shadow = /^([a-z0-9]+:)*shadow-/;

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
        '!../formkit.config.ts',
    ],
    { eager: true, query: '?raw', import: 'default' },
);

/**
 * The classes of each tag of a template, from all its attributes: an element
 * can split its classes between `class` and `:class`.
 *
 * @param source - The text of the file.
 * @returns The classes of each tag.
 */
function tagGroups(source: string): string[][] {
    const groups: string[][] = [];
    const opening = /<[A-Za-z]/g;
    let match: RegExpExecArray | null;

    while ((match = opening.exec(source)) !== null) {
        let at = match.index + 1;
        let quoted = false;

        // A `>` inside an attribute value (an arrow, a comparison) does not
        // close the tag.
        while (at < source.length && (quoted || source[at] !== '>')) {
            quoted = source[at] === '"' ? !quoted : quoted;
            at++;
        }

        const attributes = [
            ...source.slice(match.index, at).matchAll(/"([^"]*)"/g),
        ].map((value) => value[1]);

        groups.push([
            ...classesOf(attributes.map((value) => `"${value}"`).join(' ')),
            ...classesOf(attributes.join(' ')),
        ]);
    }

    return groups;
}

/**
 * The groups of classes of a file that draw a white surface without a shadow.
 *
 * @param source - The text of the file.
 * @returns Each such group, its classes joined by a space.
 */
function flatSurfaces(source: string): string[] {
    return (
        [...classGroups(source), ...tagGroups(source)]
            .filter(
                (classes) =>
                    classes.some((name) => whiteBackground.test(name)) &&
                    classes.some((name) => surfaceRadius.test(name)) &&
                    !classes.some((name) => shadow.test(name)) &&
                    !classes.includes('border-dashed'),
            )
            .map((classes) => classes.join(' '))
            // A `class="…"` is read both alone and with its tag.
            .filter((group, index, all) => all.indexOf(group) === index)
    );
}

describe('white surfaces of the design system', () => {
    it('finds a white card without a shadow, and leaves the others', () => {
        expect(flatSurfaces('<a class="rounded-card bg-card p-4">')).toEqual([
            'rounded-card bg-card p-4',
        ]);
        expect(flatSurfaces("cn('lg:rounded-sheet lg:bg-card')")).toEqual([
            'lg:rounded-sheet lg:bg-card',
        ]);
        expect(
            flatSurfaces(
                '<a class="rounded-card bg-card shadow-card"><s class="rounded-card border-dashed bg-card"><b class="rounded-card-lg bg-card lg:shadow-float"><i class="rounded-lg bg-card"><u class="rounded-t-sheet bg-card">',
            ),
        ).toEqual([]);
    });

    it('reads class and :class of the same tag together', () => {
        expect(
            flatSurfaces(
                `<div class="bg-card" :class="open ? 'rounded-card' : 'rounded-card-lg p-5'">`,
            ),
        ).toHaveLength(1);
        expect(
            flatSurfaces(
                `<div class="bg-card shadow-card" :class="(a) => a > 1 && 'rounded-card'">`,
            ),
        ).toEqual([]);
    });

    it.each(Object.keys(files))(
        '%s gives its white surfaces a shadow',
        (file) => {
            expect(flatSurfaces(files[file])).toEqual([]);
        },
    );
});
