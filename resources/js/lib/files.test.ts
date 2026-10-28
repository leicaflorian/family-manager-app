import { describe, expect, it } from 'vite-plus/test';
import { previewKind } from './files';

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
