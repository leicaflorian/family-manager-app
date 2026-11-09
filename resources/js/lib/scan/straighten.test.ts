import { describe, expect, it, vi } from 'vite-plus/test';
import type { Corners, Pixels } from './crop';
import { createStraightener } from './straighten';

const sheet = (): Pixels => ({
    width: 1,
    height: 1,
    data: new Uint8ClampedArray([1, 2, 3, 255]),
});
const CORNERS: Corners = [
    { x: 0.1, y: 0.1 },
    { x: 0.9, y: 0.1 },
    { x: 0.9, y: 0.9 },
    { x: 0.1, y: 0.9 },
];

describe('createStraightener', () => {
    it('lays the sheet flat once and gives the same flat sheet for the same corners and turns', async () => {
        const run = vi.fn(async () => sheet());
        const { flatten } = createStraightener(run);

        const first = await flatten('a', CORNERS, 0, sheet());
        const again = await flatten('a', CORNERS, 0, sheet());

        expect(run).toHaveBeenCalledTimes(1);
        expect(again).toBe(first);
    });

    it('does it again when the corners or the turns change', async () => {
        const run = vi.fn(async () => sheet());
        const { flatten } = createStraightener(run);

        await flatten('a', CORNERS, 0, sheet());
        await flatten('a', CORNERS, 1, sheet());
        const moved: Corners = [
            { x: 0, y: 0 },
            CORNERS[1],
            CORNERS[2],
            CORNERS[3],
        ];
        await flatten('a', moved, 1, sheet());

        expect(run).toHaveBeenCalledTimes(3);
    });

    it('keeps a flat sheet for each page', async () => {
        const run = vi.fn(async () => sheet());
        const { flatten } = createStraightener(run);

        await flatten('a', CORNERS, 0, sheet());
        await flatten('b', CORNERS, 0, sheet());
        await flatten('a', CORNERS, 0, sheet());

        expect(run).toHaveBeenCalledTimes(2);
    });

    it('forgets a page', async () => {
        const run = vi.fn(async () => sheet());
        const { flatten, forget } = createStraightener(run);

        await flatten('a', CORNERS, 0, sheet());
        forget('a');
        await flatten('a', CORNERS, 0, sheet());

        expect(run).toHaveBeenCalledTimes(2);
    });

    it('keeps nothing of a call that failed or was stopped, and passes the signal on', async () => {
        const run = vi
            .fn<(sheet: Pixels, signal?: AbortSignal) => Promise<Pixels>>()
            .mockRejectedValueOnce(new Error('broken'))
            .mockImplementation(async () => sheet());
        const { flatten } = createStraightener(run);
        const stop = new AbortController();

        await expect(
            flatten('a', CORNERS, 0, sheet(), stop.signal),
        ).rejects.toThrow('broken');
        await flatten('a', CORNERS, 0, sheet(), stop.signal);

        expect(run).toHaveBeenCalledTimes(2);
        expect(run.mock.calls[1]![1]).toBe(stop.signal);
    });

    it('lets go of the flat sheet when the page is cut again with other corners, keeps it with the same ones', async () => {
        const run = vi.fn(async () => sheet());
        const { flatten, invalidate } = createStraightener(run);

        await flatten('a', CORNERS, 0, sheet());
        invalidate('a', CORNERS, 0);
        await flatten('a', CORNERS, 0, sheet());
        invalidate('a', CORNERS, 1);
        await flatten('a', CORNERS, 0, sheet());

        expect(run).toHaveBeenCalledTimes(2);
    });
});
