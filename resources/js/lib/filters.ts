/**
 * The filters of a list with a search box, a range of amounts and a range of
 * days (*Movements*, *Planned expenses*): the pills that say which are on, and
 * the filters all taken away.
 */
import { trans } from 'laravel-vue-i18n';
import { formatDate, formatEuro } from './format';

/** The search and the ranges that the lists share. */
export type RangeFilters = {
    /** A part of the title. */
    search: string | null;
    /** The smallest amount. */
    min: number | null;
    /** The largest amount. */
    max: number | null;
    /** The first day, `yyyy-mm-dd`. */
    from: string | null;
    /** The last day, `yyyy-mm-dd`. */
    to: string | null;
};

/** The pill of a filter on, with what removing it leaves. */
export type FilterPill<T> = {
    /** Which filter it is. */
    key: string;
    /** What the pill says. */
    label: string;
    /** The filters that its × takes away. */
    without: Partial<T>;
};

/**
 * The text of the pill of the days.
 *
 * @param from - the first day, or null
 * @param to - the last day, or null
 * @param dates - `days` for the day of a movement, `next` for the next due date
 * @returns for example «From 01/09/2026 to 31/10/2026»
 */
function daysLabel(
    from: string | null,
    to: string | null,
    dates: 'days' | 'next',
): string {
    if (from !== null && to !== null) {
        const params = { from: formatDate(from), to: formatDate(to) };

        return dates === 'next'
            ? trans('Next from :from to :to', params)
            : trans('From :from to :to', params);
    }

    if (from !== null) {
        const params = { date: formatDate(from) };

        return dates === 'next'
            ? trans('Next from :date', params)
            : trans('From :date', params);
    }

    const params = { date: formatDate(to ?? '') };

    return dates === 'next'
        ? trans('Next until :date', params)
        : trans('Until :date', params);
}

/**
 * The pills of the amounts, the days and the text, in this order, for the
 * ones that are on.
 *
 * @param filters - the filters in use
 * @param dates - what the days are: `days` (the day of a movement, the default) or `next` (the next due date)
 * @returns the pills
 */
export function rangePills(
    filters: RangeFilters,
    dates: 'days' | 'next' = 'days',
): FilterPill<RangeFilters>[] {
    const { search, min, max, from, to } = filters;
    const pills: FilterPill<RangeFilters>[] = [];

    if (min !== null || max !== null) {
        pills.push({
            key: 'amount',
            label:
                min !== null && max !== null
                    ? trans('From :min to :max', {
                          min: formatEuro(min),
                          max: formatEuro(max),
                      })
                    : min !== null
                      ? trans('At least :amount', { amount: formatEuro(min) })
                      : trans('Up to :amount', {
                            amount: formatEuro(max ?? 0),
                        }),
            without: { min: null, max: null },
        });
    }

    if (from !== null || to !== null) {
        pills.push({
            key: 'days',
            label: daysLabel(from, to, dates),
            without: { from: null, to: null },
        });
    }

    if (search !== null) {
        pills.push({
            key: 'search',
            label: `“${search}”`,
            without: { search: null },
        });
    }

    return pills;
}

/**
 * The filters with every one taken away, the search too.
 *
 * @param filters - the filters in use
 * @returns the same filters, all null
 */
export function withoutFilters<T extends object>(
    filters: T,
): { [K in keyof T]: null } {
    return Object.fromEntries(
        Object.keys(filters).map((key) => [key, null]),
    ) as { [K in keyof T]: null };
}

/**
 * Whether any filter is on, the search included.
 *
 * @param filters - the filters in use
 * @returns true when one of them has a value
 */
export function anyFilterOn(filters: object): boolean {
    return Object.values(filters).some((value) => value !== null);
}
