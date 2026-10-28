/**
 * How the app writes amounts and dates: `84,20 €` and `15/10/2026`.
 * The pages do not write them by hand: they use these functions, usually
 * through the `useFormat` composable.
 */
import { format } from '@formkit/tempo';
import { trans, transChoice } from 'laravel-vue-i18n';

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

/** Writes a size with one decimal at most, with the comma. */
const sizeNumber = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 });

/**
 * Writes the size of a file: bytes, kilobytes or megabytes, as the person reads it.
 *
 * @param bytes - the size in bytes
 * @returns the size as text, for example `340 KB` or `1,2 MB`
 */
export function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${sizeNumber.format(bytes / (1024 * 1024))} MB`;
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

/**
 * Writes a date with the name of the day, as at the top of the *Home*.
 *
 * @param date - a date of the server or a `Date`
 * @param locale - the language of the page, `it` or `en`
 * @returns for example «Lunedì 26 ottobre 2026»
 */
export function formatLongDate(date: string | Date, locale: string): string {
    const text = format(toDate(date), 'dddd D MMMM YYYY', locale);

    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Writes a day as `yyyy-mm-dd` in the time zone of Rome. */
const romeDay = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Rome',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

/**
 * Counts the calendar days from a day to another.
 *
 * @param from - the first day, `yyyy-mm-dd`
 * @param to - the second day, `yyyy-mm-dd`
 * @returns the days between them, negative if `to` comes first
 */
function daysBetween(from: string, to: string): number {
    const [fromYear, fromMonth, fromDay] = from.split('-').map(Number);
    const [toYear, toMonth, toDay] = to.split('-').map(Number);

    return Math.round(
        (Date.UTC(toYear, toMonth - 1, toDay) -
            Date.UTC(fromYear, fromMonth - 1, fromDay)) /
            86_400_000,
    );
}

/**
 * The whole days from today to a date, counted on the calendar of Rome.
 *
 * @param date - a date of the server
 * @param today - the moment taken as now, the clock if missing
 * @returns positive for a date ahead, negative for a date passed
 */
export function daysFromToday(date: string, today: Date = new Date()): number {
    return daysBetween(romeDay.format(today), date.slice(0, 10));
}

/**
 * Says how far a date is from today: «today», «tomorrow», «in 26 days»,
 * «yesterday», «3 days ago».
 *
 * @param date - a date of the server
 * @param today - the moment taken as now, the clock if missing
 * @returns the sentence, in the language of the page
 */
export function relativeDays(date: string, today: Date = new Date()): string {
    const days = daysFromToday(date, today);

    if (days === 0) {
        return trans('today');
    }

    if (days === 1) {
        return trans('tomorrow');
    }

    if (days === -1) {
        return trans('yesterday');
    }

    return days > 0
        ? transChoice('{1} in :count day|[2,*] in :count days', days, {
              count: String(days),
          })
        : transChoice('{1} :count day ago|[2,*] :count days ago', -days, {
              count: String(-days),
          });
}
