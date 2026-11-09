import { describe, expect, it } from 'vite-plus/test';
import type { Pixels } from './crop';
import {
    MODEL_HEIGHT,
    MODEL_WIDTH,
    createDewarper,
    fromModelOutput,
    isDewarpAvailable,
    prepareInput,
    resizePixels,
    type DewarpWorker,
    type FromWorker,
    type ToWorker,
} from './dewarp';

/** A picture whose every pixel is different, so a wrong move shows. */
function gradient(width: number, height: number): Pixels {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = (y * width + x) * 4;
            data[i] = (x * 255) / Math.max(1, width - 1);
            data[i + 1] = (y * 255) / Math.max(1, height - 1);
            data[i + 2] = 128;
            data[i + 3] = 255;
        }
    }

    return { width, height, data };
}

/** A picture of a single colour. */
function flat(
    width: number,
    height: number,
    rgb: [number, number, number],
): Pixels {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let i = 0; i < width * height; i++) {
        data.set([...rgb, 255], i * 4);
    }

    return { width, height, data };
}

describe('resizePixels', () => {
    it('keeps a picture that already has the size', () => {
        const source = gradient(5, 4);

        expect(resizePixels(source, 5, 4).data).toEqual(source.data);
    });

    it('gives the new size and keeps the corners', () => {
        const source = gradient(10, 10);
        const small = resizePixels(source, 4, 6);

        expect([small.width, small.height]).toEqual([4, 6]);
        expect(small.data.length).toBe(4 * 6 * 4);
        // the first and the last pixel are the corners of the source
        expect(Array.from(small.data.slice(0, 4))).toEqual(
            Array.from(source.data.slice(0, 4)),
        );
        expect(Array.from(small.data.slice(-4))).toEqual(
            Array.from(source.data.slice(-4)),
        );
    });

    it('keeps a single colour whatever the size', () => {
        const big = resizePixels(flat(3, 3, [10, 200, 90]), 9, 7);

        expect(Array.from(big.data.slice(0, 4))).toEqual([10, 200, 90, 255]);
        expect(Array.from(big.data.slice(-4))).toEqual([10, 200, 90, 255]);
    });
});

describe('prepareInput', () => {
    it('has the size of the model, the three colours one after the other from 0 to 1', () => {
        const input = prepareInput(flat(100, 300, [255, 0, 51]));

        expect([MODEL_WIDTH, MODEL_HEIGHT]).toEqual([488, 712]);
        expect(input.length).toBe(3 * 488 * 712);
        const size = 488 * 712;
        expect(input[0]).toBeCloseTo(1);
        expect(input[size]).toBeCloseTo(0);
        expect(input[2 * size]).toBeCloseTo(0.2);
        expect(input[size - 1]).toBeCloseTo(1);
        expect(input[3 * size - 1]).toBeCloseTo(0.2);
    });

    it('stretches the sheet over the whole input', () => {
        const input = prepareInput(gradient(50, 80));
        const size = MODEL_WIDTH * MODEL_HEIGHT;

        // red grows with x: left edge 0, right edge 1
        expect(input[0]).toBeCloseTo(0);
        expect(input[MODEL_WIDTH - 1]).toBeCloseTo(1);
        // green grows with y: first row 0, last row 1
        expect(input[size]).toBeCloseTo(0);
        expect(input[size + size - 1]).toBeCloseTo(1);
    });
});

describe('fromModelOutput', () => {
    it('turns the three planes from 0 to 1 into opaque pixels, rounded and kept between 0 and 255', () => {
        // 2 × 1 pixels: red 1 and 0.5, green 0 and 1.4 (too much), blue 0.2 and -0.3 (too little)
        const values = Float32Array.from([1, 0.5, 0, 1.4, 0.2, -0.3]);

        const pixels = fromModelOutput(values, 2, 1);

        expect([pixels.width, pixels.height]).toEqual([2, 1]);
        expect(Array.from(pixels.data)).toEqual([
            255, 0, 51, 255, 128, 255, 0, 255,
        ]);
    });
});

describe('the worker', () => {
    /** A worker that answers like the real one: loads, then gives the sheet back inverted. */
    function fakeWorker(
        options: { silent?: boolean; fail?: boolean } = {},
    ): DewarpWorker & { sent: ToWorker[]; terminated: boolean } {
        const listeners: Array<(event: { data: FromWorker }) => void> = [];
        const worker = {
            sent: [] as ToWorker[],
            terminated: false,
            postMessage(message: ToWorker): void {
                worker.sent.push(message);
                if (options.silent) {
                    return;
                }
                const answer: FromWorker = options.fail
                    ? { id: message.id, type: 'error', message: 'broken' }
                    : message.type === 'load'
                      ? { id: message.id, type: 'loaded' }
                      : {
                            id: message.id,
                            type: 'done',
                            width: message.width,
                            height: message.height,
                            data: message.data.map((value, i) =>
                                i % 4 === 3 ? value : 255 - value,
                            ),
                        };
                queueMicrotask(() =>
                    listeners.forEach((listener) => listener({ data: answer })),
                );
            },
            addEventListener(
                _type: 'message',
                listener: (event: { data: FromWorker }) => void,
            ): void {
                listeners.push(listener);
            },
            terminate(): void {
                worker.terminated = true;
            },
        };

        return worker;
    }

    it('gives the sheet back flat, with the size it had', async () => {
        const worker = fakeWorker();
        const { dewarp } = createDewarper(() => worker);

        const result = await dewarp(flat(6, 4, [10, 20, 30]));

        expect([result.width, result.height]).toEqual([6, 4]);
        expect(Array.from(result.data.slice(0, 4))).toEqual([
            245, 235, 225, 255,
        ]);
    });

    it('starts one worker for all the calls', async () => {
        let started = 0;
        const worker = fakeWorker();
        const { dewarp, load } = createDewarper(() => {
            started++;

            return worker;
        });

        await load();
        await dewarp(flat(2, 2, [0, 0, 0]));
        await dewarp(flat(2, 2, [0, 0, 0]));

        expect(started).toBe(1);
    });

    it('does not touch the sheet that was given', async () => {
        const { dewarp } = createDewarper(() => fakeWorker());
        const sheet = flat(3, 3, [10, 20, 30]);

        await dewarp(sheet);

        expect(Array.from(sheet.data.slice(0, 4))).toEqual([10, 20, 30, 255]);
    });

    it('stops waiting when the signal is aborted, without waiting for the worker', async () => {
        const { dewarp } = createDewarper(() => fakeWorker({ silent: true }));
        const stop = new AbortController();

        const waiting = dewarp(flat(2, 2, [0, 0, 0]), stop.signal);
        stop.abort();

        await expect(waiting).rejects.toMatchObject({ name: 'AbortError' });
    });

    it('does not even start with a signal already aborted', async () => {
        let started = 0;
        const { dewarp } = createDewarper(() => {
            started++;

            return fakeWorker();
        });

        await expect(
            dewarp(flat(2, 2, [0, 0, 0]), AbortSignal.abort()),
        ).rejects.toMatchObject({ name: 'AbortError' });
        expect(started).toBe(0);
    });

    it('says what went wrong', async () => {
        const { dewarp } = createDewarper(() => fakeWorker({ fail: true }));

        await expect(dewarp(flat(2, 2, [0, 0, 0]))).rejects.toThrow('broken');
    });
});

describe('isDewarpAvailable', () => {
    it('is false where there is no worker, as in jsdom', () => {
        expect(isDewarpAvailable()).toBe(false);
    });
});
