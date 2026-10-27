/**
 * How the app writes amounts and dates: `84,20 €` and `15/10/2026`.
 * The pages do not write them by hand: they use these functions, usually
 * through the `useFormat` composable.
 */
import { format } from '@formkit/tempo';

const euro = new Intl.NumberFormat('it-IT', {
    style: 'currency',
    currency: 'EUR',
});

/**
 * Writes an amount in euros, the Italian way.
 *
 * @param amount - the amount in euros, for example `1234.5`
 * @returns the amount as text, for example `1.234,50 €`
 */
export function formatEuro(amount: number): string {
    // it-IT does not group thousands below 10,000: the grouping is forced.
    // Non-breaking spaces become normal spaces, so the texts can be compared.
    return euro
        .format(amount)
        .replace(/^(-?\d)(\d{3}),/, '$1.$2,')
        .replace(/\s/g, ' ');
}

/**
 * Turns a date of the server into a local `Date`.
 *
 * A date without time (`2026-10-15`) must be read as a calendar day: with
 * `new Date('2026-10-15')` it would be midnight UTC, which in some time zones is
 * the day before. With a time only the date part is taken, the one written by
 * the server in the time zone of Rome.
 *
 * @param date - `yyyy-mm-dd`, `yyyy-mm-ddThh:mm:ss…` or a `Date`
 * @returns the date at midnight, in local time
 */
export function toDate(date: string | Date): Date {
    if (date instanceof Date) {
        return date;
    }

    const [year, month, day] = date.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day);
}

/**
 * Writes a date as dd/mm/yyyy.
 *
 * @param date - a date of the server or a `Date`
 * @returns the date as text, for example `15/10/2026`
 */
export function formatDate(date: string | Date): string {
    return format(toDate(date), 'DD/MM/YYYY', 'it');
}

/**
 * Writes the day of a date as the server does, in local time.
 *
 * @param date - the date, today if missing
 * @returns for example `2026-10-27`
 */
export function isoDate(date: Date = new Date()): string {
    return format(date, 'YYYY-MM-DD');
}
