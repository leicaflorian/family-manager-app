// @vitest-environment node
/// <reference types="node" />
import { readdirSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import type { CatalogEntry } from './meta';
import { parseDoc, readCatalog } from './meta';

describe('parseDoc', () => {
    it('reads the description and the group of the component', () => {
        const source = [
            '<script setup lang="ts">',
            '/**',
            ' * A list row of the canvas.',
            ' *',
            ' * @group rows',
            ' */',
            "import { computed } from 'vue';",
            '/** The colour. */',
            'export type Tone = string;',
            '</script>',
        ].join('\n');

        expect(parseDoc(source)).toEqual({
            description: 'A list row of the canvas.',
            group: 'rows',
        });
    });

    it('gives no group when the tag is missing', () => {
        const source =
            '<script setup lang="ts">\n/** A button of the canvas. */\n</script>';

        expect(parseDoc(source)).toEqual({
            description: 'A button of the canvas.',
            group: null,
        });
    });

    it('gives an empty description without a TSDoc', () => {
        const source =
            '<script setup lang="ts">\nimport { ref } from \'vue\';\n</script>';

        expect(parseDoc(source)).toEqual({ description: '', group: null });
    });

    it('joins the lines of a long description as the TSDoc text', () => {
        const source = [
            '<template><div /></template>',
            '<script setup lang="ts">',
            '/**',
            ' * A panel that comes up from the bottom,',
            ' * with a title.',
            ' *',
            ' * On wide screens it is a dialog.',
            ' */',
            '</script>',
        ].join('\n');

        expect(parseDoc(source).description).toBe(
            'A panel that comes up from the bottom,\nwith a title.\n\nOn wide screens it is a dialog.',
        );
    });
});

describe('readCatalog', () => {
    it('reads every component of the canvas', { timeout: 120_000 }, () => {
        const catalog = readCatalog(process.cwd());
        const entry = (name: string): CatalogEntry => {
            const found = catalog.find((item) => item.name === name);

            if (!found) {
                throw new Error(`${name} is not in the catalogue`);
            }

            return found;
        };

        const files = readdirSync(
            join(process.cwd(), 'resources/js/components/fm'),
        ).filter((file) => file.endsWith('.vue'));
        expect(catalog).toHaveLength(files.length);
        expect(catalog.map((item) => item.name)).toEqual(
            files.map((file) => file.replace(/\.vue$/, '')).sort(),
        );

        const row = entry('FmRow');
        expect(row.path).toBe('resources/js/components/fm/FmRow.vue');
        expect(row.description.startsWith('A list row of the canvas')).toBe(
            true,
        );
        expect(row.props.find((prop) => prop.name === 'title')).toMatchObject({
            type: 'string',
            required: true,
            default: null,
        });
        expect(
            row.props.find((prop) => prop.name === 'external'),
        ).toMatchObject({ type: 'boolean', required: false, default: 'false' });
        expect(row.props.some((prop) => prop.name === 'class')).toBe(false);
        expect(row.types).toContain(
            "export type RowTone = 'normal' | 'overdue' | 'warning' | 'paid';",
        );
        expect(row.events).toEqual(['click']);
        expect(row.slots).toEqual(expect.arrayContaining(['before', 'after']));
        expect(row.usedBy).toContain('components/fm/FmDocumentRow.vue');
        expect(row.usedBy).toContain('pages/settings/Account.vue');
        expect(row.usedBy).not.toContain('components/fm/FmRow.test.ts');
        expect(row.usedBy).toEqual([...row.usedBy].sort());

        expect(entry('FmConfirm').events).toEqual(
            expect.arrayContaining(['confirm', 'update:open']),
        );
        expect(entry('FmSearchField').events).toContain('update:modelValue');
    });
});
