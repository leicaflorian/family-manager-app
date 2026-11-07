// @vitest-environment node
/// <reference types="node" />
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import { problems } from './problems';
import { loadStories } from './files';

/** A component with a TSDoc, in the group the test gives it. */
const component = (group: string): string =>
    `<script setup lang="ts">\n/**\n * Shows x.\n *\n * @group ${group}\n */\n</script>`;

/**
 * A folder shaped like the project, with the given files in `components/fm/`.
 *
 * @param files - The content of each file, by its name.
 * @returns The root of the folder.
 */
function project(files: Record<string, string>): string {
    const root = mkdtempSync(join(tmpdir(), 'stories-'));
    const dir = join(root, 'resources/js/components/fm');
    mkdirSync(dir, { recursive: true });
    Object.entries(files).forEach(([file, source]) => {
        writeFileSync(join(dir, file), source);
    });

    return root;
}

describe('the stories of the components', () => {
    it('loads the stories next to the components, by component', () => {
        const stories = loadStories();

        expect(stories.FmRow?.default.title).toBe('Rows/FmRow');
        expect(Object.keys(stories.FmRow ?? {})).toContain('LongTitle');
    });

    it('reports a component without stories', () => {
        const root = project({
            'FmX.vue': component('basics'),
            'FmX.test.ts': '',
        });

        expect(problems(root, {})).toEqual([
            'FmX has no stories: add FmX.stories.ts',
        ]);
    });

    it('reports what a component lacks', () => {
        const root = project({
            'FmNoDoc.vue': '<script setup lang="ts"></script>',
            'FmNoDoc.test.ts': '',
            'FmNoGroup.vue':
                '<script setup lang="ts">\n/** Shows x. */\n</script>',
            'FmNoGroup.test.ts': '',
            'FmEmptyGroup.vue':
                '<script setup lang="ts">\n/**\n * Shows x.\n *\n * @group\n * @deprecated\n */\n</script>',
            'FmEmptyGroup.test.ts': '',
            'FmBadGroup.vue': component('y'),
            'FmBadGroup.test.ts': '',
            'FmNoTest.vue': component('basics'),
            'FmNoCases.vue': component('basics'),
            'FmNoCases.test.ts': '',
            'FmWrongTitle.vue': component('rows'),
            'FmWrongTitle.test.ts': '',
            'FmFine.vue': component('forms'),
            'FmFine.test.ts': '',
        });
        const story = { default: { title: '' }, Only: {} };
        const stories = {
            FmNoDoc: story,
            FmNoGroup: story,
            FmEmptyGroup: story,
            FmBadGroup: story,
            FmNoTest: { default: { title: 'Basics/FmNoTest' }, Only: {} },
            FmNoCases: { default: { title: 'Basics/FmNoCases' } },
            FmWrongTitle: { default: { title: 'Basics/FmWrongTitle' }, A: {} },
            FmFine: { default: { title: 'Forms/FmFine' }, A: {} },
        };

        expect(problems(root, stories)).toEqual([
            'FmBadGroup: unknown group "y"',
            'FmEmptyGroup: no @group',
            'FmNoCases: no stories',
            'FmNoDoc: no TSDoc',
            'FmNoGroup: no @group',
            'FmNoTest: no FmNoTest.test.ts',
            'FmWrongTitle: the title is "Basics/FmWrongTitle", expected "Rows/FmWrongTitle"',
        ]);
    });

    it('finds nothing missing in the components of the canvas', () => {
        expect(problems(process.cwd(), loadStories())).toEqual([]);
    });
});
