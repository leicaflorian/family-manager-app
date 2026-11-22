import { describe, expect, it } from 'vite-plus/test';
import { detailStep } from './detail';

describe('detailStep', () => {
    it('is a 40 px square on the page background, dimmed when disabled', () => {
        expect(detailStep).toContain('size-10');
        expect(detailStep).toContain('bg-canvas');
        expect(detailStep).toContain('disabled:opacity-40');
    });
});
