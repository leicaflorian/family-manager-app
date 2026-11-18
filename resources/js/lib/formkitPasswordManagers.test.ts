import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import formkitConfig from '@/formkit.config';
import { settle } from '@/test/page';

/**
 * Mounts a field with the configuration of the app.
 *
 * @param type - the type of the field
 * @param attrs - more props of the field
 */
function field(type: string, attrs: Record<string, unknown> = {}): void {
    render(
        {
            render: () =>
                h(FormKit, {
                    type,
                    label: 'Card',
                    options: [{ value: '1', label: 'Marco' }],
                    ...attrs,
                }),
        },
        { global: { plugins: [[formkit, formkitConfig]] } },
    );
}

describe('ignorePasswordManagers', () => {
    afterEach(() => settle());

    it.each(['autocomplete', 'taglist'])(
        'keeps the password managers away from a %s',
        async (type) => {
            field(type);

            const box = await screen.findByRole('combobox', { name: 'Card' });
            expect(box).toHaveAttribute('data-bwignore', 'true');
            expect(box).toHaveAttribute('data-1p-ignore', 'true');
            expect(box).toHaveAttribute('data-lpignore', 'true');
            expect(box).toHaveAttribute('autocomplete', 'off');
        },
    );

    it('keeps the password managers away from a datepicker', async () => {
        field('datepicker');

        const box = await screen.findByLabelText('Card');
        expect(box).toHaveAttribute('data-bwignore', 'true');
    });

    it('leaves the text fields to the password managers', async () => {
        field('email');

        expect(await screen.findByLabelText('Card')).not.toHaveAttribute(
            'data-bwignore',
        );
    });
});
