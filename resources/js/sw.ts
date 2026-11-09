/**
 * The service worker of the app, served at `/sw.js` (scope `/`). It does two things: it takes the files of *Share* on
 * Android and opens the *Scan* page with them, and it shows the offline page when a page does not open for lack of
 * network. It keeps no page of the app. The logic is in `lib/pwa/worker.ts`; here are only the events.
 */
import {
    OFFLINE_CACHE,
    answerNavigation,
    isShareRequest,
    precacheOffline,
    receiveShare,
} from '@/lib/pwa/worker';
import { SHARED_CACHE } from '@/lib/pwa/sharedFiles';

/** The part of a service worker event the worker uses (the DOM library has no worker types). */
type WorkerEvent = Event & { waitUntil: (work: Promise<unknown>) => void };
type WorkerFetchEvent = WorkerEvent & {
    request: Request;
    respondWith: (response: Promise<Response>) => void;
};
type WorkerScope = {
    location: Location;
    skipWaiting: () => Promise<void>;
    clients: { claim: () => Promise<void> };
    addEventListener: (type: string, listener: (event: never) => void) => void;
};

const worker = self as unknown as WorkerScope;

worker.addEventListener('install', (event: WorkerEvent) => {
    event.waitUntil(precacheOffline(caches).then(() => worker.skipWaiting()));
});

worker.addEventListener('activate', (event: WorkerEvent) => {
    event.waitUntil(
        (async () => {
            for (const name of await caches.keys()) {
                // the offline page and the files of Share are the only caches: any other one is old
                if (name !== OFFLINE_CACHE && name !== SHARED_CACHE)
                    await caches.delete(name);
            }

            await worker.clients.claim();
        })(),
    );
});

worker.addEventListener('fetch', (event: WorkerFetchEvent) => {
    const origin = worker.location.origin;

    if (isShareRequest(event.request, origin)) {
        event.respondWith(receiveShare(event.request, caches, origin));

        return;
    }

    if (event.request.mode === 'navigate' && event.request.method === 'GET') {
        event.respondWith(answerNavigation(event.request, fetch, caches));
    }
});
