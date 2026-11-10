import { describe, expect, it } from 'vite-plus/test';
import { classGroups } from '@/test/classes';

/**
 * The thin edge of the boards: a 1 px border (buttons, menus, chips, the
 * thumbnails of the files, cards, the lines under the tabs) is `edge-soft`.
 * `edge`, darker, is for the fields (1.5 px), the 2 px circles and the dashed
 * boxes. The test reads every `cn(…)` call and every `class="…"` and fails
 * when a 1 px border takes `border-edge`.
 */

/** A border 1 px wide, on every side or on one. */
const thinBorder = /^border(-[trblxy])?$/;

/** A class that gives the border another width or style. */
const otherBorder =
    /^border(-[trblxy])?-(\d+|\[[^\]]+\])$|^border-(dashed|dotted|double)$/;

/**
 * The paper sheets drawn in the box *Documents to complete* of the Home: on
 * the boards *Main* and *Main-pc* they keep the darker edge, to stand out on
 * the light blue.
 */
const darkerOnPurpose: Record<string, number> = {
    '../pages/Home.vue': 2,
};

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
 * The groups of classes of a file with a 1 px border in `edge`.
 *
 * @param source - The text of the file.
 * @returns Each such group, its classes joined by a space.
 */
function darkThinEdges(source: string): string[] {
    return classGroups(source)
        .filter(
            (classes) =>
                classes.includes('border-edge') &&
                classes.some((name) => thinBorder.test(name)) &&
                !classes.some((name) => otherBorder.test(name)),
        )
        .map((classes) => classes.join(' '));
}

describe('edges of the design system', () => {
    it('finds a 1 px border in edge, and leaves the thicker and dashed ones', () => {
        expect(darkThinEdges('<a class="border border-edge bg-card">')).toEqual(
            ['border border-edge bg-card'],
        );
        expect(darkThinEdges("cn('border-b border-edge')")).toEqual([
            'border-b border-edge',
        ]);
        expect(
            darkThinEdges(
                '<a class="border border-edge-soft"><b class="border-[1.5px] border-edge"><i class="border-2 border-dashed border-edge">',
            ),
        ).toEqual([]);
    });

    it.each(Object.keys(files))(
        '%s draws a 1 px border in edge-soft',
        (file) => {
            expect(darkThinEdges(files[file])).toHaveLength(
                darkerOnPurpose[file] ?? 0,
            );
        },
    );
});
