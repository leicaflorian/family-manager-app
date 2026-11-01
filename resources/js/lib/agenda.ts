/**
 * The logic of the pages of the Agenda: how the entries are grouped by month,
 * counted by area, filtered by the person and the areas, and told in the
 * summary above the list. The server sends the entries of the period already in
 * order; the pages do the rest.
 */
import { trans, transChoice } from 'laravel-vue-i18n';
import { monthName, shortDate } from './budget';
import { listNames } from './names';

/** A block of the list: the deadlines to sort out, or one month. */
export type AgendaGroup = {
    /** `overdue`, or the month as `yyyy-mm`. */
    key: string;
    /** The title of the block, for example «October 2026». */
    title: string;
    /** Whether it is the block of the passed deadlines, which the page shows in red. */
    overdue: boolean;
    /** The sum of the payments of the block; null when it has none (the incomes do not count). */
    outgoing: number | null;
    /** The entries, in the order the server sent them. */
    entries: App.Data.AgendaEntryData[];
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
 * Groups the entries as the list shows them: first the deadlines already
 * passed (*To sort out*), then one block for each month with the sum of its
 * payments. Payments and incomes before today do not come from the server, so
 * only a deadline can be passed.
 *
 * @param entries - the entries, in the order of the server
 * @param today - today, `yyyy-mm-dd`
 * @returns the blocks, without the empty ones
 */
export function groupByMonth(
    entries: App.Data.AgendaEntryData[],
    today: string,
): AgendaGroup[] {
    const groups: AgendaGroup[] = [];
    const overdue = entries.filter(
        (entry) => entry.kind === 'deadline' && entry.date < today,
    );

    const passed = new Set(overdue);

    if (overdue.length > 0) {
        groups.push({
            key: 'overdue',
            title: trans('To sort out'),
            overdue: true,
            outgoing: null,
            entries: overdue,
        });
    }

    for (const entry of entries) {
        if (passed.has(entry)) {
            continue;
        }

        const month = entry.date.slice(0, 7);
        let group = groups.find((candidate) => candidate.key === month);

        if (group === undefined) {
            group = {
                key: month,
                title: monthName(month),
                overdue: false,
                outgoing: null,
                entries: [],
            };
            groups.push(group);
        }

        group.entries.push(entry);

        if (entry.kind === 'payment') {
            group.outgoing = toCents(
                (group.outgoing ?? 0) + (entry.amount ?? 0),
            );
        }
    }

    return groups;
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

/**
 * The line above the list: «10 entries in the next month, from 26/10 to 26/11
 * · 2 hidden: Work».
 *
 * @param visible - how many entries the list shows
 * @param hidden - for each area turned off, how many entries it hides
 * @param months - the length of the period, in months
 * @param from - the first day, `yyyy-mm-dd`
 * @param to - the last day, `yyyy-mm-dd`
 * @param labels - the name of each area, by area, as the server sends them
 * @returns the sentence, in the language of the page
 */
export function summary(
    visible: number,
    hidden: Record<string, number>,
    months: number,
    from: string,
    to: string,
    labels: Record<string, string>,
): string {
    const text = trans(':entries :period, from :from to :to', {
        entries: transChoice('{1} :count entry|[2,*] :count entries', visible, {
            count: String(visible),
        }),
        period: transChoice(
            '{1} in the next month|[2,*] in the next :count months',
            months,
            { count: String(months) },
        ),
        from: shortDate(from),
        to: shortDate(to),
    });
    const areas = Object.keys(hidden);
    const count = Object.values(hidden).reduce((sum, n) => sum + n, 0);

    // Areas turned off that have no entries hide nothing: there is nothing to say.
    if (count === 0) {
        return text;
    }

    const hiddenText = transChoice(
        '{1} :count hidden: :areas|[2,*] :count hidden: :areas',
        count,
        {
            count: String(count),
            areas: listNames(areas.map((area) => labels[area] ?? area)),
        },
    );

    return `${text} · ${hiddenText}`;
}
