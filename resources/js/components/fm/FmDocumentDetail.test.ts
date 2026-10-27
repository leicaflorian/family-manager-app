import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import FmDocumentDetail from './FmDocumentDetail.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmDocumentDetail', () => {
    it('shows the data, the links and the notes of the document', () => {
        renderPage(FmDocumentDetail, { document: documentDetail });

        expect(screen.getByText('Lavoro › Buste paga')).toBeInTheDocument();
        expect(screen.getByText('Laura Bianchi')).toBeInTheDocument();
        expect(screen.getByText('23/10/2026')).toBeInTheDocument();
        expect(screen.getByText('No expiry')).toBeInTheDocument();
        expect(
            screen.getByText('Laura - Comune di Bologna'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('With the arrears of the contract.'),
        ).toBeInTheDocument();
        expect(screen.getByText('salary, 2026')).toBeInTheDocument();
        expect(
            screen.getByTitle('Busta paga ottobre 2026.pdf'),
        ).toHaveAttribute('src', '/files/12');
    });

    it('downloads and edits the document', () => {
        renderPage(FmDocumentDetail, { document: documentDetail });

        expect(screen.getByRole('link', { name: 'Download' })).toHaveAttribute(
            'href',
            '/files/12/download',
        );
        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/documents/5/edit',
        );
    });

    it('asks before deleting, then deletes', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(FmDocumentDetail, { document: documentDetail });

        await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
        expect(destroy).not.toHaveBeenCalled();
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith('/documents/5', expect.anything());
    });

    it('without the permissions shows neither Edit nor Delete', () => {
        renderPage(FmDocumentDetail, {
            document: { ...documentDetail, can_edit: false, can_delete: false },
        });

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });
});
