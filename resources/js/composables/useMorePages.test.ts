import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useMorePages } from './useMorePages';

/** The options of the last `router.reload` call. */
type ReloadOptions = {
    data: Record<string, number>;
    only: string[];
    preserveUrl: boolean;
    onFinish: () => void;
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('useMorePages', () => {
    it('reloads only the props of the list, keeping the address', () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const { loadMore } = useMorePages(['personal', 'documents']);

        loadMore('personal', 2);

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                data: { personal_page: 2 },
                only: ['personal', 'personalNext', 'personalTotal'],
                preserveUrl: true,
            }),
        );
    });

    it('is loading until the visit finishes, each list on its own', () => {
        const calls: ReloadOptions[] = [];
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            calls.push(options as unknown as ReloadOptions);
        });
        const { loading, loadMore } = useMorePages(['personal', 'documents']);

        expect(loading.value).toEqual({ personal: false, documents: false });

        loadMore('personal', 2);
        loadMore('documents', 3);
        expect(loading.value).toEqual({ personal: true, documents: true });

        calls[0]!.onFinish();
        expect(loading.value).toEqual({ personal: false, documents: true });
    });
});
