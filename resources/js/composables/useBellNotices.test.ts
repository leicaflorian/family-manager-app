import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useBellNotices } from './useBellNotices';

const shared = vi.hoisted(() => ({
    unread_notices: 3 as number | undefined,
    bell_notices: undefined as
        | { unread: unknown[]; seen: unknown[] }
        | undefined,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { ...shared } }),
}));

afterEach(() => {
    vi.restoreAllMocks();
    shared.unread_notices = 3;
    shared.bell_notices = undefined;
});

describe('useBellNotices', () => {
    it('gives the unread count of the shared props, 0 when missing', () => {
        expect(useBellNotices().unread.value).toBe(3);

        shared.unread_notices = undefined;
        expect(useBellNotices().unread.value).toBe(0);
    });

    it('gives the notices only when the server sent them', () => {
        expect(useBellNotices().notices.value).toBeUndefined();

        shared.bell_notices = { unread: [], seen: [] };
        expect(useBellNotices().notices.value).toEqual({
            unread: [],
            seen: [],
        });
    });

    it('loads only the notices, without changing the address', () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation(() => undefined);

        useBellNotices().loadNotices();

        expect(reload).toHaveBeenCalledWith({
            only: ['bell_notices'],
            preserveUrl: true,
        });
    });
});
