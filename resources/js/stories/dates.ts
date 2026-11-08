/**
 * Dates for the stories, counted from today so that a story that
 * is «expiring» or «expired» stays true as the days go by.
 */

/**
 * A date some days from today.
 *
 * @param days - the days from today, negative for the past
 * @returns the date as `aaaa-mm-gg`
 */
export function fromToday(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
