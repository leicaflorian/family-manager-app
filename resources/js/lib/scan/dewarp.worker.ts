/**
 * The Web Worker of *Raddrizza* (see `dewarp.ts`). It loads the engine
 * (`onnxruntime-web`, the build of WebAssembly alone) and the model
 * (`/models/UVDoc.onnx`) the first time it is asked, keeps them, and lays one
 * sheet at a time flat. The engine comes with an `import()` so it is a piece of
 * its own in the build and never in the bundle of a page.
 */
import {
    MODEL_HEIGHT,
    MODEL_WIDTH,
    fromModelOutput,
    prepareInput,
    resizePixels,
    type FromWorker,
    type ToWorker,
} from './dewarp';

/** `self` in a worker: the DOM types of the project describe a window, whose `postMessage` is not this one. */
const scope = self as unknown as {
    postMessage(message: FromWorker, transfer?: Transferable[]): void;
};

type Engine = Awaited<ReturnType<typeof loadEngine>>;

/** The address of the model, served as it is from `public/`. */
const MODEL_URL = '/models/UVDoc.onnx';

let engine: Promise<Engine> | null = null;

/**
 * Loads the engine and the model.
 *
 * @returns the `onnxruntime-web` module and the session of the model
 * @throws if a download fails
 */
async function loadEngine() {
    const [ort, wasm] = await Promise.all([
        import('onnxruntime-web/wasm'),
        // the address Vite gives to the file, hashed in the build: no copy by hand
        import('onnxruntime-web/ort-wasm-simd-threaded.wasm?url'),
    ]);

    ort.env.wasm.wasmPaths = { wasm: wasm.default };
    // More threads need SharedArrayBuffer, which needs a page served with https and isolated from other origins: not the case on the phone
    ort.env.wasm.numThreads = self.crossOriginIsolated
        ? Math.min(4, navigator.hardwareConcurrency || 1)
        : 1;

    const response = await fetch(MODEL_URL);

    if (!response.ok) {
        throw new Error(
            `The model could not be downloaded (${response.status})`,
        );
    }

    const session = await ort.InferenceSession.create(
        new Uint8Array(await response.arrayBuffer()),
        { executionProviders: ['wasm'] },
    );

    return { ort, session };
}

/**
 * The engine, loaded once. A failed load is forgotten so the next call tries again.
 */
function getEngine(): Promise<Engine> {
    engine ??= loadEngine().catch((error: unknown) => {
        engine = null;
        throw error;
    });

    return engine;
}

/**
 * Lays a sheet flat.
 *
 * @param width - width of the sheet
 * @param height - height of the sheet
 * @param data - the RGBA pixels of the sheet
 * @returns the flat sheet at the size of the one received
 */
async function flatten(width: number, height: number, data: Uint8ClampedArray) {
    const { ort, session } = await getEngine();
    const input = prepareInput({ width, height, data });
    const result = await session.run({
        [session.inputNames[0]!]: new ort.Tensor('float32', input, [
            1,
            3,
            MODEL_HEIGHT,
            MODEL_WIDTH,
        ]),
    });
    const output = result[session.outputNames[0]!]!;
    const flat = fromModelOutput(
        output.data as Float32Array,
        Number(output.dims[3]),
        Number(output.dims[2]),
    );

    // back to the size of the sheet, so what comes after works as before
    return resizePixels(flat, width, height);
}

/** Pages are done one at a time: two together would take twice the memory and no less time. */
let last: Promise<void> = Promise.resolve();

self.addEventListener('message', (event: MessageEvent<ToWorker>) => {
    const message = event.data;

    last = last.then(async () => {
        try {
            if (message.type === 'load') {
                await getEngine();
                scope.postMessage({ id: message.id, type: 'loaded' });

                return;
            }

            const flat = await flatten(
                message.width,
                message.height,
                message.data,
            );
            scope.postMessage(
                {
                    id: message.id,
                    type: 'done',
                    width: flat.width,
                    height: flat.height,
                    data: flat.data,
                },
                [flat.data.buffer],
            );
        } catch (error) {
            scope.postMessage({
                id: message.id,
                type: 'error',
                message: error instanceof Error ? error.message : String(error),
            });
        }
    });
});
