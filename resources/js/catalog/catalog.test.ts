// @vitest-environment node
/// <reference types="node" />
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import FmRow from '@/components/fm/FmRow.vue';
import type { ComponentExamples } from './examples';
import { loadExamples } from './examples';
import { renderCatalog } from './markdown';
import type { CatalogEntry } from './meta';
import { parseDoc, readCatalog } from './meta';
import { problems } from './problems';

/** An entry with the given fields, the rest empty. */
function entry(fields: Partial<CatalogEntry>): CatalogEntry {
    const name = fields.name ?? 'FmX';

    return {
        name,
        path: `resources/js/components/fm/${name}.vue`,
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

const emptyGroup =
    '<script setup lang="ts">\n/**\n * Shows x.\n *\n * @group\n * @deprecated\n */\n</script>';

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
        const examples: Record<string, ComponentExamples> = {
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

        // Every component but FmNoTest has its test file
        const root = mkdtempSync(join(tmpdir(), 'catalog-'));
        const dir = join(root, 'resources/js/components/fm');
        mkdirSync(dir, { recursive: true });
        entries.forEach(({ name }) => {
            if (name !== 'FmNoTest') {
                writeFileSync(join(dir, `${name}.test.ts`), '');
            }
        });
        entries.push(
            entry({
                name: 'FmNoTest',
                path: 'resources/js/components/fm/FmNoTest.vue',
            }),
        );
        examples.FmNoTest = {
            component: FmRow,
            examples: [{ name: 'A', props: {} }],
        };

        expect(problems(entries, examples, root)).toEqual([
            'FmNoDoc: no TSDoc',
            'FmNoGroup: no @group',
            'FmBadGroup: unknown group "y"',
            'FmNoExamples: no FmNoExamples.examples.ts',
            'FmNoCases: no cases',
            'FmNoTest: no FmNoTest.test.ts',
        ]);
    });

    it('reports an empty @group as no group', () => {
        const root = mkdtempSync(join(tmpdir(), 'catalog-'));
        mkdirSync(join(root, 'resources/js/components/fm'), {
            recursive: true,
        });
        writeFileSync(join(root, 'resources/js/components/fm/FmX.test.ts'), '');

        expect(
            problems(
                [entry({ group: parseDoc(emptyGroup).group })],
                {
                    FmX: {
                        component: FmRow,
                        examples: [{ name: 'A', props: {} }],
                    },
                },
                root,
            ),
        ).toEqual(['FmX: no @group']);
    });

    it('finds nothing missing in the components of the canvas', () => {
        expect(problems(readCatalog(process.cwd()), loadExamples())).toEqual(
            [],
        );
    }, 120_000);
});
