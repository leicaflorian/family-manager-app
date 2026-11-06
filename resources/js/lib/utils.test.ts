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

describe('cn with the design tokens', () => {
    it('knows the sizes of the tokens, so the last size wins', () => {
        expect(cn('size-control-md', 'size-10')).toBe('size-10');
        expect(cn('h-control-md', 'h-control-lg')).toBe('h-control-lg');
    });

    it('knows the shadows of the tokens, apart from the colours', () => {
        expect(cn('shadow-menu', 'shadow-none')).toBe('shadow-none');
        expect(cn('bg-card shadow-raised', 'bg-segment')).toBe(
            'shadow-raised bg-segment',
        );
    });

    it('knows the radii of the tokens', () => {
        expect(cn('rounded-card', 'rounded-tag')).toBe('rounded-tag');
    });

    it('tells a text style from a text colour', () => {
        expect(cn('text-body text-ink', 'text-meta')).toBe(
            'text-ink text-meta',
        );
        expect(cn('text-meta text-ink', 'text-ink-soft')).toBe(
            'text-meta text-ink-soft',
        );
    });
});
