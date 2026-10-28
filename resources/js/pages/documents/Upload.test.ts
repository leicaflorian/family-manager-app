import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import Upload from './Upload.vue';

const options = {
    categories: [],
    people: [],
    organizations: [],
    links: [],
    statuses: [{ value: 'active', label: 'Active', help: null }],
    tags: [],
    presetLinks: [],
};

const payslip = {
    id: 12,
    name: 'Busta paga novembre 2026.pdf',
    mime_type: 'application/pdf',
    size: 120000,
    url: '/files/12',
    preview_url: '/files/12/preview',
    title: 'Busta paga novembre 2026',
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Upload', () => {
    it('without files waiting, asks to choose them, with the accepted types', () => {
        renderPage(Upload, { pending: [], ...options });

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
        renderPage(Upload, { pending: [], ...options });

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

    it('shows the form of the first file waiting, with its preview and how many are left', () => {
        renderPage(Upload, {
            pending: [
                payslip,
                {
                    ...payslip,
                    id: 13,
                    name: 'Scontrino.png',
                    title: 'Scontrino',
                },
            ],
            ...options,
        });

        expect(screen.getByText('1 of 2')).toBeInTheDocument();
        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga novembre 2026',
        );
        expect(
            screen.getByRole('img', { name: 'Busta paga novembre 2026.pdf' }),
        ).toHaveAttribute('src', '/files/12/preview');
        expect(
            screen.getByRole('button', { name: 'Save' }),
        ).toBeInTheDocument();
    });

    it('discards the file waiting', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Upload, { pending: [payslip], ...options });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Discard this file' }),
        );

        expect(destroy).toHaveBeenCalledWith('/upload/12', expect.anything());
    });

    it('starts the form with the card it comes from among the links', () => {
        renderPage(Upload, {
            pending: [payslip],
            ...options,
            links: [
                {
                    type: 'vehicle',
                    label: 'Vehicles',
                    options: [{ value: '1', label: 'Fiat Panda', help: null }],
                },
            ],
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });

        expect(
            screen.getByRole('button', { name: 'Remove the link Fiat Panda' }),
        ).toBeInTheDocument();
    });

    it('keeps the card it comes from when it sends the files', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, {
            pending: [],
            ...options,
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

    it('keeps the card it comes from when it files the document', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Upload, {
            pending: [payslip],
            ...options,
            presetLinks: [{ type: 'vehicle', id: 1 }],
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/documents?link=vehicle:1',
        );
    });
});
