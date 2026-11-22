import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useDocumentPin } from './useDocumentPin';

describe('useDocumentPin', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('pins an unpinned document with a PUT that keeps the scroll', () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});

        useDocumentPin().togglePin({ id: 5, pinned: false });

        expect(put).toHaveBeenCalledWith(
            '/documents/5/pin',
            {},
            { preserveScroll: true },
        );
    });

    it('unpins a pinned document with a DELETE that keeps the scroll', () => {
        const del = vi.spyOn(router, 'delete').mockImplementation(() => {});

        useDocumentPin().togglePin({ id: 5, pinned: true });

        expect(del).toHaveBeenCalledWith('/documents/5/pin', {
            preserveScroll: true,
        });
    });

    it('labels the item after the state', () => {
        const { pinLabel } = useDocumentPin();

        expect(pinLabel(false)).toBe('Mark as favourite');
        expect(pinLabel(true)).toBe('Remove from favourites');
    });
});
