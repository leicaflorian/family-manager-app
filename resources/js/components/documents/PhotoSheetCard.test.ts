import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import PhotoSheetCard from './PhotoSheetCard.vue';

describe('PhotoSheetCard', () => {
    it('is a link to the scan, with the title and what comes after', () => {
        render(PhotoSheetCard);

        const link = screen.getByRole('link', {
            name: /Take a photo of a sheet/,
        });
        expect(link).toHaveAttribute('href', '/scan');
        expect(
            screen.getByText(
                'You crop and improve the photos, then you find it in To sort',
            ),
        ).toBeInTheDocument();
    });

    it('shows an arrow on the phone row', () => {
        const { container } = render(PhotoSheetCard);

        expect(
            container.querySelector('[data-testid="photo-sheet-arrow"]'),
        ).not.toBeNull();
    });

    it('has no arrow when stacked, as a column of the tablet', () => {
        const { container } = render(PhotoSheetCard, {
            props: { stacked: true },
        });

        expect(
            container.querySelector('[data-testid="photo-sheet-arrow"]'),
        ).toBeNull();
        expect(
            screen.getByRole('link', { name: /Take a photo of a sheet/ }),
        ).toBeInTheDocument();
    });
});
