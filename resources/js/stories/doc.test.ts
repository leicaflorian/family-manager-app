import { describe, expect, it } from 'vite-plus/test';
import { parseDoc } from './doc';

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

    it('gives no group when @group is empty, not the next tag', () => {
        const source =
            '<script setup lang="ts">\n/**\n * Shows x.\n *\n * @group\n * @deprecated\n */\n</script>';

        expect(parseDoc(source).group).toBeNull();
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

    it('reads the TSDoc of a generic component, whose script tag spans lines', () => {
        const source = [
            '<script',
            '    setup',
            '    lang="ts"',
            '    generic="T extends { id: number }"',
            '>',
            '/**',
            ' * A level of the tree.',
            ' *',
            ' * @group forms',
            ' */',
            '</script>',
        ].join('\n');

        expect(parseDoc(source)).toEqual({
            description: 'A level of the tree.',
            group: 'forms',
        });
    });
});
