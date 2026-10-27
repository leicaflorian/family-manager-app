import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Areas from './Areas.vue';

describe('Areas', () => {
    it('says the cards are coming soon and leads to the documents', () => {
        renderPage(Areas);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Areas' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The cards are coming soon'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Go to the documents' }),
        ).toHaveAttribute('href', '/documents');
    });
});
