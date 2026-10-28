import { describe, expect, it } from 'vite-plus/test';
import { cn } from './utils';

describe('cn', () => {
    it('joins the classes and leaves out the empty ones', () => {
        expect(cn('flex gap-2', false, '', null, undefined, 'w-full')).toBe(
            'flex gap-2 w-full',
        );
    });

    it('keeps only the last of two classes that clash', () => {
        expect(cn('px-4 text-ink', 'px-6')).toBe('text-ink px-6');
    });
});
