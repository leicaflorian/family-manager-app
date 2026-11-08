import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

/**
 * `resources/design/boards.json` says on which board of the canvas of the
 * components (Claude Design) each component of `components/fm/` is drawn, or
 * `pages` for the blocks that live in the boards of the pages. The canvas is
 * private, so the test cannot open it: it checks that every component has its
 * place decided, which reminds whoever adds one to draw its card.
 */

/** The shape of `boards.json`. */
type Boards = {
    /** The address of the canvas of the components. */
    canvas: string;
    /** The boards of the canvas, by the name of their file without `.dc.html`. */
    boards: string[];
    /** The board of each component, or `pages`. */
    components: Record<string, string>;
};

const boards = JSON.parse(
    readFileSync('resources/design/boards.json', 'utf8'),
) as Boards;

// No type argument on the glob: see `.claude/learnings.md`, the Vitest file
// cache would keep an old list of files.
const sources: Record<string, string> = import.meta.glob(
    '../components/fm/*.vue',
    { query: '?raw', import: 'default', eager: true },
);

/** The source of each component, by its name. */
const components = new Map(
    Object.entries(sources).map(([path, source]) => [
        path.replace(/^.*\/(\w+)\.vue$/, '$1'),
        source,
    ]),
);

/**
 * The group a component names in its TSDoc.
 *
 * @param source - The source of the component.
 * @returns The group, or `null` when it names none.
 */
function groupOf(source: string): string | null {
    return /@group (\w+)/.exec(source)?.[1] ?? null;
}

describe('boards.json', () => {
    it('puts every component on a board or in pages', () => {
        const misplaced = [...components.keys()].filter((name) => {
            const board = boards.components[name];

            return (
                board === undefined ||
                (board !== 'pages' && !boards.boards.includes(board))
            );
        });

        expect(misplaced).toEqual([]);
    });

    it('names every component once', () => {
        // JSON.parse keeps only the last of two equal keys, so the raw text is
        // read: a second line for the same component would hide the first.
        const names = [
            ...readFileSync('resources/design/boards.json', 'utf8').matchAll(
                /^\s*"(Fm\w+)"\s*:/gm,
            ),
        ].map((match) => match[1]);
        const repeated = names.filter(
            (name, index) => names.indexOf(name) !== index,
        );

        expect(repeated).toEqual([]);
    });

    it('names no component that is gone', () => {
        const gone = Object.keys(boards.components).filter(
            (name) => !components.has(name),
        );

        expect(gone).toEqual([]);
    });

    it('keeps page sections off the boards', () => {
        const onBoards = [...components.entries()]
            .filter(([, source]) =>
                ['sections', 'forms'].includes(groupOf(source) ?? ''),
            )
            .map(([name]) => name)
            .filter((name) => boards.components[name] !== 'pages');

        expect(onBoards).toEqual([]);
    });

    it('points to a canvas on claude.ai', () => {
        expect(boards.canvas).toMatch(
            /^https:\/\/claude\.ai\/(code\/)?artifact\/[A-Za-z0-9-]+$/,
        );
    });
});
