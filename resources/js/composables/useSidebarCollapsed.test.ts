import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { nextTick } from 'vue';
import { useSidebarCollapsed } from './useSidebarCollapsed';

describe('useSidebarCollapsed', () => {
    afterEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('starts open, and remembers in this browser that it was collapsed', async () => {
        const collapsed = useSidebarCollapsed();
        expect(collapsed.value).toBe(false);

        collapsed.value = true;
        await nextTick();

        expect(useSidebarCollapsed().value).toBe(true);
    });

    it('starts open when the browser keeps no storage', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });

        expect(useSidebarCollapsed().value).toBe(false);
    });
});
