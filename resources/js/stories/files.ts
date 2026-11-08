/**
 * The story files of the components of the canvas, loaded for the tests: the
 * check of what a component lacks (`stories.test.ts`) and the mounting of
 * every story (`AllStories.test.ts`).
 */
import type { StoryModule } from './problems';

/**
 * Every `.stories.ts` of `components/fm/`, by the name of its component, and
 * every one of `stories/fields/` (the FormKit fields), by its own name.
 *
 * @returns The stories of each file, as `{ FmRow: …, Text: … }`.
 */
export function loadStories(): Record<string, StoryModule> {
    // No type argument on the glob: Vitest keeps a transformed module in its
    // file cache unless its source has the text `import.meta.glob(`, and with
    // `glob<…>(` a new `.stories.ts` would stay unseen by the tests.
    const files: Record<string, StoryModule> = import.meta.glob(
        ['../components/fm/*.stories.ts', './fields/*.stories.ts'],
        { eager: true },
    );

    return Object.fromEntries(
        Object.entries(files).map(([path, stories]) => [
            path.replace(/^.*\/(\w+)\.stories\.ts$/, '$1'),
            stories,
        ]),
    );
}
