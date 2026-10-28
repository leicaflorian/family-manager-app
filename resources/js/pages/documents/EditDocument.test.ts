import { router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { DocumentFormOptions } from '@/lib/documents';
import { documentDetail, documentFilterOptions } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import EditDocument from './EditDocument.vue';

const options: DocumentFormOptions = {
    ...documentFilterOptions,
    tags: [],
    links: [
        {
            type: 'employment',
            label: 'Employments',
            options: [
                { value: '3', label: 'Laura - Comune di Bologna', help: null },
            ],
        },
    ],
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Edit a document', () => {
    it('starts from the document as it is', () => {
        renderPage(EditDocument, { document: documentDetail, options });

        expect(
            screen.getByRole('heading', { name: 'Edit the document' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('What it is')).toHaveValue(
            'Busta paga di ottobre',
        );
        expect(
            screen.getByRole('button', {
                name: 'Remove the link Laura - Comune di Bologna',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Busta paga ottobre 2026.pdf'),
        ).toBeInTheDocument();
    });

    it('saves the change on the document', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditDocument, { document: documentDetail, options });

        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledOnce();
        expect(put.mock.calls[0]?.[0]).toBe('/documents/5');
        expect(put.mock.calls[0]?.[1]).toMatchObject({
            title: 'Busta paga di ottobre',
            category_id: 4,
            links: [{ type: 'employment', id: 3 }],
        });
    });

    it('shows the file beside the form, as the board', () => {
        renderPage(EditDocument, { document: documentDetail, options });

        expect(
            screen.getByRole('img', { name: 'Busta paga ottobre 2026.pdf' }),
        ).toHaveAttribute('src', '/files/12/preview');
        expect(
            screen.queryByRole('link', { name: 'Close' }),
        ).not.toBeInTheDocument();
    });

    it('deletes the document from the bottom of the form, after asking', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(EditDocument, { document: documentDetail, options });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the document' }),
        );
        expect(destroy).not.toHaveBeenCalled();
        const panel = await screen.findByRole('dialog');
        await fireEvent.click(
            within(panel).getByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith('/documents/5', expect.anything());
    });

    it('without the permission it does not offer to delete', () => {
        renderPage(EditDocument, {
            document: { ...documentDetail, can_delete: false },
            options,
        });

        expect(
            screen.queryByRole('button', { name: 'Delete the document' }),
        ).not.toBeInTheDocument();
    });

    it('shows the tags as chips, each one with its ×, and sends what is left', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditDocument, { document: documentDetail, options });

        const tags = screen
            .getByRole('combobox', { name: 'Tags' })
            .closest<HTMLElement>('[data-type]')!;
        expect(within(tags).getByText('salary')).toBeInTheDocument();
        await fireEvent.click(
            within(tags).getAllByRole('button', { name: 'Remove' })[0]!,
        );
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put.mock.calls[0]?.[1]).toMatchObject({ tags: ['2026'] });
    });
});
