/**
 * Moving a day on the calendar. The days travel as `yyyy-mm-dd`, as the server
 * sends and reads them; the arithmetic is done in UTC, so that the change to
 * summer or winter time never moves a day.
 */

/**
 * Splits a day into its numbers.
 *
 * @param day - `yyyy-mm-dd`
 * @returns year, month (1-12) and day of the month
 */
function partsOf(day: string): [number, number, number] {
    const [year, month, date] = day.split('-').map(Number);

    return [year, month, date];
}

/**
 * Writes a UTC date as a day.
 *
 * @param value - the date
 * @returns `yyyy-mm-dd`
 */
function dayOf(value: Date): string {
    return value.toISOString().slice(0, 10);
}

/**
 * Moves a day forward or back by some days.
 *
 * @param day - `yyyy-mm-dd`
 * @param days - how many days, negative to go back
 * @returns the new day, `yyyy-mm-dd`
 */
export function addDays(day: string, days: number): string {
    const [year, month, date] = partsOf(day);

    return dayOf(new Date(Date.UTC(year, month - 1, date + days)));
}

/**
 * Moves a day forward or back by some months, keeping the day of the month;
 * a day the new month does not have becomes its last one (31/01 → 28/02).
 *
 * @param day - `yyyy-mm-dd`
 * @param months - how many months, negative to go back
 * @returns the new day, `yyyy-mm-dd`
 */
export function addMonths(day: string, months: number): string {
    const [year, month, date] = partsOf(day);
    // Day 0 of the month after is the last day of the target month
    const last = new Date(
        Date.UTC(year, month - 1 + months + 1, 0),
    ).getUTCDate();

    return dayOf(
        new Date(Date.UTC(year, month - 1 + months, Math.min(date, last))),
    );
}

/**
 * Moves a day forward or back by some years; the 29th of February becomes the
 * 28th in a year that does not have it.
 *
 * @param day - `yyyy-mm-dd`
 * @param years - how many years, negative to go back
 * @returns the new day, `yyyy-mm-dd`
 */
export function addYears(day: string, years: number): string {
    return addMonths(day, years * 12);
}
