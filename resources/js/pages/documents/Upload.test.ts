import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Upload from './Upload.vue';

const shared = vi.hoisted(() => ({ to_sort_count: 0 }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: '/upload', props: { ...shared } }),
}));

/** The props of the server: only the card the person comes from. */
const props = { presetLinks: [] };

afterEach(() => {
    shared.to_sort_count = 0;
    vi.restoreAllMocks();
});

describe('Upload', () => {
    it('asks to choose the files, with the accepted types', () => {
        renderPage(Upload, { ...props });

        expect(
            screen.getByRole('heading', { name: 'Upload a document' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Choose the files')).toHaveAttribute(
            'accept',
            expect.stringContaining('.heic'),
        );
        expect(
            screen.getByText('PDF, JPG, PNG or HEIC, up to 25 MB each'),
        ).toBeInTheDocument();
    });

    it('sends the chosen files', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, { ...props });

        const file = new File(['%PDF-1.4'], 'Busta paga.pdf', {
            type: 'application/pdf',
        });
        await fireEvent.change(screen.getByLabelText('Choose the files'), {
            target: { files: [file] },
        });

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]?.[0]).toBe('/upload');
        expect(post.mock.calls[0]?.[1]).toMatchObject({ files: [file] });
    });

    it('leads to the files to sort when there are some', () => {
        shared.to_sort_count = 1;
        renderPage(Upload, { ...props });

        expect(
            screen.getByRole('link', { name: '1 file to sort' }),
        ).toHaveAttribute('href', '/to-sort');
    });

    it('does not mention the files to sort when there are none', () => {
        renderPage(Upload, { ...props });

        expect(
            screen.queryByRole('link', { name: /to sort/ }),
        ).not.toBeInTheDocument();
    });

    it('keeps the card it comes from when it sends the files', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, {
            ...props,
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });

        const file = new File(['%PDF-1.4'], 'Libretto.pdf', {
            type: 'application/pdf',
        });
        await fireEvent.change(screen.getByLabelText('Choose the files'), {
            target: { files: [file] },
        });

        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/upload?link=vehicle:1',
        );
    });

    it('closes to the card it comes from, or to the home page', () => {
        const result = renderPage(Upload, {
            ...props,
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });
        expect(screen.getByRole('link', { name: 'Close' })).toHaveAttribute(
            'href',
            '/cards/vehicle/1',
        );
        result.unmount();

        renderPage(Upload, { ...props });
        expect(screen.getByRole('link', { name: 'Close' })).toHaveAttribute(
            'href',
            '/dashboard',
        );
    });

    it('only takes the files: there is no form, the files are checked in Import', () => {
        renderPage(Upload, { ...props });

        expect(screen.queryByLabelText('What it is')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
    });
});
