/**
 * The data of the month grid of the Agenda: what each day of the period
 * holds, open things first and done ones at the end.
 */
import { addDays } from './days';

/** A thing of a day, as the grid draws it. */
export type MonthItem = {
    /** The key of the entry, unique in the grid. */
    key: string;
    /** What it is called. */
    title: string;
    /** The colour of the area (`home`, `vehicles`…). */
    area: string;
    /** Open and before today. */
    late: boolean;
    /** Closed: drawn grey and struck through. */
    done: boolean;
};

/** A day of the grid with the things that fall on it. */
export type MonthDay = {
    /** `yyyy-mm-dd`. */
    date: string;
    /** The open things in the order of the server, then the done ones. */
    items: MonthItem[];
};

/**
 * Splits the entries of a period by day.
 *
 * @param from - the first day, `yyyy-mm-dd`
 * @param to - the last day, `yyyy-mm-dd`
 * @param open - the open entries
 * @param done - the done entries
 * @param today - today, `yyyy-mm-dd`: an open entry before it is late
 * @returns one element for every day from `from` to `to`, empty days too
 */
export function monthDays(
    from: string,
    to: string,
    open: App.Data.AgendaEntryData[],
    done: App.Data.AgendaEntryData[],
    today: string,
): MonthDay[] {
    const byDate = new Map<string, MonthItem[]>();

    for (let date = from; date <= to; date = addDays(date, 1)) {
        byDate.set(date, []);
    }

    const add = (entry: App.Data.AgendaEntryData, isDone: boolean): void => {
        byDate.get(entry.date)?.push({
            key: entry.key,
            title: entry.title,
            area: entry.area,
            late: !isDone && entry.date < today,
            done: isDone,
        });
    };

    open.forEach((entry) => add(entry, false));
    done.forEach((entry) => add(entry, true));

    return Array.from(byDate, ([date, items]) => ({ date, items }));
}
