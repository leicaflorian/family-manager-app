import { fireEvent, screen } from '@testing-library/vue';
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
});
