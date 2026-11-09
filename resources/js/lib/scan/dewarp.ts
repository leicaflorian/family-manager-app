/**
 * *Raddrizza* in the phone: a cropped sheet that is not flat (folded, rolled,
 * held in the hand) comes back laid flat. UVDoc, a small model made for photos
 * of documents, runs in a Web Worker with `onnxruntime-web`: nothing is sent to
 * a server. The model moves the pixels of the sheet, it does not draw them
 * again.
 *
 * This file is what the pages import: the preparation of the input and the
 * reading of the output (plain functions on pixels, tested without the model)
 * and `dewarp()`, which talks to the worker (`dewarp.worker.ts`). The engine and
 * the model are loaded in the worker only, with an `import()` the first time,
 * so no other page carries them.
 */
import type { Pixels } from './crop';

/** The size UVDoc was made for: the sheet is stretched to it before the model runs. */
export const MODEL_WIDTH = 488;
export const MODEL_HEIGHT = 712;

/** What the page tells the worker. */
export type ToWorker =
    | { id: number; type: 'load' }
    | {
          id: number;
          type: 'dewarp';
          width: number;
          height: number;
          data: Uint8ClampedArray;
      };

/** What the worker answers. */
export type FromWorker =
    | { id: number; type: 'loaded' }
    | {
          id: number;
          type: 'done';
          width: number;
          height: number;
          data: Uint8ClampedArray;
      }
    | { id: number; type: 'error'; message: string };

/** The part of a `Worker` that is used, so a test can give a fake one. */
export type DewarpWorker = {
    postMessage(message: ToWorker, transfer?: Transferable[]): void;
    addEventListener(
        type: 'message',
        listener: (event: { data: FromWorker }) => void,
    ): void;
    terminate(): void;
};

/**
 * Gives a picture another size, with bilinear interpolation. The corners of the
 * new picture are the corners of the old one.
 *
 * @param image - the picture to resize
 * @param width - the new width, in pixels
 * @param height - the new height, in pixels
 * @returns a new picture
 */
export function resizePixels(
    image: Pixels,
    width: number,
    height: number,
): Pixels {
    const data = new Uint8ClampedArray(width * height * 4);
    const stepX = width > 1 ? (image.width - 1) / (width - 1) : 0;
    const stepY = height > 1 ? (image.height - 1) / (height - 1) : 0;

    for (let y = 0; y < height; y++) {
        const sy = y * stepY;
        const y0 = Math.floor(sy);
        const y1 = Math.min(y0 + 1, image.height - 1);
        const fy = sy - y0;

        for (let x = 0; x < width; x++) {
            const sx = x * stepX;
            const x0 = Math.floor(sx);
            const x1 = Math.min(x0 + 1, image.width - 1);
            const fx = sx - x0;
            const a = (y0 * image.width + x0) * 4;
            const b = (y0 * image.width + x1) * 4;
            const c = (y1 * image.width + x0) * 4;
            const d = (y1 * image.width + x1) * 4;
            const out = (y * width + x) * 4;

            for (let k = 0; k < 4; k++) {
                const top =
                    image.data[a + k]! * (1 - fx) + image.data[b + k]! * fx;
                const bottom =
                    image.data[c + k]! * (1 - fx) + image.data[d + k]! * fx;
                data[out + k] = top * (1 - fy) + bottom * fy;
            }
        }
    }

    return { width, height, data };
}

/**
 * The tensor the model wants: the sheet stretched to 488 × 712, the three
 * colours one plane after the other, each value from 0 to 1.
 *
 * @param sheet - the cropped sheet
 * @returns `3 × 712 × 488` values
 */
export function prepareInput(sheet: Pixels): Float32Array {
    const stretched = resizePixels(sheet, MODEL_WIDTH, MODEL_HEIGHT);
    const size = MODEL_WIDTH * MODEL_HEIGHT;
    const input = new Float32Array(3 * size);

    for (let i = 0; i < size; i++) {
        input[i] = stretched.data[4 * i]! / 255;
        input[size + i] = stretched.data[4 * i + 1]! / 255;
        input[2 * size + i] = stretched.data[4 * i + 2]! / 255;
    }

    return input;
}

/**
 * The picture the model gives back, from its three planes of values from 0 to
 * 1 to opaque pixels. The clamped array rounds and keeps each value between 0
 * and 255 by itself.
 *
 * @param values - `3 × height × width` values, one plane after the other
 * @param width - the width of the output
 * @param height - the height of the output
 * @returns the flat sheet at the size of the output
 */
export function fromModelOutput(
    values: Float32Array,
    width: number,
    height: number,
): Pixels {
    const size = width * height;
    const data = new Uint8ClampedArray(4 * size);

    for (let i = 0; i < size; i++) {
        data[4 * i] = values[i]! * 255;
        data[4 * i + 1] = values[size + i]! * 255;
        data[4 * i + 2] = values[2 * size + i]! * 255;
        data[4 * i + 3] = 255;
    }

    return { width, height, data };
}

/**
 * Whether this browser can run *Raddrizza*: it needs WebAssembly and workers.
 *
 * @returns true if both exist
 */
export function isDewarpAvailable(): boolean {
    return typeof WebAssembly !== 'undefined' && typeof Worker !== 'undefined';
}

/**
 * The error that tells a call was stopped, like the one `fetch` gives.
 *
 * @param signal - the signal that was aborted
 * @returns the reason of the signal, or an `AbortError`
 */
function abortError(signal: AbortSignal): unknown {
    return signal.reason ?? new DOMException('Aborted', 'AbortError');
}

/**
 * Makes the two functions that talk to one worker. The worker starts at the
 * first call and stays for the next ones, with the model loaded.
 *
 * @param spawn - starts the worker (a fake one in the tests)
 * @returns `load()` to download the model and the engine ahead of time, and
 *   `dewarp()` to lay a sheet flat
 */
export function createDewarper(spawn: () => DewarpWorker): {
    load: (signal?: AbortSignal) => Promise<void>;
    dewarp: (sheet: Pixels, signal?: AbortSignal) => Promise<Pixels>;
} {
    type Waiting = {
        resolve: (answer: FromWorker) => void;
        reject: (error: Error) => void;
    };

    let worker: DewarpWorker | null = null;
    let next = 1;
    const waiting = new Map<number, Waiting>();

    /** The worker, started when it is first needed. */
    function start(): DewarpWorker {
        if (worker) {
            return worker;
        }

        worker = spawn();
        worker.addEventListener('message', ({ data }) => {
            const call = waiting.get(data.id);
            waiting.delete(data.id);

            if (!call) {
                return;
            }

            if (data.type === 'error') {
                call.reject(new Error(data.message));
            } else {
                call.resolve(data);
            }
        });

        return worker;
    }

    /**
     * Sends a message and waits for its answer. When the signal is aborted the
     * wait ends at once; the worker keeps going (it cannot be interrupted in
     * the middle of the model) and its late answer is dropped.
     */
    function ask(
        build: (id: number) => ToWorker,
        signal: AbortSignal | undefined,
        transfer: Transferable[] = [],
    ): Promise<FromWorker> {
        if (signal?.aborted) {
            return Promise.reject(abortError(signal));
        }

        const id = next++;
        const running = start();

        return new Promise<FromWorker>((resolve, reject) => {
            waiting.set(id, { resolve, reject });
            signal?.addEventListener(
                'abort',
                () => {
                    waiting.delete(id);
                    reject(abortError(signal));
                },
                { once: true },
            );
            running.postMessage(build(id), transfer);
        });
    }

    return {
        async load(signal) {
            await ask((id) => ({ id, type: 'load' }), signal);
        },
        async dewarp(sheet, signal) {
            // a copy: the sheet of the page stays as it is
            const copy = new Uint8ClampedArray(sheet.data);
            const answer = await ask(
                (id) => ({
                    id,
                    type: 'dewarp',
                    width: sheet.width,
                    height: sheet.height,
                    data: copy,
                }),
                signal,
                [copy.buffer],
            );

            if (answer.type !== 'done') {
                throw new Error('Unexpected answer from the worker');
            }

            return {
                width: answer.width,
                height: answer.height,
                data: answer.data,
            };
        },
    };
}

/** The real one, with the worker of this folder. */
const shared = createDewarper(
    () =>
        new Worker(new URL('./dewarp.worker.ts', import.meta.url), {
            type: 'module',
        }) as unknown as DewarpWorker,
);

/**
 * Downloads the model and loads the engine in the worker, so the first page is
 * not the one that waits for them. A call that finds them loaded is quick.
 *
 * @param signal - stops the wait
 * @throws if the model or the engine cannot be loaded
 */
export const loadDewarp = shared.load;

/**
 * Lays a cropped sheet flat.
 *
 * @param sheet - the cropped sheet
 * @param signal - stops the wait (the worker finishes the page it has, and its answer is dropped)
 * @returns the flat sheet, with the size of `sheet`
 * @throws if the work fails or the signal is aborted
 */
export const dewarp = shared.dewarp;
