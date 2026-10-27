import { describe, expect, it } from 'vite-plus/test';
import { previewKind } from './files';

describe('previewKind', () => {
    it('shows a PDF in the page', () => {
        expect(previewKind('application/pdf')).toBe('pdf');
    });

    it('shows JPG and PNG as they are', () => {
        expect(previewKind('image/jpeg')).toBe('image');
        expect(previewKind('image/png')).toBe('image');
    });

    it('shows an icon for HEIC, which browsers do not open, and for any other type', () => {
        expect(previewKind('image/heic')).toBe('other');
        expect(previewKind('application/octet-stream')).toBe('other');
    });
});
