import { describe, expect, it } from 'vite-plus/test';
import { tickBoxClass } from './tick';

describe('tickBoxClass', () => {
    it('is the blue box when ticked and the empty bordered box when not', () => {
        expect(tickBoxClass(true)).toContain('bg-action');
        expect(tickBoxClass(true)).not.toContain('border-ink-faint');
        expect(tickBoxClass(false)).toContain('border-ink-faint');
        expect(tickBoxClass(false)).not.toContain('bg-action');
        expect(tickBoxClass(false)).toContain('size-[22px]');
    });
});
