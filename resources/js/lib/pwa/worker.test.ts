import { describe, expect, it } from 'vite-plus/test';
import { fakeCacheStorage } from '@/test/cacheStorage';
import {
    OFFLINE_CACHE,
    OFFLINE_PAGE,
    answerNavigation,
    isShareRequest,
    precacheOffline,
    receiveShare,
} from './worker';

const ORIGIN = 'https://family.test';

/** A request as the service worker sees it. */
function request(
    method: string,
    path: string,
    mode: RequestMode = 'cors',
): Pick<Request, 'method' | 'url' | 'mode'> {
    return { method, url: `${ORIGIN}${path}`, mode };
}

describe('isShareRequest', () => {
    it('is true for the POST of Share on /scan/share', () => {
        expect(isShareRequest(request('POST', '/scan/share'), ORIGIN)).toBe(
            true,
        );
    });

    it('is false for a GET, another path or another site', () => {
        expect(isShareRequest(request('GET', '/scan/share'), ORIGIN)).toBe(
            false,
        );
        expect(isShareRequest(request('POST', '/scan'), ORIGIN)).toBe(false);
        expect(
            isShareRequest(
                { method: 'POST', url: 'https://other.test/scan/share' },
                ORIGIN,
            ),
        ).toBe(false);
    });
});

describe('receiveShare', () => {
    it('puts the files in fm-shared and goes to /scan?shared=1 with a 303', async () => {
        const { storage, fake } = fakeCacheStorage();
        const body = new FormData();
        body.append(
            'files',
            new File(['pdf'], 'a.pdf', { type: 'application/pdf' }),
        );
        body.append('title', 'not a file');

        const response = await receiveShare(
            { formData: async () => body },
            storage,
            ORIGIN,
        );

        expect(response.status).toBe(303);
        expect(response.headers.get('Location')).toBe(
            `${ORIGIN}/scan?shared=1`,
        );
        expect(fake.peek('fm-shared')?.entries.size).toBe(1);
    });

    it('opens the page anyway, with nothing in it, when the body cannot be read', async () => {
        const { storage, fake } = fakeCacheStorage();

        const response = await receiveShare(
            {
                formData: async () => {
                    throw new TypeError('bad body');
                },
            },
            storage,
            ORIGIN,
        );

        expect(response.status).toBe(303);
        expect(response.headers.get('Location')).toBe(
            `${ORIGIN}/scan?shared=1`,
        );
        expect(await fake.has('fm-shared')).toBe(false);
    });
});

describe('answerNavigation', () => {
    it('gives what the network answers, and keeps nothing', async () => {
        const { storage, fake } = fakeCacheStorage();
        const network = new Response('the page');

        const response = await answerNavigation(
            request('GET', '/dashboard', 'navigate'),
            async () => network,
            storage,
        );

        expect(response).toBe(network);
        expect(fake.caches.size).toBe(0);
    });

    it('shows the offline page when the network does not answer', async () => {
        const { storage } = fakeCacheStorage();
        await precacheOffline(storage);

        const response = await answerNavigation(
            request('GET', '/dashboard', 'navigate'),
            async () => {
                throw new TypeError('Failed to fetch');
            },
            storage,
        );

        expect(await response.text()).toContain(OFFLINE_PAGE);
    });

    it('lets a server error through: only a missing network is offline', async () => {
        const { storage } = fakeCacheStorage();
        const broken = new Response('oops', { status: 500 });

        expect(
            await answerNavigation(
                request('GET', '/dashboard', 'navigate'),
                async () => broken,
                storage,
            ),
        ).toBe(broken);
    });

    it('falls back to a plain 503 when the offline page was never kept', async () => {
        const { storage } = fakeCacheStorage();

        const response = await answerNavigation(
            request('GET', '/dashboard', 'navigate'),
            async () => {
                throw new TypeError('Failed to fetch');
            },
            storage,
        );

        expect(response.status).toBe(503);
    });
});

describe('precacheOffline', () => {
    it('keeps only the offline page, in its own cache', async () => {
        const { storage, fake } = fakeCacheStorage();

        await precacheOffline(storage);

        expect([...(fake.peek(OFFLINE_CACHE)?.entries.keys() ?? [])]).toEqual([
            OFFLINE_PAGE,
        ]);
        expect(OFFLINE_PAGE).toBe('/offline.html');
    });
});
