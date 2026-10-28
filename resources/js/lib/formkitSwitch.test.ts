import { FormKit, plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import formkitConfig from '@/formkit.config';

/**
 * Mounts one field with the configuration of the app.
 *
 * @param type - the type of the field
 * @param label - its label
 */
function field(type: string, label: string): void {
    render(
        { render: () => h(FormKit, { type, label }) },
        { global: { plugins: [[formkit, formkitConfig]] } },
    );
}

describe('toggleAsSwitch', () => {
    it('makes the toggle a switch for screen readers', async () => {
        field('toggle', 'Stay signed in');

        expect(
            await screen.findByRole('switch', { name: 'Stay signed in' }),
        ).toBeInTheDocument();
    });

    it('leaves the checkbox a box to tick', async () => {
        field('checkbox', 'I accept the terms');

        expect(
            await screen.findByRole('checkbox', { name: 'I accept the terms' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('switch')).toBeNull();
    });
});
