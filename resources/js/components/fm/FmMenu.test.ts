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
        plain: { type: Boolean, default: false },
        text: { type: Boolean, default: false },
        outline: { type: Boolean, default: false },
        control: { type: Boolean, default: false },
        choice: { type: Boolean, default: false },
        row: { type: Boolean, default: false },
        icon: { type: Function as PropType<LucideIcon>, default: undefined },
    },
    setup: (props) => () =>
        h(
            FmMenu,
            {
                label: props.label,
                small: props.small,
                plain: props.plain,
                text: props.text,
                outline: props.outline,
                control: props.control,
                choice: props.choice,
                row: props.row,
                icon: props.icon,
            },
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

    it('plain, the ⋯ button has no background until hovered, and its icon changes colour as a plain button beside it', () => {
        renderPage(Menu, { plain: true });

        const button = screen.getByRole('button', { name: 'More actions' });
        expect(button).toHaveClass('bg-transparent', 'hover:text-action-dark');
        expect(button).not.toHaveClass('bg-canvas');
    });

    it('row, the ⋮ button is a 32 px square that announces a menu', () => {
        renderPage(Menu, { row: true });

        const button = screen.getByRole('button', { name: 'More actions' });
        expect(button).toHaveClass('size-8');
        expect(button).toHaveAttribute('aria-haspopup', 'menu');
        expect(
            button.querySelector('.lucide-ellipsis-vertical'),
        ).not.toBeNull();
    });

    it('takes its own icon in place of the ⋯', () => {
        renderPage(Menu, { label: 'Add', icon: Plus });

        const button = screen.getByRole('button', { name: 'Add' });
        expect(button.querySelector('.lucide-plus')).not.toBeNull();
        expect(button.querySelector('.lucide-ellipsis')).toBeNull();
    });

    it('with text, shows its label beside the icon, as a text button', async () => {
        renderPage(Menu, { label: 'Record payment', icon: Plus, text: true });

        const button = screen.getByRole('button', { name: 'Record payment' });
        expect(button).toHaveTextContent('Record payment');
        expect(button).toHaveClass('text-body', 'hover:underline');
        await userEvent.click(button);
        expect(
            await screen.findByRole('menuitem', { name: 'Rename' }),
        ).toBeInTheDocument();
    });

    it('as a control, the ⋯ is a square as tall as the buttons beside it, in a panel too', async () => {
        renderPage(Menu, { control: true });

        const button = screen.getByRole('button', { name: 'More actions' });
        expect(button).toHaveClass(
            'size-control',
            'rounded-control',
            'border-edge-soft',
            'bg-segment',
        );
        expect(button.querySelector('.lucide-ellipsis')).not.toBeNull();
        await userEvent.click(button);
        expect(
            await screen.findByRole('menuitem', { name: 'Rename' }),
        ).toBeInTheDocument();
    });

    it('opens its entries above a panel from below, even one over another', async () => {
        renderPage(Menu);

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );

        // FmBottomSheet is z-50, and z-[70] over another panel
        expect(await screen.findByRole('menu')).toHaveClass('z-[80]');
    });

    it('outlined, shows its label beside the icon in a white button with a grey border', async () => {
        renderPage(Menu, { label: 'Postpone', icon: Plus, outline: true });

        const button = screen.getByRole('button', { name: 'Postpone' });
        expect(button).toHaveTextContent('Postpone');
        expect(button).toHaveClass('h-9', 'border-edge-soft', 'bg-card');
        expect(button.querySelector('.lucide-plus')).not.toBeNull();
        await userEvent.click(button);
        expect(
            await screen.findByRole('menuitem', { name: 'Rename' }),
        ).toBeInTheDocument();
    });

    it('as a choice, shows its label in ink and bold with a chevron after it', async () => {
        renderPage(Menu, { label: 'Gas - Eni · Home', choice: true });

        const button = screen.getByRole('button', { name: 'Gas - Eni · Home' });
        expect(button).toHaveTextContent('Gas - Eni · Home');
        expect(button).toHaveClass('text-label', 'text-ink', 'min-h-touch');
        const chevron = button.querySelector('svg');
        expect(chevron).toHaveClass('lucide-chevron-down', 'text-ink-soft');
        // The chevron comes after the words
        expect(button.lastElementChild).toBe(chevron);
        await userEvent.click(button);
        expect(
            await screen.findByRole('menuitem', { name: 'Rename' }),
        ).toBeInTheDocument();
    });
});
