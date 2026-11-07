import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { provideOnColor } from '@/composables/useOnColor';
import { FormKit } from '@formkit/vue';
import { formPlugins } from '@/lib/formPlugins';
import { search } from '@/test/page';
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
    it('marks its content as a panel, for the size of the controls', async () => {
        await openSheet();

        expect(
            screen.getByRole('dialog', { name: 'Filter the documents' }),
        ).toHaveAttribute('data-sheet');
    });

    it('keeps the open menu of a field inside the panel', async () => {
        render(FmBottomSheet, {
            props: { open: true, title: 'Filter the documents' },
            slots: {
                default: defineComponent({
                    setup: () => () =>
                        h(FormKit, {
                            type: 'autocomplete',
                            label: 'Category',
                            options: [{ value: 1, label: 'Home' }],
                        }),
                }),
            },
            global: { plugins: formPlugins },
        });
        await screen.findByRole('dialog');

        const list = await search('Category', 'Ho');

        expect(list.closest('[data-sheet]')).not.toBeNull();
    });

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

    it('leaves Esc to an open menu inside, and stays open', async () => {
        // An open FormKit list marks its field with data-expanded
        const result = render(FmBottomSheet, {
            props: { open: true, title: 'New reminder' },
            slots: {
                default:
                    '<div data-expanded="true"><button type="button">Notices</button></div>',
            },
        });
        await screen.findByRole('dialog');
        screen.getByRole('button', { name: 'Notices' }).focus();

        await userEvent.keyboard('{Escape}');

        expect(result.emitted('update:open')).toBeUndefined();
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

    it('asks before closing, and stays open when the answer is no', async () => {
        const result = render(FmBottomSheet, {
            props: {
                open: true,
                title: 'Pay this deadline',
                beforeClose: () => Promise.resolve(false),
            },
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));
        await Promise.resolve();

        expect(result.emitted('update:open')).toBeUndefined();
    });

    it('closes when the answer is yes', async () => {
        const result = render(FmBottomSheet, {
            props: {
                open: true,
                title: 'Pay this deadline',
                beforeClose: () => Promise.resolve(true),
            },
        });
        await screen.findByRole('dialog');

        await userEvent.keyboard('{Escape}');

        await vi.waitFor(() =>
            expect(result.emitted('update:open')).toEqual([[false]]),
        );
    });

    it('can sit above the panels already open, whatever their order in the page', async () => {
        render(FmBottomSheet, {
            props: { open: true, title: 'Pay this deadline' },
        });
        render(FmBottomSheet, {
            props: { open: true, title: 'Leave without saving?', above: true },
        });

        // Two panels open together hide each other from screen readers in
        // jsdom, title included: they are found by their text
        const dialogs = await screen.findAllByRole('dialog', { hidden: true });
        const panel = dialogs.find((dialog) =>
            dialog.textContent?.includes('Pay this deadline'),
        )!;
        const question = dialogs.find((dialog) =>
            dialog.textContent?.includes('Leave without saving?'),
        )!;

        // Same layer: the one later in the page wins. Above: it wins anyway
        expect(layer(question)).toBeGreaterThan(layer(panel));
    });
});

/**
 * The layer of a panel, from the z-index class Tailwind gives it.
 *
 * @param element - the panel
 * @returns the number of its z-index class
 */
function layer(element: HTMLElement): number {
    return Number(/(?:^|\s)z-\[?(\d+)/.exec(element.className)?.[1] ?? 0);
}
