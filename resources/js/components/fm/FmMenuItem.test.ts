import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { renderPage } from '@/test/page';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

/**
 * Opens a menu holding one entry with the given props.
 *
 * @param props - the props of the entry
 * @returns the entry
 */
async function openWith(props: Record<string, unknown>): Promise<HTMLElement> {
    renderPage(
        defineComponent({
            setup: () => () =>
                h(FmMenu, null, {
                    default: () => h(FmMenuItem, props, () => 'Entry'),
                }),
        }),
    );
    await userEvent.click(screen.getByRole('button', { name: 'More actions' }));

    return screen.findByRole('menuitem', { name: 'Entry' });
}

describe('FmMenuItem', () => {
    it('without an address it is an action', async () => {
        const select = vi.fn();
        const entry = await openWith({ onSelect: select });

        await userEvent.click(entry);

        expect(select).toHaveBeenCalled();
    });

    it('with an address it goes to the page', async () => {
        const entry = await openWith({ href: '/documents/5/edit' });

        expect(entry).toHaveAttribute('href', '/documents/5/edit');
        expect(entry).not.toHaveAttribute('target');
    });

    it('external, it opens the address in a new tab', async () => {
        const entry = await openWith({ href: '/files/12', external: true });

        expect(entry).toHaveAttribute('href', '/files/12');
        expect(entry).toHaveAttribute('target', '_blank');
        expect(entry).toHaveAttribute('rel', 'noopener');
    });
});
