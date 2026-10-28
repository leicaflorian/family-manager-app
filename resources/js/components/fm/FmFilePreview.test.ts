import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFilePreview from './FmFilePreview.vue';

describe('FmFilePreview', () => {
    it('shows a PDF in a frame', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/1',
                mimeType: 'application/pdf',
                name: 'Bill.pdf',
            },
        });

        expect(screen.getByTitle('Bill.pdf')).toHaveAttribute(
            'src',
            '/files/1',
        );
    });

    it('shows an image as it is', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/2',
                mimeType: 'image/png',
                name: 'Receipt.png',
            },
        });

        expect(
            screen.getByRole('img', { name: 'Receipt.png' }),
        ).toHaveAttribute('src', '/files/2');
    });

    it('shows the name and a link to open the file for the other types', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/3',
                mimeType: 'image/heic',
                name: 'Photo.heic',
            },
        });

        expect(screen.getByText('Photo.heic')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Open the file' }),
        ).toHaveAttribute('href', '/files/3');
    });

    it('with fill takes the height of the box around it, not a fixed one', () => {
        const { container } = render(FmFilePreview, {
            props: {
                url: '/files/12',
                mimeType: 'application/pdf',
                name: 'Busta paga.pdf',
                fill: true,
            },
        });

        expect(container.firstElementChild).not.toHaveClass('min-h-[420px]');
        expect(screen.getByTitle('Busta paga.pdf')).toHaveClass('h-full');
        expect(screen.getByTitle('Busta paga.pdf')).not.toHaveClass(
            'h-[560px]',
        );
    });
});
