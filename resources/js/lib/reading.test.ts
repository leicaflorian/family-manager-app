import { describe, expect, it } from 'vite-plus/test';
import { anyFileReading, isNotRead, isReading } from './reading';

describe('isReading', () => {
    it('is true while the file waits in the queue or is read', () => {
        expect(isReading('queued')).toBe(true);
        expect(isReading('reading')).toBe(true);
    });

    it('is false for every other state, and for a file never read', () => {
        for (const status of [
            'done',
            'failed',
            'skipped',
            'off',
            null,
            undefined,
        ] as const) {
            expect(isReading(status)).toBe(false);
        }
    });
});

describe('isNotRead', () => {
    it('is true when the reading ended without a proposal', () => {
        expect(isNotRead('failed')).toBe(true);
        expect(isNotRead('off')).toBe(true);
        expect(isNotRead('skipped')).toBe(true);
    });

    it('is false otherwise', () => {
        for (const status of [
            'queued',
            'reading',
            'done',
            null,
            undefined,
        ] as const) {
            expect(isNotRead(status)).toBe(false);
        }
    });
});

describe('anyFileReading', () => {
    it('is true when one of the files waits in the queue or is read', () => {
        expect(
            anyFileReading([
                { reading_status: 'done' },
                { reading_status: 'queued' },
            ]),
        ).toBe(true);
    });

    it('is false when none is, and for no files', () => {
        expect(
            anyFileReading([
                { reading_status: 'done' },
                { reading_status: null },
            ]),
        ).toBe(false);
        expect(anyFileReading([])).toBe(false);
    });
});
