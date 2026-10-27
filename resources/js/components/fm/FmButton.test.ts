import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmButton from './FmButton.vue';

describe('FmButton', () => {
    it('renders a button with the text', () => {
        render(FmButton, { slots: { default: 'Fine, save' } });

        expect(
            screen.getByRole('button', { name: 'Fine, save' }),
        ).toHaveAttribute('type', 'button');
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
});
