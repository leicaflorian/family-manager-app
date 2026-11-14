/**
 * The logic of the pages of the Agenda: how the entries are grouped by week
 * (and by month further on), how the done ones are grouped by month, counted
 * by area and filtered by the person and the areas. The server sends the
 * entries already in order; the pages do the rest.
 */
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { reopen } from '@/routes/reminders';
import { capitalized, longMonth, monthName, shortDate } from './budget';
import { addDays, endOfWeek } from './days';
import { isoDate } from './format';

/** A block of the list: the entries to sort out, a week, or one month. */
export type AgendaGroup = {
    /** `overdue`, `this-week`, `next-week`, `later`, or the month as `yyyy-mm`. */
    key: string;
    /** The title of the block, for example «This week» or «October 2026». */
    title: string;
    /** Whether it is the block of the passed entries, which the page shows in red. */
    overdue: boolean;
    /** The entries, in the order the server sent them. */
    entries: App.Data.AgendaEntryData[];
};

/**
 * Whether an entry is already passed: a deadline, a payment or a task whose
 * day is before today.
 *
 * @param entry - the entry
 * @param today - today, `yyyy-mm-dd`
 * @returns true for a passed entry
 */
function isPassed(entry: App.Data.AgendaEntryData, today: string): boolean {
    return entry.date < today;
}

/**
 * Groups the entries as the list shows them: first the entries already
 * passed (*Late*), then *This week* (today to Sunday) and *Next week*;
 * then, for one month, the rest of the period in one block («Later, until
 * 26/11»), for a longer period one block for each month.
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
                entries: [],
            };
            groups.push(group);
        }

        return group;
    };

    for (const entry of entries) {
        const group = isPassed(entry, today)
            ? groupFor('overdue', () => trans('Late'))
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

    return groups;
}

/**
 * Groups the done entries by the month they were closed in, the most recent
 * month first; inside a month the order of the server stays. A month of this
 * year is named alone («November»), one of another year with it («December
 * 2025»), since the list goes back more than a year.
 *
 * @param entries - the done entries, with `reminder.done_on` set
 * @param today - today, `yyyy-mm-dd`
 * @returns the blocks, one for each month
 */
export function doneByMonth(
    entries: App.Data.AgendaEntryData[],
    today: string = isoDate(),
): AgendaGroup[] {
    const groups = new Map<string, AgendaGroup>();

    for (const entry of entries) {
        const doneOn = entry.reminder?.done_on;

        if (doneOn === null || doneOn === undefined) {
            continue;
        }

        const key = doneOn.slice(0, 7);
        const group = groups.get(key) ?? {
            key,
            title: key.startsWith(today.slice(0, 4))
                ? capitalized(longMonth(key))
                : monthName(key),
            overdue: false,
            entries: [],
        };

        group.entries.push(entry);
        groups.set(key, group);
    }

    return [...groups.values()].sort((a, b) => b.key.localeCompare(a.key));
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
 * Takes the `pay` flag off an address: the Home links a payment with the panel
 * of *I paid it* open, the panel of the Agenda links the due date itself.
 *
 * @param href - an address, absolute or from the root of the site
 * @returns the same address without `pay` in its query
 */
export function withoutPayQuery(href: string): string {
    const url = new URL(href, 'http://relative.invalid');

    url.searchParams.delete('pay');
    const rest = `${url.pathname}${url.search}${url.hash}`;

    return /^[a-z][a-z0-9+.-]*:/i.test(href) ? `${url.origin}${rest}` : rest;
}

/**
 * Puts a done reminder back among the things to do, leaving the page where it
 * is: *Reopen* of the panel and of the done rows on the computer.
 *
 * @param id - the id of the reminder
 * @param onSuccess - what to do once it is reopened, for example close the panel
 */
export function reopenReminder(id: number, onSuccess?: () => void): void {
    router.delete(reopen.url(id), {
        preserveScroll: true,
        preserveUrl: true,
        onSuccess: () => onSuccess?.(),
    });
}
