import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import type { LucideIcon } from '@lucide/vue';
import { Plus } from '@lucide/vue';
import type { PropType } from 'vue';
import { defineComponent, h } from 'vue';
import { renderPage } from '@/test/page';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

/** A menu with one entry, as a page uses it. */
const Menu = defineComponent({
    props: {
        label: { type: String, default: undefined },
        small: { type: Boolean, default: false },
        icon: { type: Function as PropType<LucideIcon>, default: undefined },
    },
    setup: (props) => () =>
        h(
            FmMenu,
            { label: props.label, small: props.small, icon: props.icon },
            { default: () => h(FmMenuItem, null, () => 'Rename') },
        ),
});

describe('FmMenu', () => {
    it('is a ⋯ button that opens the entries', async () => {
        renderPage(Menu);

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );

        expect(
            await screen.findByRole('menuitem', { name: 'Rename' }),
        ).toBeInTheDocument();
    });

    it('takes its own label', () => {
        renderPage(Menu, { label: 'More on Laura' });

        expect(
            screen.getByRole('button', { name: 'More on Laura' }),
        ).toBeInTheDocument();
    });

    it('small, the ⋯ button is a square of 40 px', () => {
        renderPage(Menu, { small: true });

        expect(
            screen.getByRole('button', { name: 'More actions' }),
        ).toHaveClass('size-10');
    });

    it('takes its own icon in place of the ⋯', () => {
        renderPage(Menu, { label: 'Add', icon: Plus });

        const button = screen.getByRole('button', { name: 'Add' });
        expect(button.querySelector('.lucide-plus')).not.toBeNull();
        expect(button.querySelector('.lucide-ellipsis')).toBeNull();
    });
});
