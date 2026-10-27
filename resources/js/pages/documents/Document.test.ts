import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Document from './Document.vue';

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
});
