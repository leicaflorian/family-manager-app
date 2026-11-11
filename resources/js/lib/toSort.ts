import { toSort } from '@/routes';

/** A filter of *To sort* as the server sends it. */
export type ToSortFilter = App.Data.ToSortPageData['filters'][number];

/** A filter of *To sort* as a link: the chips of the phone and the tablet, the box *Where they come from* of the computer. */
export type FilterLink = {
    /** The value of the filter, `null` for *All*. */
    value: string | null;
    /** The name the server gives it. */
    label: string;
    /** How many files it has. */
    count: number;
    /** The address of the page with the filter. */
    href: string;
    /** It is the filter chosen. */
    current: boolean;
    /** It has no file, and it is not *All*: it cannot be chosen. */
    disabled: boolean;
};

/**
 * The filters of *To sort* as links, with the address of each, the one chosen
 * and the ones that cannot be chosen.
 *
 * @param filters - the filters in order, as the server sends them
 * @param source - the filter chosen, `null` for *All*
 * @returns the links, in the same order
 */
export function filterLinks(
    filters: readonly ToSortFilter[],
    source: App.Enums.FileSource | null,
): FilterLink[] {
    return filters.map((filter) => ({
        value: filter.value,
        label: filter.label,
        count: filter.count,
        href:
            filter.value === null
                ? toSort().url
                : toSort({ query: { source: filter.value } }).url,
        current: source === filter.value,
        disabled: filter.count === 0 && filter.value !== null,
    }));
}
