import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
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
            screen.getByRole('img', { name: 'Busta paga ottobre 2026.pdf' }),
        ).toHaveAttribute('src', '/files/12/preview');
    });

    it('in the panel beside the list only Download and the ⋯ menu are on top', () => {
        renderPage(FmDocumentDetail, {
            document: documentDetail,
            compact: true,
        });

        expect(
            screen.getByRole('link', { name: 'Download' }),
        ).not.toHaveTextContent('Download');
        expect(
            screen.getByRole('button', { name: 'More actions' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });

    it('the ⋯ menu opens the file in a new tab and edits the document', async () => {
        renderPage(FmDocumentDetail, {
            document: documentDetail,
            compact: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );

        const open = await screen.findByRole('menuitem', {
            name: 'Open in a new tab',
        });
        expect(open).toHaveAttribute('href', '/files/12');
        expect(open).toHaveAttribute('target', '_blank');
        expect(screen.getByRole('menuitem', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/documents/5/edit',
        );
    });

    it('the ⋯ menu asks before deleting, then deletes', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(FmDocumentDetail, {
            document: documentDetail,
            compact: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Delete' }),
        );
        expect(destroy).not.toHaveBeenCalled();
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith('/documents/5', expect.anything());
    });

    it('without the permissions the ⋯ menu has neither Edit nor Delete', async () => {
        renderPage(FmDocumentDetail, {
            document: { ...documentDetail, can_edit: false, can_delete: false },
            compact: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );

        expect(
            await screen.findByRole('menuitem', { name: 'Open in a new tab' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('menuitem', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('menuitem', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });

    it('in the panel beside the list the title, the date and the buttons are on top, in one card', () => {
        const { container } = renderPage(FmDocumentDetail, {
            document: documentDetail,
            compact: true,
        });

        const heading = screen.getByRole('heading', {
            name: 'Busta paga di ottobre',
            level: 2,
        });
        expect(heading.querySelector('a')).toHaveAttribute(
            'href',
            '/documents/5',
        );
        expect(screen.getByText('Arrived on 23/10/2026')).toBeInTheDocument();
        expect(container.querySelectorAll('[data-card]')).toHaveLength(1);
    });

    it('on its own page the title is not repeated', () => {
        renderPage(FmDocumentDetail, { document: documentDetail });

        expect(
            screen.queryByRole('heading', { name: 'Busta paga di ottobre' }),
        ).not.toBeInTheDocument();
    });

    it('the button to add a link has no border', () => {
        renderPage(FmDocumentDetail, { document: documentDetail });

        expect(screen.getByTitle('Add a link')).not.toHaveClass('border');
    });

    it('shows the photo of the person the document belongs to', () => {
        renderPage(FmDocumentDetail, {
            document: { ...documentDetail, person_photo: '/files/40' },
        });

        expect(
            screen.getByRole('img', { name: documentDetail.person ?? '' }),
        ).toHaveAttribute('src', '/files/40');
    });

    it('leads every link to its card', () => {
        renderPage(FmDocumentDetail, {
            document: {
                ...documentDetail,
                links: [
                    {
                        type: 'vehicle',
                        id: 1,
                        type_label: 'Vehicles',
                        label: 'Fiat Panda',
                    },
                ],
            },
        });

        expect(
            screen.getByRole('link', { name: /Fiat Panda/ }),
        ).toHaveAttribute('href', '/cards/vehicle/1');
    });
});
