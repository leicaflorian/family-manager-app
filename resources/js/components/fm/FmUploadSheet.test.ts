import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmUploadSheet from './FmUploadSheet.vue';

const file = new File(['%PDF-1.4'], 'Libretto.pdf', {
    type: 'application/pdf',
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmUploadSheet', () => {
    it('asks for the files of the documents', async () => {
        renderPage(FmUploadSheet, {
            open: true,
            link: { type: 'vehicle', id: 1 },
        });

        expect(
            await screen.findByRole('dialog', { name: 'Upload a document' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Choose the files')).toBeInTheDocument();
    });

    it('sends the files chosen, linked to the card', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(FmUploadSheet, {
            open: true,
            link: { type: 'vehicle', id: 1 },
        });

        await fireEvent.change(
            await screen.findByLabelText('Choose the files'),
            {
                target: { files: [file] },
            },
        );

        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/upload?link=vehicle:1',
        );
        expect(post.mock.calls[0]?.[1]).toMatchObject({ files: [file] });
    });

    it('says why a file was refused, and stays open', async () => {
        vi.spyOn(router, 'post').mockImplementation(
            (_url, _data, options?: VisitOptions) => {
                options?.onError?.({
                    'files.0': 'Each file can be at most 25 MB.',
                });
            },
        );
        renderPage(FmUploadSheet, {
            open: true,
            link: { type: 'vehicle', id: 1 },
        });

        await fireEvent.change(
            await screen.findByLabelText('Choose the files'),
            {
                target: { files: [file] },
            },
        );

        expect(
            await screen.findByText('Each file can be at most 25 MB.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
});
