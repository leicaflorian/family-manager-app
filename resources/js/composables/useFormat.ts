import { formatDate, formatEuro, formatMoney } from '@/lib/format';

/** The writing functions `useFormat` gives to the pages. */
export type UseFormat = {
    /** An amount like `84,20 €`; empty if missing. */
    euro: (amount: number | null | undefined) => string;
    /** An amount in any currency: euros as `84,20 €`, others with the code, `-120,00 USD`. */
    money: (amount: number, currency: string) => string;
    /** A date like `15/10/2026`; empty if missing. */
    date: (date: string | Date | null | undefined) => string;
};

/**
 * Amounts and dates as the app writes them, for the templates of the pages.
 * A missing value (a document without a date) becomes an empty string, so the
 * template does not have to check it every time.
 *
 * @returns the functions `euro` and `date`
 */
export function useFormat(): UseFormat {
    return {
        euro: (amount) =>
            amount === null || amount === undefined ? '' : formatEuro(amount),
        money: formatMoney,
        date: (date) =>
            date === null || date === undefined || date === ''
                ? ''
                : formatDate(date),
    };
}
