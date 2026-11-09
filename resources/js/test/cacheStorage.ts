/**
 * A `CacheStorage` kept in memory, for the tests of the service worker and of the page that reads what it keeps.
 * It stores the responses as they are put, which is all those tests need.
 */

/** The cache of the fake: the responses by the key they were put with. */
export class FakeCache {
    readonly entries = new Map<string, Response>();

    /**
     * Keeps a response.
     *
     * @param request - the key
     * @param response - the response
     */
    async put(request: RequestInfo | URL, response: Response): Promise<void> {
        this.entries.set(FakeCache.key(request), response);
    }

    /**
     * Keeps what the address answers, as `cache.add` does.
     *
     * @param request - the address
     */
    async add(request: RequestInfo | URL): Promise<void> {
        this.entries.set(
            FakeCache.key(request),
            new Response(`fetched ${FakeCache.key(request)}`),
        );
    }

    /**
     * Finds a response.
     *
     * @param request - the key
     * @returns the response, or undefined
     */
    async match(request: RequestInfo | URL): Promise<Response | undefined> {
        return this.entries.get(FakeCache.key(request));
    }

    /**
     * The keys, in the order they were put.
     *
     * @returns the requests
     */
    async keys(): Promise<Request[]> {
        return [...this.entries.keys()].map(
            (key) => ({ url: key }) as unknown as Request,
        );
    }

    /**
     * Forgets a response.
     *
     * @param request - the key
     * @returns whether there was one
     */
    async delete(request: RequestInfo | URL): Promise<boolean> {
        return this.entries.delete(FakeCache.key(request));
    }

    /**
     * The text a request or address is kept under.
     *
     * @param request - the request or address
     * @returns the key
     */
    private static key(request: RequestInfo | URL): string {
        return typeof request === 'string'
            ? request
            : request instanceof URL
              ? request.href
              : request.url;
    }
}

/** The fake storage: named caches opened on demand. */
export class FakeCacheStorage {
    readonly caches = new Map<string, FakeCache>();

    /**
     * Opens a cache, creating it when it is new.
     *
     * @param name - the name
     * @returns the cache
     */
    async open(name: string): Promise<Cache> {
        if (!this.caches.has(name)) this.caches.set(name, new FakeCache());

        return this.caches.get(name) as unknown as Cache;
    }

    /**
     * Forgets a whole cache.
     *
     * @param name - the name
     * @returns whether there was one
     */
    async delete(name: string): Promise<boolean> {
        return this.caches.delete(name);
    }

    /**
     * Whether a cache of that name exists.
     *
     * @param name - the name
     * @returns true when it does
     */
    async has(name: string): Promise<boolean> {
        return this.caches.has(name);
    }

    /**
     * The cache with that name as the test reads it.
     *
     * @param name - the name
     * @returns the fake cache, or undefined
     */
    peek(name: string): FakeCache | undefined {
        return this.caches.get(name);
    }
}

/**
 * A fake storage typed as the real one.
 *
 * @returns the storage, and the same object with the methods the test reads
 */
export function fakeCacheStorage(): {
    storage: CacheStorage;
    fake: FakeCacheStorage;
} {
    const fake = new FakeCacheStorage();

    return { storage: fake as unknown as CacheStorage, fake };
}
