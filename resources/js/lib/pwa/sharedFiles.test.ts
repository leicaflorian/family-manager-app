import { describe, expect, it } from 'vite-plus/test';
import { fakeCacheStorage } from '@/test/cacheStorage';
import {
    forgetShared,
    SHARED_CACHE,
    storeShared,
    takeShared,
} from './sharedFiles';

/** A file as the phone gives it. */
function file(name: string, type: string, text: string): File {
    return new File([text], name, { type });
}

describe('sharedFiles', () => {
    it('keeps the shared files in the fm-shared cache, with their name and type', async () => {
        const { storage, fake } = fakeCacheStorage();

        await storeShared(storage, [
            file('pagella Giulia.pdf', 'application/pdf', 'pdf'),
            file('foto.jpg', 'image/jpeg', 'jpg'),
        ]);

        expect(SHARED_CACHE).toBe('fm-shared');
        const kept = [...(fake.peek('fm-shared')?.entries.values() ?? [])];
        expect(kept).toHaveLength(2);
        expect(kept[0].headers.get('Content-Type')).toBe('application/pdf');
        expect(
            decodeURIComponent(kept[0].headers.get('X-File-Name') ?? ''),
        ).toBe('pagella Giulia.pdf');
    });

    it('gives the files back in the order they came and empties the cache', async () => {
        const { storage, fake } = fakeCacheStorage();
        await storeShared(storage, [
            file('uno.pdf', 'application/pdf', 'one'),
            file('due.png', 'image/png', 'two'),
        ]);

        const taken = await takeShared(storage);

        expect(taken.map((one) => one.name)).toEqual(['uno.pdf', 'due.png']);
        expect(taken.map((one) => one.type)).toEqual([
            'application/pdf',
            'image/png',
        ]);
        expect(await taken[0].text()).toBe('one');
        expect(await fake.has('fm-shared')).toBe(false);
    });

    it('gives nothing, and creates nothing, when nothing was shared', async () => {
        const { storage, fake } = fakeCacheStorage();

        expect(await takeShared(storage)).toEqual([]);
        expect(await fake.has('fm-shared')).toBe(false);
    });

    it('gives nothing when the browser has no cache storage', async () => {
        expect(await takeShared(undefined)).toEqual([]);
    });

    it('skips what is not a file', async () => {
        const { storage } = fakeCacheStorage();
        await storeShared(storage, [
            file('uno.pdf', 'application/pdf', 'one'),
            'text' as unknown as File,
        ]);

        expect(await takeShared(storage)).toHaveLength(1);
    });

    it('forgets the files left in the cache, and has nothing to do without a cache storage', async () => {
        const { storage, fake } = fakeCacheStorage();
        await storeShared(storage, [file('a.pdf', 'application/pdf', 'pdf')]);

        await forgetShared(storage);

        expect(fake.peek(SHARED_CACHE)).toBeUndefined();
        expect(await takeShared(storage)).toEqual([]);
        await expect(forgetShared(undefined)).resolves.toBeUndefined();
    });
});
