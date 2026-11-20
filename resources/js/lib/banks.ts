import { trans } from 'laravel-vue-i18n';
import { formatDate, todayInRome } from '@/lib/format';

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
