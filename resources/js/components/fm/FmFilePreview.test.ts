import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFilePreview from './FmFilePreview.vue';

const placeholder = expect.stringContaining('document-placeholder');

describe('FmFilePreview', () => {
    it('shows the preview image of a PDF, which opens the file', () => {
        const { container } = render(FmFilePreview, {
            props: {
                url: '/files/1',
                mimeType: 'application/pdf',
                name: 'Bill.pdf',
                previewUrl: '/files/1/preview',
            },
        });

        const image = screen.getByRole('img', { name: 'Bill.pdf' });
        expect(image).toHaveAttribute('src', '/files/1/preview');
        expect(screen.getByRole('link')).toContainElement(image);
        expect(screen.getByRole('link')).toHaveAttribute('href', '/files/1');
        expect(screen.getByRole('link')).toHaveAttribute('target', '_blank');
        expect(container.querySelector('iframe')).toBeNull();
    });

    it('shows the placeholder, which opens the file, when the preview image does not load', async () => {
        render(FmFilePreview, {
            props: {
                url: '/files/1',
                mimeType: 'application/pdf',
                name: 'Bill.pdf',
                previewUrl: '/files/1/preview',
            },
        });

        await fireEvent.error(screen.getByRole('img', { name: 'Bill.pdf' }));

        const link = screen.getByRole('link', { name: 'Open the file' });
        expect(link).toHaveAttribute('href', '/files/1');
        expect(link).toHaveAttribute('target', '_blank');
        expect(screen.getByRole('img', { name: 'Bill.pdf' })).toHaveAttribute(
            'src',
            placeholder,
        );
    });

    it('shows the placeholder for a PDF without preview', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/1',
                mimeType: 'application/pdf',
                name: 'Bill.pdf',
                previewUrl: null,
            },
        });

        expect(screen.getByRole('img', { name: 'Bill.pdf' })).toHaveAttribute(
            'src',
            placeholder,
        );
        expect(
            screen.getByRole('link', { name: 'Open the file' }),
        ).toHaveAttribute('href', '/files/1');
    });

    it('shows the placeholder for a type that has no preview', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/4',
                mimeType: 'application/zip',
                name: 'Archive.zip',
                previewUrl: null,
            },
        });

        expect(
            screen.getByRole('img', { name: 'Archive.zip' }),
        ).toHaveAttribute('src', placeholder);
        expect(
            screen.getByRole('link', { name: 'Open the file' }),
        ).toHaveAttribute('href', '/files/4');
    });

    it('shows the placeholder when a PNG does not load', async () => {
        render(FmFilePreview, {
            props: {
                url: '/files/2',
                mimeType: 'image/png',
                name: 'Receipt.png',
                previewUrl: null,
            },
        });

        await fireEvent.error(screen.getByRole('img', { name: 'Receipt.png' }));

        expect(
            screen.getByRole('img', { name: 'Receipt.png' }),
        ).toHaveAttribute('src', placeholder);
        expect(
            screen.getByRole('link', { name: 'Open the file' }),
        ).toHaveAttribute('href', '/files/2');
    });

    it('shows the preview of a HEIC photo', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/3',
                mimeType: 'image/heic',
                name: 'Photo.heic',
                previewUrl: '/files/3/preview',
            },
        });

        expect(screen.getByRole('img', { name: 'Photo.heic' })).toHaveAttribute(
            'src',
            '/files/3/preview',
        );
    });

    it('shows a PNG as it is', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/2',
                mimeType: 'image/png',
                name: 'Receipt.png',
                previewUrl: null,
            },
        });

        expect(
            screen.getByRole('img', { name: 'Receipt.png' }),
        ).toHaveAttribute('src', '/files/2');
    });

    it('shows the new preview when the props change to another file after a failure', async () => {
        const view = render(FmFilePreview, {
            props: {
                url: '/files/1',
                mimeType: 'application/pdf',
                name: 'Bill.pdf',
                previewUrl: '/files/1/preview',
            },
        });
        await fireEvent.error(screen.getByRole('img', { name: 'Bill.pdf' }));

        await view.rerender({
            url: '/files/5',
            mimeType: 'application/pdf',
            name: 'Other.pdf',
            previewUrl: '/files/5/preview',
        });

        expect(screen.getByRole('img', { name: 'Other.pdf' })).toHaveAttribute(
            'src',
            '/files/5/preview',
        );
    });

    it('with fill takes the height of the box around it, not a fixed one', () => {
        const { container } = render(FmFilePreview, {
            props: {
                url: '/files/12',
                mimeType: 'application/pdf',
                name: 'Busta paga.pdf',
                previewUrl: '/files/12/preview',
                fill: true,
            },
        });

        const image = screen.getByRole('img', { name: 'Busta paga.pdf' });
        expect(container.firstElementChild).not.toHaveClass('min-h-[420px]');
        expect(image).toHaveClass('max-h-full');
        expect(image).not.toHaveClass('max-h-[560px]');
    });

    it('with fill the placeholder takes the height of the box too', () => {
        render(FmFilePreview, {
            props: {
                url: '/files/12',
                mimeType: 'application/pdf',
                name: 'Busta paga.pdf',
                previewUrl: null,
                fill: true,
            },
        });

        expect(screen.getByRole('img', { name: 'Busta paga.pdf' })).toHaveClass(
            'max-h-full',
        );
    });
});
