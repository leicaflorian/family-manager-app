import { trans } from 'laravel-vue-i18n';
import { formatDate, formatDayMonth, todayInRome } from '@/lib/format';
import { listNames } from '@/lib/names';

/**
 * The letters of the tile of a bank without a logo: the first of the first two words, or of the first two humps
 * of a name written in one word (`BancoPosta` → `BP`), or the first two letters of a single word.
 *
 * @param name - the name of the bank
 * @returns up to two capital letters; empty for an empty name
 */
export function bankInitials(name: string): string {
    const words = name.match(/\p{Lu}[^\p{Lu}\s]*|[^\p{Lu}\s]+/gu) ?? [];
    const parts =
        name.trim().split(/\s+/).length > 1 ? name.trim().split(/\s+/) : words;

    if (parts.length >= 2) {
        return parts
            .slice(0, 2)
            .map((part) => Array.from(part)[0] ?? '')
            .join('')
            .toUpperCase();
    }

    return Array.from(name.trim()).slice(0, 2).join('').toUpperCase();
}

/**
 * When the transactions of a bank were read last, in words.
 *
 * @param moment - the moment of the server, `yyyy-mm-ddThh:mm` in Rome
 * @param now - the moment taken as now, the clock if missing
 * @returns «Last update today at 13:00» or «Last update on 18/11/2026 at 07:00»
 */
export function lastReadText(moment: string, now: Date = new Date()): string {
    const day = moment.slice(0, 10);
    const time = moment.slice(11, 16);

    return day === todayInRome(now)
        ? trans('Last update today at :time', { time })
        : trans('Last update on :date at :time', {
              date: formatDate(day),
              time,
          });
}

/**
 * The hours the banks are read, as a sentence with an «at» for each: «at 7, at 13 and at 19» («alle 7, alle 13 e
 * alle 19»).
 *
 * @param hours - the hours of the day, as the server schedules them
 * @returns the hours written in the language of the page
 */
export function readTimes(hours: number[]): string {
    return listNames(
        hours.map((hour) => trans('at :hour', { hour: String(hour) })),
    );
}

/**
 * The day a bank transaction is listed with: the booked one, for a pending transaction the one the bank gave.
 *
 * @param row - the transaction (only its two days are read)
 * @param now - the moment taken as now, the clock if missing
 * @returns «06/11», with the year only for a day that is not of this year; empty without any day
 */
export function dayOf(
    row: { booked_on: string | null; value_on: string | null },
    now: Date = new Date(),
): string {
    const day = row.booked_on ?? row.value_on;

    if (!day) {
        return '';
    }

    return day.slice(0, 4) === todayInRome(now).slice(0, 4)
        ? formatDayMonth(day)
        : formatDate(day);
}
