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
    return italianGrouping(euro.format(amount));
}

/**
 * Fixes a number written by it-IT: it does not group thousands below 10,000,
 * so the grouping is forced, and non-breaking spaces become normal spaces, so
 * the texts can be compared.
 *
 * @param text - the number as Intl wrote it
 * @returns the number with the thousands grouped and plain spaces
 */
function italianGrouping(text: string): string {
    return text.replace(/^(-?\d)(\d{3}),/, '$1.$2,').replace(/\s/g, ' ');
}

// One decimal at most, as the boards write consumption; rounded half away from zero, like round() on the server
// (`Consumption::quantity()`)
const quantityFormat = new Intl.NumberFormat('it-IT', {
    maximumFractionDigits: 1,
    useGrouping: 'always',
});

/** How each unit of a consumption is written. */
const UNIT_LABELS: Record<App.Enums.ConsumptionUnit, string> = {
    kwh: 'kWh',
    smc: 'Smc',
    m3: 'm³',
};

/**
 * Writes the unit of a consumption as it is printed on the bill.
 *
 * @param unit - the unit of the utility
 * @returns for example `kWh`
 */
export function unitLabel(unit: string): string {
    return UNIT_LABELS[unit as App.Enums.ConsumptionUnit] ?? unit;
}

/**
 * Writes a number, the Italian way: the thousands grouped, at most one
 * decimal, none when it is zero.
 *
 * @param value - the number
 * @returns for example `2.010,5`
 */
export function formatNumber(value: number): string {
    return quantityFormat.format(value);
}

/**
 * Writes a consumption, the Italian way: at most one decimal, none when it
 * is zero (`18,7 Smc`).
 *
 * @param value - the quantity
 * @param unit - the unit of the utility (`kwh`, `smc`, `m3`)
 * @returns for example `2.010 kWh`
 */
export function formatQuantity(value: number, unit: string): string {
    return `${formatNumber(value)} ${unitLabel(unit)}`;
}

/**
 * Writes an amount in any currency as the app writes euros: the Italian way, with the code of the currency
 * (`-120,00 USD`); euros keep the `€` sign.
 *
 * @param amount - the amount, with its sign
 * @param currency - the code of the currency, for example `USD`
 * @returns the amount as text
 */
export function formatMoney(amount: number, currency: string): string {
    if (currency === 'EUR') {
        return formatEuro(amount);
    }

    const number = new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);

    return `${italianGrouping(number)} ${currency}`;
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
 * Writes a date as dd/mm, without the year.
 *
 * @param date - a date of the server or a `Date`
 * @returns the date as text, for example `05/09`
 */
export function formatDayMonth(date: string | Date): string {
    return format(toDate(date), 'DD/MM', 'it');
}

/** The day, month, year, hours and minutes of a moment, in the time of Rome. */
const romeMoment = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Rome',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

/**
 * The parts of a moment in the time of Rome, whatever the clock of the computer.
 *
 * @param moment - a moment of the server, ISO with its offset
 * @returns the day, the month, the year, the hours and the minutes, two digits each (four for the year)
 */
function romeParts(
    moment: string,
): Record<'day' | 'month' | 'year' | 'hour' | 'minute', string> {
    const parts = Object.fromEntries(
        romeMoment
            .formatToParts(new Date(moment))
            .map((part) => [part.type, part.value]),
    );

    return {
        day: parts.day ?? '',
        month: parts.month ?? '',
        year: parts.year ?? '',
        hour: parts.hour ?? '',
        minute: parts.minute ?? '',
    };
}

/**
 * Writes the day and the time of a moment, in the time of Rome.
 *
 * @param moment - a moment of the server, ISO with its offset
 * @returns for example `25/10/2026 09:41`
 */
export function formatDateTime(moment: string): string {
    const { day, month, year, hour, minute } = romeParts(moment);

    return `${day}/${month}/${year} ${hour}:${minute}`;
}

/**
 * Writes the day without the year and the time of a moment, in the time of Rome, as the phone shows them.
 *
 * @param moment - a moment of the server, ISO with its offset
 * @returns for example `25/10 · 09:41`
 */
export function formatShortDateTime(moment: string): string {
    const { day, month, hour, minute } = romeParts(moment);

    return `${day}/${month} · ${hour}:${minute}`;
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
    return dayWithName(date, 'dddd D MMMM YYYY', locale);
}

/**
 * Writes a day with its name and month but no year, as the title of a day of
 * the calendar of the *Agenda*.
 *
 * @param date - a date of the server or a `Date`
 * @param locale - the language of the page, `it` or `en`
 * @returns for example «Mercoledì 4 novembre»
 */
export function formatDayTitle(date: string | Date, locale: string): string {
    return dayWithName(date, 'dddd D MMMM', locale);
}

/**
 * Writes a date with a pattern that starts with the name of the day, with a capital.
 *
 * @param date - a date of the server or a `Date`
 * @param pattern - the pattern of `@formkit/tempo`
 * @param locale - the language of the page
 * @returns the date written, starting with a capital
 */
function dayWithName(
    date: string | Date,
    pattern: string,
    locale: string,
): string {
    const text = format(toDate(date), pattern, locale);

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
 * Today on the calendar of Rome, as the server counts the days.
 *
 * @param now - the moment taken as now, the clock if missing
 * @returns `yyyy-mm-dd`
 */
export function todayInRome(now: Date = new Date()): string {
    return romeDay.format(now);
}

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
