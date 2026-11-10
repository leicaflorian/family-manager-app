import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import formkitConfig from '@/formkit.config';

/**
 * Mounts one field with the configuration of the app.
 *
 * @param props - the props of the field
 */
function field(props: Record<string, unknown>): void {
    render(
        { render: () => h(FormKit, props) },
        { global: { plugins: [[formkit, formkitConfig]] } },
    );
}

describe('markOptional', () => {
    it('writes «(optional)» after the label of a field that may stay empty, softer', async () => {
        field({ type: 'text', label: 'Organization', optional: true });

        const mark = await screen.findByText('(optional)');
        expect(mark).toHaveClass('text-body-sm', 'text-ink-soft');
        expect(mark.closest('label')).toHaveTextContent(
            'Organization (optional)',
        );
    });

    it('marks also the menus with the search', async () => {
        field({
            type: 'autocomplete',
            label: 'Linked card',
            options: [],
            optional: true,
        });

        expect(
            await screen.findByRole('combobox', {
                name: 'Linked card (optional)',
            }),
        ).toBeInTheDocument();
    });

    it('reads the prop written bare in a template, which arrives empty', async () => {
        field({ type: 'text', label: 'Holder', optional: '' });

        expect(await screen.findByText('(optional)')).toBeInTheDocument();
    });

    it('keeps the text of each option of a radio, which uses the same section', async () => {
        field({
            type: 'radio',
            label: 'What they can do',
            options: [
                { value: 'admin', label: 'Everything, like you' },
                { value: 'member', label: 'Sees everything' },
            ],
        });

        expect(
            await screen.findByRole('radio', { name: 'Everything, like you' }),
        ).toBeInTheDocument();
    });

    it('leaves the label alone without the prop', async () => {
        field({ type: 'text', label: 'Title' });

        await screen.findByText('Title');
        expect(screen.queryByText('(optional)')).toBeNull();
    });
});
