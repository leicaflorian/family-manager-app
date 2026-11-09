import { describe, expect, it, vi } from 'vite-plus/test';
import type { Pixels } from './crop';
import {
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
    it('keeps the size of the sheet, three planes one after the other from 0 to 1', () => {
        const input = prepareInput(flat(100, 300, [255, 0, 51]));

        const size = 100 * 300;
        expect(input.length).toBe(3 * size);
        expect(input[0]).toBeCloseTo(1);
        expect(input[size]).toBeCloseTo(0);
        expect(input[2 * size]).toBeCloseTo(0.2);
        expect(input[size - 1]).toBeCloseTo(1);
        expect(input[3 * size - 1]).toBeCloseTo(0.2);
    });

    it('does not move the pixels', () => {
        const input = prepareInput(gradient(5, 4));
        const size = 5 * 4;

        // red grows with x along each row, green with y down the rows
        expect(input[0]).toBeCloseTo(0);
        expect(input[4]).toBeCloseTo(1);
        expect(input[size]).toBeCloseTo(0);
        expect(input[size + 5 * 3]).toBeCloseTo(1);
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
    ): DewarpWorker & {
        sent: ToWorker[];
        terminated: boolean;
        emit: (type: 'error' | 'messageerror', message?: string) => void;
        reply: (answer: FromWorker) => void;
    } {
        const listeners: Array<(event: { data: FromWorker }) => void> = [];
        const problems: Record<
            string,
            Array<(event: { message?: string }) => void>
        > = {};
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
                type: string,
                listener: (event: never) => void,
            ): void {
                if (type === 'message') {
                    listeners.push(
                        listener as (event: { data: FromWorker }) => void,
                    );
                } else {
                    (problems[type] ??= []).push(
                        listener as (event: { message?: string }) => void,
                    );
                }
            },
            emit(type: 'error' | 'messageerror', message?: string): void {
                (problems[type] ?? []).forEach((listener) =>
                    listener({ message }),
                );
            },
            reply(answer: FromWorker): void {
                listeners.forEach((listener) => listener({ data: answer }));
            },
            terminate(): void {
                worker.terminated = true;
            },
        };

        return worker;
    }

    /** Answers the n-th message the silent worker was sent. */
    function answer(worker: ReturnType<typeof fakeWorker>, n: number): void {
        const message = worker.sent[n]! as Extract<
            ToWorker,
            { type: 'dewarp' }
        >;
        worker.reply({
            id: message.id,
            type: 'done',
            width: message.width,
            height: message.height,
            data: message.data,
        });
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

    it('rejects every waiting call when the worker breaks, and starts a new one next time', async () => {
        const first = fakeWorker({ silent: true });
        const second = fakeWorker();
        const workers = [first, second];
        const { dewarp } = createDewarper(() => workers.shift()!);

        const one = dewarp(flat(2, 2, [0, 0, 0]));
        const two = dewarp(flat(2, 2, [0, 0, 0]));
        first.emit('error', 'script failed to load');

        await expect(one).rejects.toThrow('script failed to load');
        await expect(two).rejects.toThrow('script failed to load');
        expect(first.terminated).toBe(true);
        // a new worker, which works
        await expect(dewarp(flat(2, 2, [0, 0, 0]))).resolves.toMatchObject({
            width: 2,
        });
    });

    it('treats an answer that cannot be read as a broken worker', async () => {
        const worker = fakeWorker({ silent: true });
        const { dewarp } = createDewarper(() => worker);

        const waiting = dewarp(flat(2, 2, [0, 0, 0]));
        worker.emit('messageerror');

        await expect(waiting).rejects.toThrow('cannot be read');
        expect(worker.terminated).toBe(true);
    });

    it('gives up a worker that does not answer in time', async () => {
        vi.useFakeTimers();
        try {
            const worker = fakeWorker({ silent: true });
            const { dewarp } = createDewarper(() => worker, {
                page: 1000,
                load: 5000,
            });

            const waiting = dewarp(flat(2, 2, [0, 0, 0]));
            const check = expect(waiting).rejects.toThrow('took too long');
            await vi.advanceTimersByTimeAsync(1000);

            await check;
            expect(worker.terminated).toBe(true);
        } finally {
            vi.useRealTimers();
        }
    });

    it('does not time out a call that was answered', async () => {
        vi.useFakeTimers();
        try {
            const worker = fakeWorker();
            const { dewarp } = createDewarper(() => worker, {
                page: 1000,
                load: 5000,
            });

            await dewarp(flat(2, 2, [0, 0, 0]));
            await vi.advanceTimersByTimeAsync(10_000);

            expect(worker.terminated).toBe(false);
        } finally {
            vi.useRealTimers();
        }
    });

    it('removes the listener of the signal when the call is over', async () => {
        const { dewarp } = createDewarper(() => fakeWorker());
        const stop = new AbortController();
        const remove = vi.spyOn(stop.signal, 'removeEventListener');

        await dewarp(flat(2, 2, [0, 0, 0]), stop.signal);

        expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
    });

    it('counts the time of a page from when the worker takes it, not from when it was asked', async () => {
        vi.useFakeTimers();
        try {
            const worker = fakeWorker({ silent: true });
            const { dewarp } = createDewarper(() => worker, {
                page: 1000,
                load: 5000,
            });

            const one = dewarp(flat(2, 2, [0, 0, 0]));
            const two = dewarp(flat(2, 2, [0, 0, 0]));
            const check = expect(two).resolves.toMatchObject({ width: 2 });
            await vi.advanceTimersByTimeAsync(800);
            // the first page is done: the second one starts its own 1000 ms now
            answer(worker, 0);
            await one;
            await vi.advanceTimersByTimeAsync(800);

            expect(worker.terminated).toBe(false);

            answer(worker, 1);
            await check;
        } finally {
            vi.useRealTimers();
        }
    });

    it('still gives up a worker that is stuck on the page it has', async () => {
        vi.useFakeTimers();
        try {
            const worker = fakeWorker({ silent: true });
            const { dewarp } = createDewarper(() => worker, {
                page: 1000,
                load: 5000,
            });

            const one = dewarp(flat(2, 2, [0, 0, 0]));
            const two = dewarp(flat(2, 2, [0, 0, 0]));
            const checks = [
                expect(one).rejects.toThrow('took too long'),
                expect(two).rejects.toThrow('took too long'),
            ];
            await vi.advanceTimersByTimeAsync(1000);

            await Promise.all(checks);
            expect(worker.terminated).toBe(true);
        } finally {
            vi.useRealTimers();
        }
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
