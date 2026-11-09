import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { Scanner } from './agent';
import {
    colourFor,
    readChoices,
    resolutionFor,
    resolutionsOf,
    scannerFor,
    sidesText,
    sidesThatFit,
    sourceFor,
    writeChoices,
} from './choices';

const scanner = (id: string): Scanner => ({
    id,
    name: `Scanner ${id}`,
    connection: 'usb',
});

describe('scannerFor', () => {
    it('takes the only scanner, even when another was remembered', () => {
        expect(scannerFor([scanner('a')], 'b')).toBe('a');
    });

    it('takes the remembered one among three', () => {
        expect(
            scannerFor([scanner('a'), scanner('b'), scanner('c')], 'b'),
        ).toBe('b');
    });

    it('takes none among three when nothing is remembered', () => {
        expect(
            scannerFor([scanner('a'), scanner('b'), scanner('c')], null),
        ).toBeNull();
    });

    it('takes none when the remembered one is off', () => {
        expect(scannerFor([scanner('a'), scanner('c')], 'b')).toBeNull();
    });

    it('takes none when there is no scanner', () => {
        expect(scannerFor([], 'a')).toBeNull();
    });
});

describe('resolutions', () => {
    it('starts from 150 and takes the remembered one when the scanner has it', () => {
        expect(resolutionFor(null, [150, 200, 300])).toBe(150);
        expect(resolutionFor(300, [150, 200, 300])).toBe(300);
    });

    it('takes the nearest above when the scanner has not 150', () => {
        expect(resolutionFor(null, [200, 300])).toBe(200);
        expect(resolutionFor(null, [240, 600])).toBe(240);
    });

    it('takes the highest when nothing is above, and none without resolutions', () => {
        expect(resolutionFor(300, [100, 200])).toBe(200);
        expect(resolutionFor(150, [])).toBeNull();
    });

    it('offers only the three that the scanner has, or one without a name', () => {
        expect(resolutionsOf([150, 300, 600]).map((r) => r.dots)).toEqual([
            150, 300,
        ]);
        const only = resolutionsOf([240, 600]);
        expect(only).toHaveLength(1);
        expect(only[0].dots).toBe(240);
    });

    it('counts about how many sides fit in 25 MB', () => {
        expect(sidesThatFit(150, [150, 200, 300])).toBe(62);
        expect(sidesThatFit(200, [150, 200, 300])).toBe(38);
        expect(sidesThatFit(300, [150, 200, 300])).toBe(16);
        expect(sidesThatFit(600, [150])).toBeNull();
        expect(sidesText(40)).toBe('about 40 sides');
        expect(sidesText(1)).toBe('about 1 side');
    });
});

describe('colour and source', () => {
    it('starts from grey', () => {
        expect(colourFor(null, ['colour', 'grey'])).toBe('grey');
        expect(colourFor('colour', ['colour', 'grey'])).toBe('colour');
    });

    it('falls back to what the scanner has', () => {
        expect(colourFor('grey', ['colour'])).toBe('colour');
        expect(colourFor('colour', [])).toBe('colour');
    });

    it('starts from the feeder unless the scanner has not it', () => {
        expect(sourceFor(null, ['feeder', 'flatbed'])).toBe('feeder');
        expect(sourceFor('flatbed', ['feeder', 'flatbed'])).toBe('flatbed');
        expect(sourceFor('feeder', ['flatbed'])).toBe('flatbed');
    });
});

describe('remembered choices', () => {
    afterEach(() => vi.restoreAllMocks());

    it('keeps the scanner, the resolution and the source, not the colour', () => {
        const kept = new Map<string, string>();
        const storage = {
            getItem: (key: string) => kept.get(key) ?? null,
            setItem: (key: string, value: string) => void kept.set(key, value),
        };
        writeChoices(
            {
                scanner: { id: 'a', name: 'A' },
                resolution: 200,
                source: 'flatbed',
            },
            storage,
        );
        expect([...kept.keys()]).toEqual(['family-manager.scan.choices']);
        kept.set(
            'family-manager.scan.choices',
            JSON.stringify({
                scanner: { id: 'a', name: 'A' },
                resolution: 200,
                source: 'flatbed',
                colour: 'colour',
            }),
        );
        expect(readChoices(storage)).toEqual({
            scanner: { id: 'a', name: 'A' },
            resolution: 200,
            source: 'flatbed',
        });
    });

    it('survives a storage that does not work', () => {
        const broken = {
            getItem: () => {
                throw new Error('off');
            },
            setItem: () => {
                throw new Error('off');
            },
        };
        expect(readChoices(broken)).toEqual({});
        expect(() => writeChoices({ resolution: 150 }, broken)).not.toThrow();
    });

    it('ignores a damaged value', () => {
        expect(
            readChoices({ getItem: () => '{nope', setItem: () => undefined }),
        ).toEqual({});
    });
});
