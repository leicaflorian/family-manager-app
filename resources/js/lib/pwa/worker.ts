/**
 * What the service worker does, as plain functions: `resources/js/sw.ts` only hooks them to the events. The worker
 * keeps nothing of the app: only the offline page, and the files of *Share* until the page takes them.
 */
import { storeShared } from './sharedFiles';

/** The only page the worker keeps, for a navigation that finds no network. */
export const OFFLINE_PAGE = '/offline.html';
/** Its cache. */
export const OFFLINE_CACHE = 'fm-offline';
/** The address of *Share* in the manifest (`share_target`). */
export const SHARE_PATH = '/scan/share';
/** Where *Share* ends: the *Scan* page, which finds the files. */
const SHARED_PAGE = '/scan?shared=1';

/**
 * Whether a request is the POST of *Share* from the system menu.
 *
 * @param request - the request
 * @param origin - the origin of the worker
 * @returns true for `POST /scan/share` of this site
 */
export function isShareRequest(
    request: Pick<Request, 'method' | 'url'>,
    origin: string,
): boolean {
    if (request.method !== 'POST') return false;

    const url = new URL(request.url);

    return url.origin === origin && url.pathname === SHARE_PATH;
}

/**
 * Takes the files of *Share*, puts them aside and sends the browser to the *Scan* page.
 *
 * @param request - the POST, from which only the form is read
 * @param storage - the cache storage
 * @param origin - the origin of the worker
 * @returns a 303 to `/scan?shared=1`, also when the files could not be read: the page opens with nothing in it
 */
export async function receiveShare(
    request: Pick<Request, 'formData'>,
    storage: CacheStorage,
    origin: string,
): Promise<Response> {
    try {
        await storeShared(storage, (await request.formData()).getAll('files'));
    } catch {
        // the page opens anyway, with nothing in it
    }

    return Response.redirect(new URL(SHARED_PAGE, origin).href, 303);
}

/**
 * Keeps the offline page when the worker is installed.
 *
 * @param storage - the cache storage
 */
export async function precacheOffline(storage: CacheStorage): Promise<void> {
    await (await storage.open(OFFLINE_CACHE)).add(OFFLINE_PAGE);
}

/**
 * Answers a navigation from the network; when the network does not answer, with the offline page. A page of the
 * app is never kept, and an answer of the server (also an error) is never replaced.
 *
 * @param request - the navigation
 * @param fetcher - how the network is asked (`fetch`)
 * @param storage - the cache storage
 * @returns the answer of the network, or the offline page, or a bare 503 if that was never kept
 */
export async function answerNavigation<R extends Pick<Request, 'url'>>(
    request: R,
    fetcher: (request: R) => Promise<Response>,
    storage: CacheStorage,
): Promise<Response> {
    try {
        return await fetcher(request);
    } catch {
        const cache = await storage.open(OFFLINE_CACHE);

        return (
            (await cache.match(OFFLINE_PAGE)) ??
            new Response('Offline', {
                status: 503,
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
            })
        );
    }
}
