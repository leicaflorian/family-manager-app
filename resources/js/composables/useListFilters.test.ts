import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { nextTick, ref } from 'vue';
import type { FilterPill } from '@/lib/filters';
import { screenWidth } from '@/test/page';
import { useListFilters } from './useListFilters';

const shared = vi.hoisted(() => ({
    errors: undefined as Record<string, string> | undefined,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { ...shared } }),
}));

type Filters = { search: string | null; min: number | null };

/** How the page visits itself with other filters. */
const visit = { preserveState: true, preserveScroll: true, replace: true };

/**
 * The filters of a list, with the address written as `search|min|page`.
 *
 * @param start - the filters in use
 * @returns the filters, the ref that holds them and the composable
 */
function setup(start: Filters = { search: 'luce', min: 20 }) {
    const filters = ref<Filters>(start);
    const pills = (): FilterPill<Filters>[] => [
        ...(filters.value.min === null
            ? []
            : [{ key: 'amount', label: 'At least', without: { min: null } }]),
        ...(filters.value.search === null
            ? []
            : [{ key: 'search', label: '“luce”', without: { search: null } }]),
    ];
    const list = useListFilters<Filters, { page: number }>({
        filters: () => filters.value,
        pills,
        url: (query) =>
            `/list?${query.search ?? ''}|${query.min ?? ''}|${query.page ?? ''}`,
    });

    return { filters, list };
}

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    shared.errors = undefined;
});

describe('useListFilters', () => {
    it('visits the address with the filters in use and what changes, keeping state and scroll', () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const { list } = setup();

        list.go({ page: 2 });

        expect(get).toHaveBeenCalledWith('/list?luce|20|2', {}, visit);
    });

    it('sends the text of the box trimmed, and only spaces as no search', () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const { list } = setup();

        list.searchText.value = '  gas ';
        list.submitSearch();
        list.searchText.value = '   ';
        list.submitSearch();

        expect(get.mock.calls.map((call) => call[0])).toEqual([
            '/list?gas|20|',
            '/list?|20|',
        ]);
    });

    it('starts the box from the search in use and follows it', async () => {
        const { filters, list } = setup();
        expect(list.searchText.value).toBe('luce');

        filters.value = { search: null, min: 20 };
        await nextTick();

        expect(list.searchText.value).toBe('');
    });

    it('removes the filter of a pill, and every filter at once', () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const { list } = setup();

        list.removePill('amount');
        list.removeAll();

        expect(get.mock.calls.map((call) => call[0])).toEqual([
            '/list?luce||',
            '/list?||',
        ]);
    });

    it('counts the filters on without the search, which is in the box', () => {
        expect(setup().list.filterCount.value).toBe(1);
        expect(
            setup({ search: 'luce', min: null }).list.filterCount.value,
        ).toBe(0);
    });

    it('gives what the server found wrong, nothing when it found nothing', () => {
        expect(setup().list.filterErrors.value).toEqual({});

        shared.errors = { max: 'Too small.' };
        expect(setup().list.filterErrors.value).toEqual({ max: 'Too small.' });
    });

    it('lays the filters beside the list on the computer and above it on the phone', () => {
        screenWidth(true);
        expect(setup().list.layout.value).toContain(
            'grid-cols-[max(240px,calc(240px+(100%-680px)/4))_minmax(0,1fr)]',
        );

        vi.unstubAllGlobals();
        screenWidth(390);
        expect(setup().list.layout.value).toContain('flex-col');
    });
});
