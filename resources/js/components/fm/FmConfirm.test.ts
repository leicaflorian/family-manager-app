import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmConfirm from './FmConfirm.vue';

const props = {
    open: true,
    question: 'Remove the link?',
    text: 'The document is no longer linked to it.',
    confirmLabel: 'Remove the link',
};

describe('FmConfirm', () => {
    it('asks, with what happens, and says when the person confirms', async () => {
        const result = renderPage(FmConfirm, props);

        expect(
            await screen.findByRole('heading', { name: 'Remove the link?' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The document is no longer linked to it.'),
        ).toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Remove the link' }),
        );

        expect(result.emitted('confirm')).toHaveLength(1);
    });

    it('closes with Cancel, without confirming', async () => {
        const result = renderPage(FmConfirm, props);

        await fireEvent.click(
            await screen.findByRole('button', { name: 'Cancel' }),
        );

        expect(result.emitted('update:open')).toEqual([[false]]);
        expect(result.emitted('confirm')).toBeUndefined();
    });

    it('sits above a panel already open, as the question asked from it', async () => {
        renderPage(FmConfirm, props);

        const question = await screen.findByRole('dialog', {
            name: 'Remove the link?',
        });

        // The panels are on z-50: the question goes over them
        expect(question.className).toContain('z-[70]');
    });

    it('starts on the button that does not confirm', async () => {
        renderPage(FmConfirm, props);

        const cancel = await screen.findByRole('button', { name: 'Cancel' });

        await waitFor(() => expect(cancel).toHaveFocus());
    });

    it('can name both buttons and confirm with the solid one, for an action that is not a loss', async () => {
        const result = renderPage(FmConfirm, {
            ...props,
            question: 'Upload 3 pages?',
            text: '3 pages, in a single PDF',
            confirmLabel: 'Upload',
            cancelLabel: 'Not yet',
            confirmVariant: 'solid',
        });

        const notYet = await screen.findByRole('button', { name: 'Not yet' });
        await waitFor(() => expect(notYet).toHaveFocus());
        const upload = screen.getByRole('button', { name: 'Upload' });
        expect(upload.className).toContain('bg-action');

        await fireEvent.click(upload);
        expect(result.emitted('confirm')).toHaveLength(1);
    });
});
