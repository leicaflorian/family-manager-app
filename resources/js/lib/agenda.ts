/**
 * The logic of the pages of the Agenda: how the entries are grouped by week
 * (and by month further on), summed up in the tiles above the list, counted by
 * area and filtered by the person and the areas. The server sends the entries
 * of the period already in order; the pages do the rest.
 */
import { trans } from 'laravel-vue-i18n';
import { monthName, shortDate } from './budget';
import { addDays, endOfWeek } from './days';

/** A block of the list: the deadlines to sort out, a week, or one month. */
export type AgendaGroup = {
    /** `overdue`, `this-week`, `next-week`, `later`, or the month as `yyyy-mm`. */
    key: string;
    /** The title of the block, for example «This week» or «October 2026». */
    title: string;
    /** Whether it is the block of the passed deadlines, which the page shows in red. */
    overdue: boolean;
    /** The sum of the payments of the block; null when it has none (the incomes do not count). */
    outgoing: number | null;
    /** The entries, in the order the server sent them. */
    entries: App.Data.AgendaEntryData[];
};

/** The figures of the tiles above the list. */
export type AgendaTotals = {
    /** How many deadlines are already passed. */
    overdue: number;
    /** The payments from today to Sunday: their sum, how many entries go out, and the two days, `yyyy-mm-dd`. */
    week: { amount: number; count: number; from: string; to: string };
    /** The payments of the whole period: their sum and how many entries go out, the passed deadlines too. */
    period: { amount: number; count: number };
};

/**
 * Rounds a sum of amounts to the cent, so that `13.99 + 62.4` does not end in
 * `76.38999999999999`.
 *
 * @param amount - the sum
 * @returns the sum with two decimals at most
 */
function toCents(amount: number): number {
    return Math.round(amount * 100) / 100;
}

/**
 * Whether an entry is a deadline already passed: only a deadline can be,
 * because payments and incomes before today do not come from the server.
 *
 * @param entry - the entry
 * @param today - today, `yyyy-mm-dd`
 * @returns true for a passed deadline
 */
function isPassed(entry: App.Data.AgendaEntryData, today: string): boolean {
    return entry.kind === 'deadline' && entry.date < today;
}

/**
 * The sum of the payments among some entries: the incomes and the deadlines do not count.
 *
 * @param entries - the entries
 * @returns the sum, rounded to the cent
 */
function outgoingOf(entries: App.Data.AgendaEntryData[]): number {
    return toCents(
        entries
            .filter((entry) => entry.kind === 'payment')
            .reduce((sum, entry) => sum + (entry.amount ?? 0), 0),
    );
}

/**
 * Groups the entries as the list shows them: first the deadlines already
 * passed (*To sort out*), then *This week* (today to Sunday) and *Next week*;
 * then, for one month, the rest of the period in one block («Later, until
 * 26/11»), for a longer period one block for each month. Each block has the sum
 * of its payments.
 *
 * @param entries - the entries, in the order of the server
 * @param today - today, `yyyy-mm-dd`
 * @param to - the last day of the period, `yyyy-mm-dd`
 * @param months - the length of the period, in months
 * @returns the blocks, without the empty ones
 */
export function groupByWeek(
    entries: App.Data.AgendaEntryData[],
    today: string,
    to: string,
    months: number,
): AgendaGroup[] {
    const sunday = endOfWeek(today);
    const nextSunday = addDays(sunday, 7);
    const groups: AgendaGroup[] = [];

    /**
     * The block of an entry, made the first time it is needed.
     *
     * @param key - the key of the block
     * @param title - its title
     * @returns the block
     */
    const groupFor = (key: string, title: () => string): AgendaGroup => {
        let group = groups.find((candidate) => candidate.key === key);

        if (group === undefined) {
            group = {
                key,
                title: title(),
                overdue: key === 'overdue',
                outgoing: null,
                entries: [],
            };
            groups.push(group);
        }

        return group;
    };

    for (const entry of entries) {
        const group = isPassed(entry, today)
            ? groupFor('overdue', () => trans('To sort out'))
            : entry.date <= sunday
              ? groupFor('this-week', () => trans('This week'))
              : entry.date <= nextSunday
                ? groupFor('next-week', () => trans('Next week'))
                : months === 1
                  ? groupFor('later', () =>
                        trans('Later, until :date', { date: shortDate(to) }),
                    )
                  : groupFor(entry.date.slice(0, 7), () =>
                        monthName(entry.date.slice(0, 7)),
                    );

        group.entries.push(entry);
    }

    // The passed deadlines come first even if the server sent them later
    groups.sort((a, b) => Number(b.overdue) - Number(a.overdue));

    for (const group of groups) {
        group.outgoing = group.entries.some((entry) => entry.kind === 'payment')
            ? outgoingOf(group.entries)
            : null;
    }

    return groups;
}

/**
 * The figures of the tiles above the list: the passed deadlines, what goes
 * out this week and what goes out in the whole period. The incomes are not
 * counted; a deadline counts as an entry that goes out, without an amount.
 *
 * @param entries - the entries the list shows
 * @param today - today, `yyyy-mm-dd`
 * @returns the figures
 */
export function agendaTotals(
    entries: App.Data.AgendaEntryData[],
    today: string,
): AgendaTotals {
    const sunday = endOfWeek(today);
    const outgoing = entries.filter((entry) => entry.kind !== 'income');
    const week = outgoing.filter(
        (entry) => !isPassed(entry, today) && entry.date <= sunday,
    );

    return {
        overdue: entries.filter((entry) => isPassed(entry, today)).length,
        week: {
            amount: outgoingOf(week),
            count: week.length,
            from: today,
            to: sunday,
        },
        period: { amount: outgoingOf(outgoing), count: outgoing.length },
    };
}

/**
 * The entries of the person chosen; with none chosen, all of them. The ones
 * without a person (the warranty of the television) are only for the whole family.
 *
 * @param entries - the entries of the period
 * @param personId - the person chosen, or null for the whole family
 * @returns the entries of the person
 */
function ofPerson(
    entries: App.Data.AgendaEntryData[],
    personId: number | null,
): App.Data.AgendaEntryData[] {
    return personId === null
        ? entries
        : entries.filter((entry) => entry.person_id === personId);
}

/**
 * How many entries each area has, for the person chosen. The areas turned off
 * count too: their number stays when their name is struck.
 *
 * @param entries - the entries of the period
 * @param personId - the person chosen, or null for the whole family
 * @param areas - the areas the server sends, in order
 * @returns the count of each area, zero included
 */
export function areaCounts(
    entries: App.Data.AgendaEntryData[],
    personId: number | null,
    areas: App.Data.OptionData[],
): Record<string, number> {
    const counts: Record<string, number> = Object.fromEntries(
        areas.map((area) => [area.value, 0]),
    );

    for (const entry of ofPerson(entries, personId)) {
        counts[entry.area] = (counts[entry.area] ?? 0) + 1;
    }

    return counts;
}

/**
 * The entries the list shows: the ones of the person chosen, in the areas
 * that are not turned off.
 *
 * @param entries - the entries of the period
 * @param filters - the filters in use
 * @returns the entries to show, in the same order
 */
export function visibleEntries(
    entries: App.Data.AgendaEntryData[],
    filters: App.Data.AgendaFiltersData,
): App.Data.AgendaEntryData[] {
    return ofPerson(entries, filters.person_id).filter(
        (entry) => !filters.hidden_areas.includes(entry.area),
    );
}
