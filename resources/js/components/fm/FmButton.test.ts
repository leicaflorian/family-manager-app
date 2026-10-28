import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { Pencil } from '@lucide/vue';
import { defineComponent, h } from 'vue';
import { provideOnColor } from '@/composables/useOnColor';
import FmButton from './FmButton.vue';

/**
 * A button inside a coloured background, as in the top of a card page.
 */
function onColor(props: Record<string, unknown>, text?: string) {
    return render(
        defineComponent({
            setup() {
                provideOnColor();

                return () => h(FmButton, props, () => text);
            },
        }),
    );
}

describe('FmButton', () => {
    it('renders a button with the text', () => {
        render(FmButton, { slots: { default: 'Fine, save' } });

        expect(
            screen.getByRole('button', { name: 'Fine, save' }),
        ).toHaveAttribute('type', 'button');
    });

    it('with label shows only the icon, and says the label on hover and to screen readers', () => {
        render(FmButton, {
            props: { icon: Pencil, label: 'Edit', href: '/documents/5/edit' },
        });

        const link = screen.getByRole('link', { name: 'Edit' });
        expect(link).toHaveAttribute('title', 'Edit');
        expect(link).not.toHaveTextContent('Edit');
    });

    it('with label is a light grey square, as tall as the other buttons', () => {
        render(FmButton, {
            props: { variant: 'soft', icon: Pencil, label: 'Edit' },
        });

        const button = screen.getByRole('button', { name: 'Edit' });
        expect(button).toHaveClass('size-11.5', 'bg-canvas', 'text-ink');
        expect(button).not.toHaveClass('bg-action-soft');
    });

    it('small and with label is a square of 40 px, as beside the title of a panel', () => {
        render(FmButton, {
            props: { icon: Pencil, label: 'Edit', small: true },
        });

        const button = screen.getByRole('button', { name: 'Edit' });
        expect(button).toHaveClass('size-10');
        expect(button).not.toHaveClass('size-11.5');
    });

    it('xsmall and with label is a square of 30 px with a smaller icon, as beside the tabs of a card', () => {
        render(FmButton, {
            props: { icon: Pencil, label: 'Edit', xsmall: true },
        });

        const button = screen.getByRole('button', { name: 'Edit' });
        expect(button).toHaveClass('size-7.5');
        expect(button).not.toHaveClass('size-11.5');
        expect(button.querySelector('svg')).toHaveAttribute('width', '16');
    });

    it('with label and danger keeps the red icon', () => {
        render(FmButton, {
            props: { variant: 'danger', icon: Pencil, label: 'Delete' },
        });

        expect(screen.getByRole('button', { name: 'Delete' })).toHaveClass(
            'bg-canvas',
            'text-overdue',
        );
    });

    it('does not send the click when disabled', async () => {
        const result = render(FmButton, {
            props: { disabled: true },
            slots: { default: 'Save' },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(screen.getByRole('button')).toBeDisabled();
        expect(result.emitted('click')).toBeUndefined();
    });

    it('is red in the danger variant', () => {
        render(FmButton, {
            props: { variant: 'danger' },
            slots: { default: 'Delete' },
        });

        expect(screen.getByRole('button', { name: 'Delete' })).toHaveClass(
            'text-overdue',
        );
    });

    it.each([
        ['solid', 'hover:bg-action-dark'],
        ['outline', 'hover:bg-action-soft'],
        ['soft', 'hover:bg-action/15'],
        ['text', 'hover:bg-action-soft'],
        ['danger', 'hover:bg-overdue/10'],
    ] as const)('changes on hover in the %s variant', (variant, hover) => {
        render(FmButton, { props: { variant }, slots: { default: 'Go' } });

        expect(screen.getByRole('button', { name: 'Go' })).toHaveClass(hover);
    });

    it('is blue in the solid variant', () => {
        render(FmButton, { slots: { default: 'Save' } });

        expect(screen.getByRole('button')).toHaveClass('bg-action');
    });

    it('is a link with href', () => {
        render(FmButton, {
            props: { href: '/upload' },
            slots: { default: 'Upload' },
        });

        expect(screen.getByRole('link', { name: 'Upload' })).toHaveAttribute(
            'href',
            '/upload',
        );
    });

    it('with download is a plain link that downloads the file', () => {
        render(FmButton, {
            props: { href: '/files/1/download', download: true },
            slots: { default: 'Download' },
        });

        expect(screen.getByRole('link', { name: 'Download' })).toHaveAttribute(
            'download',
        );
    });

    it('external is a plain link that opens the address in a new tab', () => {
        render(FmButton, {
            props: { href: '/files/1', external: true },
            slots: { default: 'Open in a new tab' },
        });

        const link = screen.getByRole('link', { name: 'Open in a new tab' });
        expect(link).toHaveAttribute('href', '/files/1');
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noopener');
    });

    it('inline is a text as tall as the title beside it, without the 46 px box', () => {
        render(FmButton, {
            props: { variant: 'text', icon: Pencil, inline: true },
            slots: { default: 'Upload' },
        });

        const button = screen.getByRole('button', { name: 'Upload' });
        expect(button).toHaveClass('h-auto', 'px-0', 'font-semibold');
        expect(button).not.toHaveClass('h-11.5', 'px-3');
    });

    it('on a colour is translucent white, a bit stronger on hover', () => {
        onColor({ variant: 'soft', icon: Pencil }, 'Edit');

        const button = screen.getByRole('button', { name: 'Edit' });
        expect(button).toHaveClass(
            'bg-white/15',
            'text-white',
            'hover:bg-white/25',
        );
        expect(button).not.toHaveClass('bg-action-soft', 'text-action-dark');
    });

    it('on a colour the icon square is translucent white too', () => {
        onColor({ variant: 'soft', icon: Pencil, label: 'More actions' });

        const button = screen.getByRole('button', { name: 'More actions' });
        expect(button).toHaveClass('size-11.5', 'bg-white/15', 'text-white');
        expect(button).not.toHaveClass('bg-canvas', 'text-ink');
    });
});
