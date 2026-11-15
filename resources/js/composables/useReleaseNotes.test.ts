import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useReleaseNotes } from './useReleaseNotes';

const shared = vi.hoisted(() => ({
    release: '1.2.0' as string | undefined,
    release_notes: undefined as
        | { version: string; date: string; notes: string[] }[]
        | undefined,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { ...shared } }),
}));

afterEach(() => {
    vi.restoreAllMocks();
    shared.release = '1.2.0';
    shared.release_notes = undefined;
});

describe('useReleaseNotes', () => {
    it('gives the version of the shared props, dev when missing', () => {
        expect(useReleaseNotes().release.value).toBe('1.2.0');

        shared.release = undefined;
        expect(useReleaseNotes().release.value).toBe('dev');
    });

    it('gives the notes only when the server sent them', () => {
        expect(useReleaseNotes().notes.value).toBeUndefined();

        shared.release_notes = [
            { version: '1.2.0', date: '2026-11-15', notes: ['News'] },
        ];
        expect(useReleaseNotes().notes.value).toEqual([
            { version: '1.2.0', date: '2026-11-15', notes: ['News'] },
        ]);
    });

    it('loads only the notes, without changing the address', () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation(() => undefined);

        useReleaseNotes().loadNotes();

        expect(reload).toHaveBeenCalledWith({
            only: ['release_notes'],
            preserveUrl: true,
        });
    });
});
