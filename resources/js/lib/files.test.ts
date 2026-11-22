import { describe, expect, it } from 'vite-plus/test';
import { extensionOf, previewKind } from './files';

describe('previewKind', () => {
    it('shows the preview image when the server made one, whatever the type', () => {
        expect(previewKind('application/pdf', '/files/1/preview')).toBe(
            'preview',
        );
        expect(previewKind('image/heic', '/files/1/preview')).toBe('preview');
    });

    it('shows JPG and PNG as they are when there is no preview', () => {
        expect(previewKind('image/jpeg', null)).toBe('image');
        expect(previewKind('image/png', null)).toBe('image');
    });

    it('shows the placeholder for a PDF without preview and for any other type', () => {
        expect(previewKind('application/pdf', null)).toBe('other');
        expect(previewKind('image/heic', null)).toBe('other');
        expect(previewKind('application/zip', null)).toBe('other');
    });
});

describe('extensionOf', () => {
    it('gives the extension of the types a drawing or a document can have', () => {
        expect(extensionOf('image/jpeg')).toBe('.jpg');
        expect(extensionOf('image/png')).toBe('.png');
        expect(extensionOf('image/webp')).toBe('.webp');
        expect(extensionOf('image/heic')).toBe('.heic');
        expect(extensionOf('application/pdf')).toBe('.pdf');
    });

    it('gives nothing for a type it does not know', () => {
        expect(extensionOf('application/octet-stream')).toBe('');
        expect(extensionOf('')).toBe('');
    });
});
