import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import FmFeedbackSheet from './FmFeedbackSheet.vue';

afterEach(() => {
    vi.restoreAllMocks();
    window.history.replaceState({}, '', '/');
});

/**
 * Opens the panel as the account menu does.
 *
 * @returns the rendered panel
 */
async function openSheet(): Promise<ReturnType<typeof renderPage>> {
    const rendered = renderPage(FmFeedbackSheet, { open: true });
    await screen.findByRole('dialog', { name: 'Report a problem or an idea' });

    return rendered;
}

describe('FmFeedbackSheet', () => {
    it('asks what it is about, starting from something that does not work', async () => {
        await openSheet();

        expect(
            screen.getByRole('radio', { name: 'Something does not work' }),
        ).toBeChecked();
        expect(
            screen.getByRole('radio', { name: 'I have an idea' }),
        ).not.toBeChecked();
        expect(
            screen.getByRole('group', { name: 'What is it about' }),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Tell us what happened'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: 'Add a screenshot or a file (optional)',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Along with the message we send the page you were on, the device you use and the time. We answer you by email.',
            ),
        ).toBeInTheDocument();
    });

    it('does not send without the message', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        await openSheet();

        await userEvent.click(screen.getByRole('button', { name: 'Send' }));
        await settle();

        expect(post).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('sends the idea, the message, the page, the layout and the file', async () => {
        window.history.replaceState({}, '', '/documents/12?tab=files');
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        await openSheet();

        await userEvent.click(
            screen.getByRole('radio', { name: 'I have an idea' }),
        );
        await userEvent.type(
            screen.getByLabelText('Tell us what happened'),
            'Un tema verde',
        );
        const file = new File(['png'], 'schermata.png', { type: 'image/png' });
        await userEvent.upload(
            screen.getByLabelText('Screenshot or file'),
            file,
        );
        expect(screen.getByText('schermata.png')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Send' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/feedback',
            {
                kind: 'idea',
                message: 'Un tema verde',
                page: '/documents/12?tab=files',
                layout: expect.stringMatching(/^(phone|tablet|computer)$/),
                file,
            },
            expect.objectContaining({
                forceFormData: true,
                preserveScroll: true,
            }),
        );
    });

    it('removes the chosen file', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        await openSheet();

        await userEvent.upload(
            screen.getByLabelText('Screenshot or file'),
            new File(['png'], 'schermata.png', { type: 'image/png' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Remove schermata.png' }),
        );
        await userEvent.type(
            screen.getByLabelText('Tell us what happened'),
            'Ciao',
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Send' }));
        await settle();

        expect(screen.queryByText('schermata.png')).not.toBeInTheDocument();
        expect(post.mock.calls[0]?.[1]).not.toHaveProperty('file');
    });

    it('shows the message of the server and stays open', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                file: 'The file must be an image or a PDF.',
            });
            (options as VisitOptions).onFinish?.(
                {} as Parameters<NonNullable<VisitOptions['onFinish']>>[0],
            );
        });
        await openSheet();

        await userEvent.type(
            screen.getByLabelText('Tell us what happened'),
            'Ciao',
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Send' }));
        await settle();

        expect(
            screen.getByText('The file must be an image or a PDF.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('closes once sent, and opens again empty', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onSuccess?.(
                {} as Parameters<NonNullable<VisitOptions['onSuccess']>>[0],
            );
        });
        const view = await openSheet();

        await userEvent.click(
            screen.getByRole('radio', { name: 'I have an idea' }),
        );
        await userEvent.type(
            screen.getByLabelText('Tell us what happened'),
            'Ciao',
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Send' }));
        await settle();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        await view.rerender({ open: false });
        await view.rerender({ open: true });
        await screen.findByRole('dialog');
        expect(screen.getByLabelText('Tell us what happened')).toHaveValue('');
        expect(
            screen.getByRole('radio', { name: 'Something does not work' }),
        ).toBeChecked();
    });

    it('closes with Cancel without sending', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        await openSheet();

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        await settle();

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });
});
