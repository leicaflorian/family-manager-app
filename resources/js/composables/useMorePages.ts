/**
 * The next pages of lists a page shows a few rows at a time: each list has its
 * props (`<list>`, `<list>Next`, `<list>Total`) merged on the server, and a tap
 * on *Show more* asks only for those, with the number of the page, keeping the
 * address.
 *
 * @module
 */
import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref } from 'vue';

/**
 * Loads the next page of any of the given lists, below the rows already shown.
 *
 * @param lists - the names of the lists, as the props of the page call them
 * @returns `loading`, true for each list while its page arrives, and `loadMore`
 */
export function useMorePages<L extends string>(
    lists: readonly L[],
): {
    loading: Ref<Record<L, boolean>>;
    loadMore: (list: L, page: number) => void;
} {
    const loading = ref(
        Object.fromEntries(lists.map((list) => [list, false])),
    ) as Ref<Record<L, boolean>>;

    /**
     * Adds the next page of a list without reloading the rest of the page nor
     * changing the address.
     *
     * @param list - the list to extend
     * @param page - the number of the page to add
     */
    function loadMore(list: L, page: number): void {
        loading.value[list] = true;
        router.reload({
            data: { [`${list}_page`]: page },
            only: [list, `${list}Next`, `${list}Total`],
            preserveUrl: true,
            onFinish: () => {
                loading.value[list] = false;
            },
        });
    }

    return { loading, loadMore };
}
