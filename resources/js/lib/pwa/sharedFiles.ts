/**
 * The files sent with *Share* on Android. The service worker puts them in the `fm-shared` cache (it is the one that
 * receives the request), and the *Scan* page, opened with `?shared=1`, takes them from there. Both sides get the
 * `CacheStorage` as an argument, so the logic is tested without a browser.
 */

/** Where the shared files wait for the page. */
export const SHARED_CACHE = 'fm-shared';

/** The header that carries the name of the file, which a cache entry has no place for. */
const NAME_HEADER = 'X-File-Name';

/**
 * Puts the files aside for the page.
 *
 * @param storage - the cache storage of the browser
 * @param files - what came in the request: anything that is not a file is skipped
 */
export async function storeShared(
    storage: CacheStorage,
    files: readonly unknown[],
): Promise<void> {
    const cache = await storage.open(SHARED_CACHE);
    let index = 0;

    for (const file of files) {
        if (!(file instanceof File)) continue;

        // The bytes are read here: a Response built from the File itself is not accepted by every runtime
        await cache.put(
            `shared/${Date.now()}-${index++}`,
            new Response(await file.arrayBuffer(), {
                headers: {
                    'Content-Type': file.type || 'application/octet-stream',
                    [NAME_HEADER]: encodeURIComponent(file.name),
                },
            }),
        );
    }
}

/**
 * Takes the shared files for the page and empties the cache, so they are never added twice.
 *
 * @param storage - the cache storage of the browser; undefined where it has none
 * @returns the files, in the order they were shared
 */
export async function takeShared(
    storage: CacheStorage | undefined,
): Promise<File[]> {
    if (!storage || !(await storage.has(SHARED_CACHE))) return [];

    const cache = await storage.open(SHARED_CACHE);
    const files: File[] = [];

    for (const key of await cache.keys()) {
        const response = await cache.match(key);

        if (!response) continue;

        files.push(
            new File(
                [await response.blob()],
                decodeURIComponent(response.headers.get(NAME_HEADER) ?? ''),
                {
                    type:
                        response.headers.get('Content-Type') ??
                        'application/octet-stream',
                },
            ),
        );
    }

    await storage.delete(SHARED_CACHE);

    return files;
}

/**
 * Forgets the files shared and not taken. The cache belongs to the device, not to the account, so it is emptied
 * when someone signs out or in: the next person must not find a file of the one before in their scan.
 *
 * @param storage - the cache storage of the browser; undefined where it has none
 */
export async function forgetShared(
    storage: CacheStorage | undefined,
): Promise<void> {
    await storage?.delete(SHARED_CACHE);
}
