import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import formkitConfig from '@/formkit.config';
import { search, settle } from '@/test/page';

/**
 * Mounts a menu searched by typing, with the configuration of the app.
 *
 * @param props - more props of the field
 */
function menu(props: Record<string, unknown> = {}): void {
    render(
        {
            render: () =>
                h(FormKit, {
                    type: 'autocomplete',
                    label: 'Person',
                    options: [
                        { value: '1', label: 'Marco' },
                        { value: '2', label: 'Laura' },
                    ],
                    ...props,
                }),
        },
        { global: { plugins: [[formkit, formkitConfig]] } },
    );
}

describe('sayNoResults', () => {
    afterEach(() => settle());

    it('says there are no results when nothing matches what the person types', async () => {
        menu();

        await search('Person', 'zzz');

        expect(await screen.findByText('No results')).toBeInTheDocument();
    });

    it('keeps the message the page gives', async () => {
        menu({ showEmptyMessage: true, emptyMessage: 'Press Enter' });

        await search('Person', 'zzz');

        expect(await screen.findByText('Press Enter')).toBeInTheDocument();
        expect(screen.queryByText('No results')).not.toBeInTheDocument();
    });
});
