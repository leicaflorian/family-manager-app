/**
 * What the scan asks the server: the corners of the sheet in a photo (`POST scan/corners`), the upload of a file
 * to *To sort* (`POST scan`) and, when the session has ended, entering again with the linked phone. The requests go
 * through the HTTP client of Inertia, which sends the token against forged requests and tells the progress of an
 * upload; the tests give it a fake client.
 */
import { http } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { secretFromHash } from '@/lib/phoneLink';
import { scan } from '@/routes';
import { resume } from '@/routes/connect';
import { corners as cornersRoute, store } from '@/routes/scan';
import type { Corners } from './crop';

/** How long the page waits for the corners before leaving the handles to the person, in milliseconds. */
export const CORNERS_TIMEOUT = 20000;

/** A request of the scan that did not succeed: the status (0 without an answer) and what to tell the person. */
export class ScanRequestError extends Error {
    /**
     * @param status - the HTTP status, 0 when the server did not answer
     * @param message - the reason, for the person
     */
    constructor(
        readonly status: number,
        message: string,
    ) {
        super(message);
        this.name = 'ScanRequestError';
    }

    /** The session is over (401) or its token is old (419): entering again and retrying can fix it. */
    get sessionEnded(): boolean {
        return this.status === 401 || this.status === 419;
    }
}

/** A file saved in *To sort*, as `POST scan` answers it. */
export type SavedFile = { id: number; name: string; size: number };

/**
 * The status and the body of an error of the HTTP client of Inertia, whatever its kind.
 *
 * @param error - what the client threw
 * @returns the status (0 without an answer) and the body read as JSON when it is
 */
function answerOf(error: unknown): {
    status: number;
    body: Record<string, unknown> | null;
} {
    const response = (
        error as { response?: { status?: number; data?: unknown } } | null
    )?.response;
    const status = typeof response?.status === 'number' ? response.status : 0;

    return { status, body: jsonOf(response?.data) };
}

/**
 * A body read as a JSON object.
 *
 * @param data - the body, as text or already read
 * @returns the object, or null when it is not one
 */
function jsonOf(data: unknown): Record<string, unknown> | null {
    if (data !== null && typeof data === 'object') {
        return data as Record<string, unknown>;
    }

    if (typeof data !== 'string') {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse(data);

        return parsed !== null && typeof parsed === 'object'
            ? (parsed as Record<string, unknown>)
            : null;
    } catch {
        return null;
    }
}

/**
 * The message the server wrote in its answer, if it wrote one.
 *
 * @param body - the answer read as JSON
 * @returns the message, or null
 */
function messageOf(body: Record<string, unknown> | null): string | null {
    return typeof body?.message === 'string' && body.message !== ''
        ? body.message
        : null;
}

/**
 * Whether a value is four points from 0 to 1.
 *
 * @param value - the `corners` of the answer
 * @returns true for four points
 */
function isCorners(value: unknown): value is Corners {
    return (
        Array.isArray(value) &&
        value.length === 4 &&
        value.every(
            (point: unknown) =>
                typeof (point as { x?: unknown } | null)?.x === 'number' &&
                typeof (point as { y?: unknown }).y === 'number',
        )
    );
}

/**
 * Asks the server where the sheet is in a photo. The page stops waiting after `CORNERS_TIMEOUT`.
 *
 * @param jpeg - a small copy of the photo, as it was taken (1000 px on the long side)
 * @param timeout - how long to wait, in milliseconds
 * @returns the four corners from 0 to 1, clockwise from the top left one, or null when the server sees no sheet
 * @throws ScanRequestError when the server says no, does not answer or takes too long
 */
export async function findCorners(
    jpeg: Blob,
    timeout = CORNERS_TIMEOUT,
): Promise<Corners | null> {
    const giveUp = new AbortController();
    const timer = setTimeout(() => giveUp.abort(), timeout);

    try {
        const response = await http.getClient().request({
            method: 'post',
            url: cornersRoute().url,
            data: jpeg,
            headers: {
                'Content-Type': 'image/jpeg',
                Accept: 'application/json',
            },
            signal: giveUp.signal,
        });
        const found = jsonOf(response.data)?.corners;

        return isCorners(found) ? found : null;
    } catch (error) {
        if (giveUp.signal.aborted) {
            throw new ScanRequestError(
                0,
                trans('The search for the edges takes too long.'),
            );
        }

        const { status, body } = answerOf(error);

        throw new ScanRequestError(
            status,
            messageOf(body) ??
                (status === 0
                    ? trans(
                          'Without a network the edges of the sheet cannot be looked for.',
                      )
                    : trans('The edges of the sheet could not be looked for.')),
        );
    } finally {
        clearTimeout(timer);
    }
}

/**
 * Sends one file of the document to *To sort*, with what it is.
 *
 * @param file - the JPEG, the PDF of the photos, or a PDF chosen on the phone
 * @param note - *What it is*, empty for nothing
 * @param onProgress - told how much has left the phone, from 0 to 1
 * @returns the files the server saved
 * @throws ScanRequestError when the file did not arrive
 */
export async function sendScanFile(
    file: File,
    note: string,
    onProgress: (fraction: number) => void = (): void => undefined,
): Promise<SavedFile[]> {
    const data = new FormData();
    data.append('files[]', file, file.name);
    data.append('source', 'phone');

    if (note !== '') {
        data.append('note', note);
    }

    try {
        const response = await http.getClient().request({
            method: 'post',
            url: store().url,
            data,
            headers: { Accept: 'application/json' },
            onUploadProgress: (event) => {
                if (event.progress !== undefined) {
                    onProgress(event.progress);
                }
            },
        });
        const files = jsonOf(response.data)?.files;

        return Array.isArray(files) ? (files as SavedFile[]) : [];
    } catch (error) {
        const { status, body } = answerOf(error);
        const errors = body?.errors as Record<string, string[]> | undefined;
        const first = errors ? Object.values(errors)[0]?.[0] : undefined;

        throw new ScanRequestError(
            status,
            first ??
                messageOf(body) ??
                (status === 0
                    ? trans(
                          'The upload did not go through: there is no network, or the server does not answer.',
                      )
                    : trans('The upload did not go through.')),
        );
    }
}

/**
 * Whether the session is open, opening the scan page: a linked phone whose session has ended enters again there
 * with its cookie, and the answer brings a new token against forged requests.
 *
 * @returns true when the scan opened, false when the server sent to the sign-in
 */
async function sessionOpen(): Promise<boolean> {
    try {
        const response = await fetch(scan().url, {
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
        });

        return (
            response.ok &&
            new URL(response.url, location.href).pathname ===
                new URL(scan().url, location.href).pathname
        );
    } catch {
        return false;
    }
}

/**
 * Enters again after the session has ended (a 401 or a 419 during an upload): first with the cookie of the linked
 * phone, then with the secret kept after the `#` of the address (the icon of the iPhone). Nothing is lost: the
 * pages stay in the page.
 *
 * @returns true when the phone is in again and the upload can be retried
 */
export async function enterAgain(): Promise<boolean> {
    if (await sessionOpen()) {
        return true;
    }

    const secret = secretFromHash(location.hash);

    if (secret === null) {
        return false;
    }

    try {
        await http.getClient().request({
            method: 'post',
            url: resume().url,
            data: { secret },
            headers: { Accept: 'text/html' },
        });
    } catch {
        return false;
    }

    return sessionOpen();
}
