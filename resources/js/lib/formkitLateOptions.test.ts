import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import formkitConfig from '@/formkit.config';
import { settle } from '@/test/page';

type Option = { label: string; value: string };

/**
 * Mounts a dropdown with the configuration of the app, whose options and value the test changes.
 *
 * @returns the options and the value
 */
function menu(): {
    options: ReturnType<typeof ref<Option[]>>;
    value: ReturnType<typeof ref<string | null>>;
} {
    const options = ref<Option[]>([]);
    const value = ref<string | null>(null);

    render(
        defineComponent({
            setup() {
                return () =>
                    h(FormKit, {
                        type: 'dropdown',
                        label: 'Scanner',
                        placeholder: 'Choose',
                        options: options.value,
                        modelValue: value.value,
                    });
            },
        }),
        { global: { plugins: [[formkit, formkitConfig]] } },
    );

    return { options, value };
}

describe('labelLateOptions', () => {
    it('shows the label of the value when the value and its option arrive together', async () => {
        const { options, value } = menu();
        await settle();

        options.value = [{ label: 'SP-1120N · USB', value: 'sp' }];
        value.value = 'sp';
        await settle();

        expect(
            screen.getByRole('button', { name: /^Scanner/ }),
        ).toHaveTextContent('SP-1120N · USB');
    });

    it('keeps the label when another option arrives later', async () => {
        const { options, value } = menu();
        await settle();
        options.value = [{ label: 'SP-1120N · USB', value: 'sp' }];
        value.value = 'sp';
        await settle();

        options.value = [
            { label: 'SP-1120N · USB', value: 'sp' },
            { label: 'Canon · Network', value: 'canon' },
        ];
        await settle();

        expect(
            screen.getByRole('button', { name: /^Scanner/ }),
        ).toHaveTextContent('SP-1120N · USB');
    });
});
