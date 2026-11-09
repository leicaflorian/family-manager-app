import { http } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    CORNERS_TIMEOUT,
    ScanRequestError,
    enterAgain,
    findCorners,
    sendScanFile,
} from './server';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const request =
    vi.fn<
        (
            config: Config,
        ) => Promise<{
            status: number;
            data: string;
            headers: Record<string, string>;
        }>
    >();

/** An answer of the server. */
const answer = (status: number, body: unknown) => ({
    status,
    data: JSON.stringify(body),
    headers: {},
});

/** An error of the HTTP client of Inertia, with the answer of the server. */
const refusal = (status: number, body: unknown) =>
    Object.assign(new Error(`Request failed with status ${status}`), {
        response: answer(status, body),
    });

const CORNERS = [
    { x: 0.1, y: 0.1 },
    { x: 0.9, y: 0.1 },
    { x: 0.9, y: 0.9 },
    { x: 0.1, y: 0.9 },
];

beforeEach(() => {
    request.mockReset();
    http.setClient({ request } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.useRealTimers();
    vi.unstubAllGlobals();
    history.replaceState(null, '', '/');
});

describe('findCorners', () => {
    it('sends the JPEG as it is and gives the corners', async () => {
        request.mockResolvedValue(answer(200, { corners: CORNERS }));
        const jpeg = new Blob(['jpeg'], { type: 'image/jpeg' });

        expect(await findCorners(jpeg)).toEqual(CORNERS);

        const config = request.mock.calls[0][0];
        expect(config.method).toBe('post');
        expect(config.url).toMatch(/\/scan\/corners$/);
        expect(config.data).toBe(jpeg);
        expect(config.headers?.['Content-Type']).toBe('image/jpeg');
    });

    it('gives null when the server sees no sheet', async () => {
        request.mockResolvedValue(answer(200, { corners: null }));

        expect(await findCorners(new Blob(['j']))).toBeNull();
    });

    it('says the message of the server, with its status', async () => {
        request.mockRejectedValue(
            refusal(429, {
                message: 'The edges are set by hand for today',
            }),
        );

        await expect(findCorners(new Blob(['j']))).rejects.toMatchObject({
            status: 429,
            message: 'The edges are set by hand for today',
        });
    });

    it('stops waiting after 20 seconds', async () => {
        vi.useFakeTimers();
        request.mockImplementation(
            (config) =>
                new Promise((_resolve, reject) => {
                    config.signal?.addEventListener('abort', () =>
                        reject(new Error('cancelled')),
                    );
                }),
        );

        const search = findCorners(new Blob(['j']));
        const settled = expect(search).rejects.toMatchObject({
            status: 0,
            message: 'The search for the edges takes too long.',
        });
        await vi.advanceTimersByTimeAsync(CORNERS_TIMEOUT);

        await settled;
    });
});

describe('sendScanFile', () => {
    it('sends the file with the note, from the phone, and tells the progress', async () => {
        request.mockImplementation(async (config) => {
            config.onUploadProgress?.({
                progress: 0.5,
                percentage: 50,
                loaded: 5,
                total: 10,
            });

            return answer(200, {
                files: [{ id: 7, name: 'Telefono.pdf', size: 10 }],
            });
        });
        const progress = vi.fn();
        const file = new File(['pdf'], 'scan.pdf', {
            type: 'application/pdf',
        });

        expect(await sendScanFile(file, 'polizza casa', progress)).toEqual([
            { id: 7, name: 'Telefono.pdf', size: 10 },
        ]);

        const data = request.mock.calls[0][0].data as FormData;
        expect(data.get('files[]')).toBeInstanceOf(File);
        expect(data.get('note')).toBe('polizza casa');
        expect(data.get('source')).toBe('phone');
        expect(progress).toHaveBeenCalledWith(0.5);
    });

    it('leaves the note out when nothing is written', async () => {
        request.mockResolvedValue(answer(200, { files: [] }));

        await sendScanFile(new File(['x'], 'scan.jpg'), '');

        expect((request.mock.calls[0][0].data as FormData).has('note')).toBe(
            false,
        );
    });

    it('says the first reason of a refused file', async () => {
        request.mockRejectedValue(
            refusal(422, {
                message: 'The given data was invalid.',
                errors: { 'files.0': ['The file is too large.'] },
            }),
        );

        await expect(
            sendScanFile(new File(['x'], 'scan.jpg'), ''),
        ).rejects.toMatchObject({
            status: 422,
            message: 'The file is too large.',
        });
    });

    it('knows an ended session', async () => {
        request.mockRejectedValue(
            refusal(419, { message: 'CSRF token mismatch.' }),
        );

        const error = await sendScanFile(new File(['x'], 'scan.jpg'), '').catch(
            (caught: unknown) => caught,
        );

        expect(error).toBeInstanceOf(ScanRequestError);
        expect((error as ScanRequestError).sessionEnded).toBe(true);
    });
});

describe('enterAgain', () => {
    it('is in when the scan opens: the cookie of the phone entered again', async () => {
        const fetch = vi.fn(async () => ({
            ok: true,
            url: `${location.origin}/scan`,
        }));
        vi.stubGlobal('fetch', fetch);

        expect(await enterAgain()).toBe(true);
        expect(request).not.toHaveBeenCalled();
    });

    it('sends the secret after the # when the scan sends to the sign-in', async () => {
        history.replaceState(null, '', '/scan#d=the-secret');
        const fetch = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                url: `${location.origin}/login`,
            })
            .mockResolvedValueOnce({
                ok: true,
                url: `${location.origin}/scan`,
            });
        vi.stubGlobal('fetch', fetch);
        request.mockResolvedValue(answer(200, {}));

        expect(await enterAgain()).toBe(true);
        expect(request.mock.calls[0][0].url).toMatch(/\/connect\/resume$/);
        expect(request.mock.calls[0][0].data).toEqual({ secret: 'the-secret' });
    });

    it('is not in without the cookie and without the secret', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({ ok: true, url: `${location.origin}/login` })),
        );

        expect(await enterAgain()).toBe(false);
    });
});
