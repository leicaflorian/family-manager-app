import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmTitle from './FmTitle.vue';

describe('FmTitle', () => {
    it('is the title of the page with the sentence below and the actions on the right', () => {
        render(FmTitle, {
            props: { title: 'Documents', subtitle: '18 documents' },
            slots: { actions: '<button>Upload</button>' },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Documents' }),
        ).toBeInTheDocument();
        expect(screen.getByText('18 documents')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Upload' }),
        ).toBeInTheDocument();
    });

    it('has the link back to the page it comes from, above the title', () => {
        renderPage(FmTitle, {
            title: 'Edit the document',
            back: '/documents/5',
            backLabel: 'Busta paga di ottobre',
        });

        const back = screen.getByRole('link', {
            name: 'Busta paga di ottobre',
        });
        expect(back).toHaveAttribute('href', '/documents/5');
        expect(
            back.compareDocumentPosition(
                screen.getByRole('heading', { level: 1 }),
            ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('without a page to go back to has no link', () => {
        renderPage(FmTitle, { title: 'Documents' });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});
