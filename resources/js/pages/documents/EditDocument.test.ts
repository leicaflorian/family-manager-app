import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { documentDetail, documentFilterOptions } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import EditDocument from './EditDocument.vue';

const options = {
    ...documentFilterOptions,
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
});
