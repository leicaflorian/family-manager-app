import { formatDate, formatEuro, formatRomeText } from '@/lib/format';

/** The writing functions `useFormat` gives to the pages. */
export type UseFormat = {
    /** An amount like `84,20 €`; empty if missing. */
    euro: (amount: number | null | undefined) => string;
    /** A date like `15/10/2026`; empty if missing. */
    date: (date: string | Date | null | undefined) => string;
    /** A day and time of Rome sent as `2026-12-10 21:30`, written `10/12/2026 21:30`; empty if missing. */
    dateTime: (moment: string | null | undefined) => string;
};

/**
 * Amounts and dates as the app writes them, for the templates of the pages.
 * A missing value (a document without a date) becomes an empty string, so the
 * template does not have to check it every time.
 *
 * @returns the functions `euro`, `date` and `dateTime`
 */
export function useFormat(): UseFormat {
    return {
        euro: (amount) =>
            amount === null || amount === undefined ? '' : formatEuro(amount),
        date: (date) =>
            date === null || date === undefined || date === ''
                ? ''
                : formatDate(date),
        dateTime: (moment) =>
            moment === null || moment === undefined || moment === ''
                ? ''
                : formatRomeText(moment),
    };
}
