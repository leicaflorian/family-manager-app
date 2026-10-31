// @vitest-environment node
/// <reference types="node" />
import { describe, expect, it } from 'vite-plus/test';
import FmRow from '@/components/fm/FmRow.vue';
import { loadExamples } from './examples';
import { renderCatalog } from './markdown';
import { readCatalog } from './meta';

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
});
