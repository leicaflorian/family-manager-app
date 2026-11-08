import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import FmSidePanel from './FmSidePanel.vue';

describe('FmSidePanel', () => {
    it('shows its content in a panel named by its label, leaving the page usable', () => {
        render(FmSidePanel, {
            props: { label: 'Bolletta luce' },
            slots: { default: () => h('p', 'The preview') },
        });

        const panel = screen.getByRole('dialog', { name: 'Bolletta luce' });

        expect(panel).toHaveTextContent('The preview');
        expect(panel).toHaveAttribute('aria-modal', 'false');
    });

    it('closes with Esc', async () => {
        const result = render(FmSidePanel, {
            props: { label: 'Bolletta luce' },
        });

        await userEvent.keyboard('{Escape}');

        expect(result.emitted('close')).toHaveLength(1);
    });

    it('leaves Esc to an open menu inside it', async () => {
        const result = render(FmSidePanel, {
            props: { label: 'Bolletta luce' },
            slots: {
                default: () =>
                    h('div', { role: 'menu' }, [
                        h('button', { role: 'menuitem' }, 'Edit'),
                    ]),
            },
        });

        screen.getByRole('menuitem', { name: 'Edit' }).focus();
        await userEvent.keyboard('{Escape}');

        expect(result.emitted('close')).toBeUndefined();
    });
});
