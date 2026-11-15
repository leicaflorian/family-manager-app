import userEvent from '@testing-library/user-event';
import { render, screen, waitFor } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { toast } from 'vue-sonner';
import FmToaster from './FmToaster.vue';

/** The toast that holds a message, as the person sees it. */
function toastOf(message: string): HTMLElement {
    const box = screen.getByText(message).closest('[data-sonner-toast]');

    if (!(box instanceof HTMLElement)) {
        throw new Error(`No toast with «${message}»`);
    }

    return box;
}

describe('FmToaster', () => {
    afterEach(() => {
        toast.dismiss();
    });

    it('shows a message in the colors of the other theme, so it stands out on the page', async () => {
        render(FmToaster);

        toast.success('Thanks, we will answer by email.');

        await screen.findByText('Thanks, we will answer by email.');
        const box = toastOf('Thanks, we will answer by email.');
        expect(box).toHaveClass(
            'theme-inverse',
            'bg-segment',
            'dark:bg-card',
            'text-ink',
            'shadow-menu',
        );
        expect(box).not.toHaveClass('dark');
    });

    it('colors the icon with the kind of message', async () => {
        render(FmToaster);

        toast.success('Saved');
        toast.error('Not saved');
        toast.warning('Reading is off');
        toast.info('Link expired');

        await screen.findByText('Link expired');
        const icon = (message: string): Element | null =>
            toastOf(message).querySelector('[data-icon] svg');
        expect(icon('Saved')).toHaveClass('text-paid');
        expect(icon('Not saved')).toHaveClass('text-overdue');
        expect(icon('Reading is off')).toHaveClass('text-warning');
        expect(icon('Link expired')).toHaveClass('text-action');
    });

    it('says the kind of message with its icon', async () => {
        render(FmToaster);

        toast.error('This could not be marked done, try again.');

        await screen.findByText('This could not be marked done, try again.');
        const box = toastOf('This could not be marked done, try again.');
        expect(box).toHaveAttribute('data-type', 'error');
        expect(box.querySelector('[data-icon] svg')).not.toBeNull();
    });

    it('runs the colored action of a message and closes it', async () => {
        const undo = vi.fn();
        render(FmToaster);

        toast.success('Done: Car service', {
            action: { label: 'Undo', onClick: undo },
        });
        const button = await screen.findByRole('button', { name: 'Undo' });
        expect(button).toHaveClass('bg-action', 'text-on-action');
        await userEvent.click(button);

        expect(undo).toHaveBeenCalledOnce();
        await waitFor(() => {
            expect(
                screen.queryByText('Done: Car service'),
            ).not.toBeInTheDocument();
        });
    });

    it('stays above the bottom bar of the phone and its + button', async () => {
        const { container } = render(FmToaster, {
            props: { aboveBottomBar: true },
        });

        toast('Saved');

        await screen.findByText('Saved');
        const list = container.ownerDocument.querySelector<HTMLElement>(
            '[data-sonner-toaster]',
        );
        expect(list?.style.getPropertyValue('--mobile-offset-bottom')).toBe(
            '128px',
        );
        expect(list?.style.getPropertyValue('--offset-bottom')).toBe('136px');
    });

    it('keeps the usual distance from the bottom without the bar', async () => {
        const { container } = render(FmToaster);

        toast('Saved');

        await screen.findByText('Saved');
        const list = container.ownerDocument.querySelector<HTMLElement>(
            '[data-sonner-toaster]',
        );
        expect(list?.style.getPropertyValue('--mobile-offset-bottom')).toBe(
            '16px',
        );
        expect(list?.style.getPropertyValue('--offset-bottom')).toBe('24px');
    });
});
