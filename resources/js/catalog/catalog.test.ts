// @vitest-environment node
/// <reference types="node" />
import { describe, expect, it } from 'vite-plus/test';
import FmRow from '@/components/fm/FmRow.vue';
import { loadExamples } from './examples';
import { renderCatalog } from './markdown';
import type { CatalogEntry } from './meta';
import { readCatalog } from './meta';
import { problems } from './problems';

/** An entry with the given fields, the rest empty. */
function entry(fields: Partial<CatalogEntry>): CatalogEntry {
    return {
        name: 'FmX',
        path: 'resources/js/components/fm/FmX.vue',
        group: 'basics',
        description: 'Shows x.',
        props: [],
        events: [],
        slots: [],
        types: [],
        usedBy: [],
        ...fields,
    };
}

const hint = 'The catalogue is out of date: run `npm run catalog`';

describe('the catalogue', () => {
    it('loads the examples next to the components, by component', () => {
        const examples = loadExamples();

        expect(examples.FmRow?.component).toBe(FmRow);
        expect(examples.FmRow?.examples.map((example) => example.name)).toEqual(
            [
                'Link',
                'Overdue',
                'Button',
                'Area dot',
                'Long title',
                'With a value on the right',
            ],
        );
    });

    it(
        'matches the committed files, written by npm run catalog',
        { timeout: 120_000 },
        async () => {
            const root = process.cwd();
            const entries = readCatalog(root);

            await expect(
                renderCatalog(entries, loadExamples()),
            ).toMatchFileSnapshot('../components/fm/CATALOG.md', hint);
            await expect(
                JSON.stringify(entries, null, 2) + '\n',
            ).toMatchFileSnapshot('./catalog.json', hint);
        },
    );

    it('reports what a component lacks', () => {
        const entries = [
            entry({ name: 'FmNoDoc', description: '', group: null }),
            entry({ name: 'FmNoGroup', group: null }),
            entry({ name: 'FmBadGroup', group: 'y' }),
            entry({ name: 'FmNoExamples' }),
            entry({ name: 'FmNoCases' }),
            entry({ name: 'FmFine' }),
        ];
        const examples = {
            FmNoDoc: { component: FmRow, examples: [{ name: 'A', props: {} }] },
            FmNoGroup: {
                component: FmRow,
                examples: [{ name: 'A', props: {} }],
            },
            FmBadGroup: {
                component: FmRow,
                examples: [{ name: 'A', props: {} }],
            },
            FmNoCases: { component: FmRow, examples: [] },
            FmFine: { component: FmRow, examples: [{ name: 'A', props: {} }] },
        };

        expect(problems(entries, examples)).toEqual([
            'FmNoDoc: no TSDoc',
            'FmNoGroup: no @group',
            'FmBadGroup: unknown group "y"',
            'FmNoExamples: no FmNoExamples.examples.ts',
            'FmNoCases: no cases',
        ]);
    });

    it('finds nothing missing in the components of the canvas', () => {
        expect(problems(readCatalog(process.cwd()), loadExamples())).toEqual(
            [],
        );
    }, 120_000);
});
