import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmConfirmDelete from './FmConfirmDelete.vue';

const props = {
    open: true,
    url: '/documents/5',
    question: 'Delete this document?',
    text: 'The file goes too.',
    confirmLabel: 'Delete the document',
};

describe('FmConfirmDelete', () => {
    it('asks, with what happens, then deletes', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(FmConfirmDelete, props);

        expect(
            await screen.findByRole('heading', {
                name: 'Delete this document?',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('The file goes too.')).toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the document' }),
        );

        expect(destroy).toHaveBeenCalledWith('/documents/5', expect.anything());
    });

    it('closed, it shows nothing', () => {
        renderPage(FmConfirmDelete, { ...props, open: false });

        expect(
            screen.queryByRole('heading', { name: 'Delete this document?' }),
        ).not.toBeInTheDocument();
    });
});
