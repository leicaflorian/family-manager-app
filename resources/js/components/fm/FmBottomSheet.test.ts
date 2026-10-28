import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { provideOnColor } from '@/composables/useOnColor';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

/**
 * The panel with a field and a button, to try the focus.
 */
async function openSheet(open = true) {
    const result = render(FmBottomSheet, {
        props: { open, title: 'Filter the documents' },
        slots: {
            default:
                '<input aria-label="Whose" /><button type="button">Show 4 documents</button>',
        },
    });

    // The panel goes in the body with a portal, which is mounted right after.
    if (open) {
        await screen.findByRole('dialog');
    }

    return result;
}

describe('FmBottomSheet', () => {
    it('is not visible when closed', async () => {
        await openSheet(false);

        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('opens with open and shows the title', async () => {
        await openSheet();

        expect(
            screen.getByRole('dialog', { name: 'Filter the documents' }),
        ).toBeInTheDocument();
    });

    it('closes with Esc', async () => {
        const result = await openSheet();

        await userEvent.keyboard('{Escape}');

        expect(result.emitted('update:open')).toEqual([[false]]);
    });

    it('closes with the handle', async () => {
        const result = await openSheet();

        await userEvent.click(
            screen.getByRole('button', { name: 'Close the panel' }),
        );

        expect(result.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('closes with the ×', async () => {
        const result = await openSheet();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(result.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('keeps the focus inside while it is open', async () => {
        await openSheet();
        const sheet = screen.getByRole('dialog');

        for (let i = 0; i < 5; i++) {
            await userEvent.tab();
            expect(sheet).toContainElement(
                document.activeElement as HTMLElement,
            );
        }
    });

    it('keeps its buttons as on white, also when opened from a coloured top', async () => {
        render(
            defineComponent({
                setup() {
                    provideOnColor();

                    return () =>
                        h(
                            FmBottomSheet,
                            { open: true, title: 'Delete the card?' },
                            () =>
                                h(
                                    FmButton,
                                    { variant: 'text' },
                                    () => 'Cancel',
                                ),
                        );
                },
            }),
        );

        const cancel = await screen.findByRole('button', { name: 'Cancel' });
        expect(cancel).toHaveClass('text-action');
        expect(cancel).not.toHaveClass('bg-white/15', 'text-white');
    });
});
