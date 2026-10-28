import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Document from './Document.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('A document', () => {
    it('shows the title, the way back to Documents and the document', () => {
        renderPage(Document, { document: documentDetail });

        expect(
            screen.getByRole('heading', {
                name: 'Busta paga di ottobre',
                level: 1,
            }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Documents' })).toHaveAttribute(
            'href',
            '/documents',
        );
        expect(
            screen.getByText('Laura - Comune di Bologna'),
        ).toBeInTheDocument();
    });

    it('has Download and Edit next to the title, as the board', () => {
        renderPage(Document, { document: documentDetail });

        expect(screen.getByRole('link', { name: 'Download' })).toHaveAttribute(
            'href',
            '/files/12/download',
        );
        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/documents/5/edit',
        );
    });

    it('has the links and the notes under their headings, beside the preview', () => {
        renderPage(Document, { document: documentDetail });

        expect(
            screen.getByRole('heading', { name: 'Links', level: 2 }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Employments/, pressed: true }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Add a link' }),
        ).toHaveAttribute('href', '/documents/5/edit');
        expect(
            screen.getByRole('heading', { name: 'Notes', level: 2 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Active')).toBeInTheDocument();
    });

    it('shows the links of one type at a time', async () => {
        renderPage(Document, {
            document: {
                ...documentDetail,
                links: [
                    ...documentDetail.links,
                    {
                        type: 'home',
                        id: 2,
                        type_label: 'Homes',
                        label: 'Via Roma 12',
                    },
                ],
            },
        });

        expect(screen.queryByText('Via Roma 12')).not.toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: /Homes/ }));

        expect(screen.getByText('Via Roma 12')).toBeInTheDocument();
        expect(
            screen.queryByText('Laura - Comune di Bologna'),
        ).not.toBeInTheDocument();
    });

    it('the ⋯ menu opens the file in a new tab', async () => {
        renderPage(Document, { document: documentDetail });

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        const menu = await screen.findByRole('menu');
        expect(
            within(menu).getByRole('menuitem', { name: 'Open in a new tab' }),
        ).toHaveAttribute('target', '_blank');
        expect(
            within(menu).queryByRole('menuitem', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });

    it('Delete beside Edit deletes after asking, as the page of a person', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Document, { document: documentDetail });

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));
        expect(destroy).not.toHaveBeenCalled();
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith('/documents/5', expect.anything());
    });

    it('without the permissions shows neither Edit nor Delete', async () => {
        renderPage(Document, {
            document: { ...documentDetail, can_edit: false, can_delete: false },
        });

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Add a link' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });
});
