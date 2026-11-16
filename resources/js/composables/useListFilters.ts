import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import { useComputerLayout } from '@/composables/useLayoutMode';
import type { FilterPill } from '@/lib/filters';
import { withoutFilters } from '@/lib/filters';
import { cn } from '@/lib/utils';

/** What a list with a search box and filters gives its page. */
export type ListFilters<F, E> = {
    /** The computer layout: the filters in a column, else in the panel from below. */
    desktop: Ref<boolean>;
    /** The text in the search box, sent with Enter. */
    searchText: Ref<string>;
    /** Sends the text of the box; only spaces is no search. */
    submitSearch: () => void;
    /** Opens the page with some filters (or other values of the address) changed, keeping the rest and the scroll. */
    go: (change?: Partial<F & E>) => void;
    /** Takes away the filter of a pill, keeping the others. */
    removePill: (key: string) => void;
    /** Takes every filter away, the search too. */
    removeAll: () => void;
    /** How many filters the button of the phone counts: the search is in the box, not among them. */
    filterCount: ComputedRef<number>;
    /** What the server found wrong in the filters (an amount *to* under the *from*). */
    filterErrors: ComputedRef<Partial<Record<string, string>>>;
    /** The classes of the two columns on the computer, the filters first; one column on the phone. */
    layout: ComputedRef<string>;
};

/**
 * The search box and the filters of a list page (*Movements*, *Planned
 * expenses*): the text of the box, the visits with other filters, the pills
 * that take one away, the count of the filters on and the layout of the
 * column of the filters.
 *
 * @param options.filters - the filters in use, as the server sends them
 * @param options.pills - the pills of the filters on, each with what removing it leaves
 * @param options.url - the address of the page for the filters (and the other values of the address) given
 * @returns the state and the actions of the filters
 */
export function useListFilters<
    F extends { search: string | null },
    E extends object = object,
>(options: {
    filters: () => F;
    pills: () => FilterPill<F>[];
    url: (query: Partial<F & E>) => string;
}): ListFilters<F, E> {
    const desktop = useComputerLayout();
    const page = usePage();
    const searchText = ref(options.filters().search ?? '');

    watch(
        () => options.filters().search,
        (value) => (searchText.value = value ?? ''),
    );

    /**
     * Opens the page with other values, keeping the filters not changed and the scroll.
     *
     * @param change - what changes from the page in use
     */
    function go(change: Partial<F & E> = {}): void {
        router.get(
            options.url({ ...options.filters(), ...change }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return {
        desktop,
        searchText,
        submitSearch: (): void =>
            go({ search: searchText.value.trim() || null } as Partial<F & E>),
        go,
        removePill: (key: string): void =>
            go(
                (options.pills().find((pill) => pill.key === key)?.without ??
                    {}) as Partial<F & E>,
            ),
        removeAll: (): void =>
            go(withoutFilters(options.filters()) as Partial<F & E>),
        filterCount: computed(
            () =>
                options.pills().filter((pill) => pill.key !== 'search').length,
        ),
        filterErrors: computed(
            (): Partial<Record<string, string>> => page.props.errors ?? {},
        ),
        layout: computed(() =>
            desktop.value
                ? cn('grid grid-cols-[16rem_minmax(0,1fr)] items-start gap-5')
                : cn('flex flex-col gap-4'),
        ),
    };
}
