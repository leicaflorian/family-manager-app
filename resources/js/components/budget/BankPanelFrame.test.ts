import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { defineComponent, h, ref } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, screenWidth, settle } from '@/test/page';
import BankPanelFrame from './BankPanelFrame.vue';

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

/**
 * A page with a button that opens the panel, as *From the bank* does with a group card; with `vanishing` the button
 * goes away while the panel is open (the cards of the groups become a list beside the panel), and the fallback
 * button gets the focus back.
 */
const Harness = defineComponent({
    props: {
        vanishing: { type: Boolean, default: false },
        look: { type: String, default: undefined },
    },
    setup(props) {
        const open = ref(false);
        const fallback = ref<HTMLElement | null>(null);

        return () =>
            h('div', [
                open.value && props.vanishing
                    ? null
                    : h(
                          'button',
                          {
                              type: 'button',
                              onClick: () => (open.value = true),
                          },
                          'Open the group',
                      ),
                h('button', { type: 'button', ref: fallback }, 'The page'),
                h(
                    BankPanelFrame,
                    {
                        title: 'Satispay Europe',
                        look: props.look as 'card' | 'sheet' | undefined,
                        open: open.value,
                        'onUpdate:open': (value: boolean) =>
                            (open.value = value),
                        returnFocus: () => fallback.value,
                    },
                    () => h('p', 'The movements of the group'),
                ),
            ]);
    },
});

describe('BankPanelFrame', () => {
    it('is a bottom sheet on the phone', async () => {
        screenWidth(false);
        renderPage(Harness);

        await userEvent.click(
            screen.getByRole('button', { name: 'Open the group' }),
        );

        expect(
            await screen.findByRole('dialog', { name: 'Satispay Europe' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('complementary')).not.toBeInTheDocument();
    });

    it('is a region beside the list when wide, named by its title, not a dialog', async () => {
        screenWidth(true);
        renderPage(Harness);

        await userEvent.click(
            screen.getByRole('button', { name: 'Open the group' }),
        );
        await settle();

        const panel = screen.getByRole('complementary', {
            name: 'Satispay Europe',
        });
        const title = screen.getByRole('heading', { name: 'Satispay Europe' });

        expect(panel).toHaveAttribute('aria-labelledby', title.id);
        expect(panel).toHaveTextContent('The movements of the group');
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('moves the focus to the title when it opens and back to the button that opened it when it closes', async () => {
        screenWidth(true);
        renderPage(Harness);
        const opener = screen.getByRole('button', { name: 'Open the group' });

        await userEvent.click(opener);
        await settle();
        expect(
            screen.getByRole('heading', { name: 'Satispay Europe' }),
        ).toHaveFocus();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));
        await settle();

        expect(screen.queryByRole('complementary')).not.toBeInTheDocument();
        expect(opener).toHaveFocus();
    });

    it('closes with Escape', async () => {
        screenWidth(true);
        renderPage(Harness);
        const opener = screen.getByRole('button', { name: 'Open the group' });

        await userEvent.click(opener);
        await settle();
        expect(screen.getByRole('complementary')).toBeInTheDocument();
        await userEvent.keyboard('{Escape}');
        await settle();

        expect(screen.queryByRole('complementary')).not.toBeInTheDocument();
        expect(opener).toHaveFocus();
    });

    it('gives the focus to the page when the button that opened it is gone', async () => {
        screenWidth(true);
        renderPage(Harness, { vanishing: true });

        await userEvent.click(
            screen.getByRole('button', { name: 'Open the group' }),
        );
        await settle();
        await userEvent.keyboard('{Escape}');
        await settle();

        expect(screen.getByRole('button', { name: 'The page' })).toHaveFocus();
    });

    it('draws the title of a group bigger than the one of a sheet, as the boards', async () => {
        screenWidth(true);
        const view = renderPage(Harness, { look: 'card' });

        await userEvent.click(
            screen.getByRole('button', { name: 'Open the group' }),
        );
        await settle();
        expect(
            screen.getByRole('heading', { name: 'Satispay Europe' }),
        ).toHaveClass('text-stat');
        view.unmount();

        renderPage(Harness, { look: 'sheet' });
        await userEvent.click(
            screen.getByRole('button', { name: 'Open the group' }),
        );
        await settle();
        expect(
            screen.getByRole('heading', { name: 'Satispay Europe' }),
        ).toHaveClass('text-sheet-title');
    });
});
