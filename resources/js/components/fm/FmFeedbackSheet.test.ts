import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { reportVueError } from '@/lib/diagnostics';
import { renderPage, settle } from '@/test/page';
import FmFeedbackSheet from './FmFeedbackSheet.vue';

beforeEach(() => {
    // jsdom has no object URLs
    URL.createObjectURL = vi.fn(() => 'blob:photo');
    URL.revokeObjectURL = vi.fn();
});

afterEach(() => {
    vi.restoreAllMocks();
    window.history.replaceState({}, '', '/');
});

/**
 * Opens the panel as the account menu does.
 *
 * @returns the rendered panel
 */
async function openSheet(
    screenshot: File | null = null,
): Promise<ReturnType<typeof renderPage>> {
    const rendered = renderPage(FmFeedbackSheet, { open: true, screenshot });
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
                'Along with the message we send a photo of this page and some technical details, which you can read above. We answer you by email.',
            ),
        ).toBeInTheDocument();
    });

    it('changes the note with the kind', async () => {
        await openSheet();

        await userEvent.click(
            screen.getByRole('radio', { name: 'I have an idea' }),
        );

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

    describe('the photo of the page and what we send', () => {
        const photo = new File(['png'], 'page.png', { type: 'image/png' });

        /** Writes a message, so that it can be sent. */
        async function write(): Promise<void> {
            await userEvent.type(
                screen.getByLabelText('Tell us what happened'),
                'Ciao',
            );
            await settle();
        }

        it('a problem shows the photo of this page with Remove and sends it with the details', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            await openSheet(photo);

            expect(
                screen.getByRole('img', { name: 'Photo of this page' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', {
                    name: 'Remove the photo of this page',
                }),
            ).toBeInTheDocument();
            await write();
            await userEvent.click(screen.getByRole('button', { name: 'Send' }));
            await settle();

            const data = post.mock.calls[0]?.[1] as Record<string, unknown>;
            expect(data.screenshot).toBe(photo);
            expect(typeof data.diagnostics).toBe('string');
            expect(JSON.parse(data.diagnostics as string)).toHaveProperty(
                'console',
            );
        });

        it('Remove takes the photo away and it is not sent; the picker still works', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            await openSheet(photo);

            await userEvent.click(
                screen.getByRole('button', {
                    name: 'Remove the photo of this page',
                }),
            );
            expect(
                screen.queryByRole('img', { name: 'Photo of this page' }),
            ).not.toBeInTheDocument();
            await write();
            await userEvent.click(screen.getByRole('button', { name: 'Send' }));
            await settle();
            expect(post.mock.calls[0]?.[1]).not.toHaveProperty('screenshot');
            expect(post.mock.calls[0]?.[1]).toHaveProperty('diagnostics');
        });

        it('sends both the own file and the photo', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            await openSheet(photo);
            const own = new File(['png'], 'mine.png', { type: 'image/png' });

            await userEvent.upload(
                screen.getByLabelText('Screenshot or file'),
                own,
            );
            await write();
            await userEvent.click(screen.getByRole('button', { name: 'Send' }));
            await settle();

            const data = post.mock.calls[0]?.[1] as Record<string, unknown>;
            expect(data.file).toBe(own);
            expect(data.screenshot).toBe(photo);
        });

        it('What we send opens and lists the page, the last pages, the errors and the device', async () => {
            window.history.replaceState({}, '', '/documents/42');
            reportVueError(new TypeError('amount is undefined'), null, 'info');
            await openSheet(photo);

            const toggle = screen.getByRole('button', {
                name: 'What we send (technical details)',
            });
            expect(toggle).toHaveAttribute('aria-expanded', 'false');
            expect(screen.queryByText('Last pages')).not.toBeInTheDocument();

            await userEvent.click(toggle);

            expect(toggle).toHaveAttribute('aria-expanded', 'true');
            expect(screen.getByText('Page')).toBeInTheDocument();
            expect(screen.getAllByText('/documents/42')).toHaveLength(2);
            expect(screen.getByText('Last pages')).toBeInTheDocument();
            expect(screen.getByText(/Page errors \(\d+\)/)).toBeInTheDocument();
            expect(
                screen.getByText(/TypeError: amount is undefined/),
            ).toBeInTheDocument();
            expect(
                screen.getByText(/Failed requests \(\d+\)/),
            ).toBeInTheDocument();
            expect(screen.getByText('Device')).toBeInTheDocument();
            expect(screen.getByText('Version')).toBeInTheDocument();
        });

        it('I have an idea hides the photo and what we send and sends neither; back to a problem they are there again', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            await openSheet(photo);

            await userEvent.click(
                screen.getByRole('radio', { name: 'I have an idea' }),
            );
            expect(
                screen.queryByRole('img', { name: 'Photo of this page' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', {
                    name: 'What we send (technical details)',
                }),
            ).not.toBeInTheDocument();
            await write();
            await userEvent.click(screen.getByRole('button', { name: 'Send' }));
            await settle();
            expect(post.mock.calls[0]?.[1]).not.toHaveProperty('screenshot');
            expect(post.mock.calls[0]?.[1]).not.toHaveProperty('diagnostics');

            await userEvent.click(
                screen.getByRole('radio', { name: 'Something does not work' }),
            );
            expect(
                screen.getByRole('img', { name: 'Photo of this page' }),
            ).toBeInTheDocument();
        });

        it('a removed photo does not come back by switching kind', async () => {
            await openSheet(photo);

            await userEvent.click(
                screen.getByRole('button', {
                    name: 'Remove the photo of this page',
                }),
            );
            await userEvent.click(
                screen.getByRole('radio', { name: 'I have an idea' }),
            );
            await userEvent.click(
                screen.getByRole('radio', { name: 'Something does not work' }),
            );

            expect(
                screen.queryByRole('img', { name: 'Photo of this page' }),
            ).not.toBeInTheDocument();
        });

        it('without a screenshot the panel has no thumbnail and sends without it', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            await openSheet(null);

            expect(
                screen.queryByRole('img', { name: 'Photo of this page' }),
            ).not.toBeInTheDocument();
            await write();
            await userEvent.click(screen.getByRole('button', { name: 'Send' }));
            await settle();

            expect(post.mock.calls[0]?.[1]).not.toHaveProperty('screenshot');
            expect(post.mock.calls[0]?.[1]).toHaveProperty('diagnostics');
        });

        it('a screenshot arriving while the panel is open appears without resetting the message', async () => {
            const view = await openSheet(null);
            await write();

            await view.rerender({ screenshot: photo });

            expect(
                screen.getByRole('img', { name: 'Photo of this page' }),
            ).toBeInTheDocument();
            expect(screen.getByLabelText('Tell us what happened')).toHaveValue(
                'Ciao',
            );
        });
    });
});
