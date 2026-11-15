import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { createApp } from 'vue';
import { router } from '@inertiajs/vue3';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import { toast } from 'vue-sonner';
import { agendaAreas, agendaEntries } from '@/test/fixtures';
import {
    areaCounts,
    doneByMonth,
    groupByWeek,
    lateCount,
    markReminderDone,
    reopenReminder,
    visibleEntries,
    withoutPayQuery,
} from './agenda';
import { i18nOptions } from './i18n';

const today = '2026-10-26';
const defaults: App.Data.AgendaFiltersData = {
    months: 1,
    person_id: null,
    hidden_areas: [],
    calendar: false,
};

/**
 * Sets the language of the texts outside a component, as the app does at start.
 *
 * @param lang - `en` or `it`
 */
async function speak(lang: string): Promise<void> {
    createApp({}).use(i18nVue, i18nOptions(lang));
    await loadLanguageAsync(lang);
}

beforeAll(async () => {
    await speak('en');
});

describe('groupByWeek', () => {
    it('puts the passed deadlines first, then this week, next week and the rest of the month', () => {
        const groups = groupByWeek(agendaEntries, today, '2026-11-26', 1);

        expect(groups.map((group) => group.title)).toEqual([
            'Late',
            'This week',
            'Next week',
            'Later, until 26/11',
        ]);
        expect(groups.map((group) => group.overdue)).toEqual([
            true,
            false,
            false,
            false,
        ]);
        expect(groups[0].entries.map((entry) => entry.title)).toEqual([
            'Tessera sanitaria di Giulia',
        ]);
        expect(groups.map((group) => group.entries.length)).toEqual([
            1, 3, 3, 5,
        ]);
    });

    it('ends this week on Sunday and starts the next one on Monday', () => {
        // Sunday 01/11: this week is that day alone
        const groups = groupByWeek(
            agendaEntries.slice(4),
            '2026-11-01',
            '2026-12-01',
            1,
        );

        expect(groups.map((group) => group.key)).toEqual([
            'next-week',
            'later',
        ]);
        expect(groups[0].entries.map((entry) => entry.date)).toEqual([
            '2026-11-04',
            '2026-11-04',
            '2026-11-06',
        ]);
        expect(groups[1].entries[0].date).toBe('2026-11-10');
    });

    it('groups by month after next week when the period is longer', () => {
        const later = {
            ...agendaEntries[10],
            key: 'payment:later',
            date: '2026-12-21',
        };
        const groups = groupByWeek(
            [...agendaEntries, later],
            today,
            '2027-01-26',
            3,
        );

        expect(groups.map((group) => group.title)).toEqual([
            'Late',
            'This week',
            'Next week',
            'November 2026',
            'December 2026',
        ]);
    });

    it('puts every passed entry in the group to sort out: deadlines, payments and tasks', () => {
        const task = {
            ...agendaEntries[4],
            key: 'task:7',
            kind: 'task',
            title: 'Portare la bici a riparare',
            date: '2026-10-20',
        };
        const payment = { ...agendaEntries[2], date: '2026-10-25' };
        const [overdue] = groupByWeek(
            [agendaEntries[0], task, payment],
            today,
            '2026-11-26',
            1,
        );

        expect(overdue.overdue).toBe(true);
        expect(overdue.entries).toHaveLength(3);
    });

    it('has no sum on the groups', () => {
        const groups = groupByWeek(agendaEntries, today, '2026-11-26', 1);

        for (const group of groups) {
            expect(group).not.toHaveProperty('outgoing');
        }
    });

    it('has no group to sort out when nothing is passed', () => {
        const groups = groupByWeek(
            agendaEntries.slice(1),
            today,
            '2026-11-26',
            1,
        );

        expect(groups.map((group) => group.key)).toEqual([
            'this-week',
            'next-week',
            'later',
        ]);
    });

    it('is written in Italian', async () => {
        await speak('it');

        expect(
            groupByWeek(agendaEntries, today, '2026-11-26', 1).map(
                (group) => group.title,
            ),
        ).toEqual([
            'In ritardo',
            'Questa settimana',
            'Prossima settimana',
            'Più avanti, fino al 26/11',
        ]);
        expect(
            groupByWeek(agendaEntries, today, '2027-01-26', 3)[3].title,
        ).toBe('Novembre 2026');

        await speak('en');
    });
});

describe('doneByMonth', () => {
    /** A closed entry, done on the day given. */
    function closed(doneOn: string): App.Data.AgendaEntryData {
        return {
            ...agendaEntries[8],
            key: `deadline:${doneOn}`,
            reminder: { ...agendaEntries[8].reminder!, done_on: doneOn },
        };
    }

    it('makes a group for each month of the closing, the most recent first', () => {
        const groups = doneByMonth(
            [closed('2026-11-05'), closed('2026-10-30'), closed('2026-11-01')],
            today,
        );

        expect(groups.map((group) => group.key)).toEqual([
            '2026-11',
            '2026-10',
        ]);
        expect(groups.map((group) => group.title)).toEqual([
            'November',
            'October',
        ]);
        expect(groups.map((group) => group.entries.length)).toEqual([2, 1]);
        expect(groups.every((group) => !group.overdue)).toBe(true);
    });

    it('keeps the order of the server inside a month and is empty without entries', () => {
        const groups = doneByMonth(
            [closed('2026-11-05'), closed('2026-11-01')],
            today,
        );

        expect(
            groups[0].entries.map((entry) => entry.reminder?.done_on),
        ).toEqual(['2026-11-05', '2026-11-01']);
        expect(doneByMonth([], today)).toEqual([]);
    });

    it('names the months of this year alone, and keeps the year for the others', async () => {
        const entries = [closed('2026-01-10'), closed('2025-12-20')];

        expect(doneByMonth(entries, today).map((group) => group.title)).toEqual(
            ['January', 'December 2025'],
        );

        await speak('it');
        expect(doneByMonth(entries, today).map((group) => group.title)).toEqual(
            ['Gennaio', 'Dicembre 2025'],
        );
        await speak('en');
    });
});

describe('lateCount', () => {
    it('counts the entries whose day is before today', () => {
        expect(lateCount(agendaEntries, today)).toBe(
            agendaEntries.filter((entry) => entry.date < today).length,
        );
        expect(lateCount(agendaEntries, '2026-01-01')).toBe(0);
    });
});

describe('markReminderDone', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('marks a reminder done, leaving the page where it is', () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.({} as never);
            });
        const done = vi.fn();

        markReminderDone(7, 'Polizza auto', done);

        expect(post).toHaveBeenCalledWith(
            '/reminders/7/done',
            {},
            expect.objectContaining({
                preserveScroll: true,
                preserveUrl: true,
            }),
        );
        expect(done).toHaveBeenCalledOnce();
    });
    it('shows the reason of the server and no undo message when it refuses', () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onError?.({ status: 'This cannot be confirmed.' });
        });
        const success = vi.spyOn(toast, 'success');
        const error = vi.spyOn(toast, 'error').mockImplementation(() => 1);
        const done = vi.fn();

        markReminderDone(7, 'Polizza auto', done);

        expect(error).toHaveBeenCalledWith('This cannot be confirmed.');
        expect(success).not.toHaveBeenCalled();
        expect(done).not.toHaveBeenCalled();
    });
});

describe('reopenReminder', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('puts a done reminder back among the things to do, leaving the page where it is', () => {
        const remove = vi
            .spyOn(router, 'delete')
            .mockImplementation((_url, options) => {
                options?.onSuccess?.({} as never);
            });
        const done = vi.fn();

        reopenReminder(7, done);

        expect(remove).toHaveBeenCalledWith(
            '/reminders/7/done',
            expect.objectContaining({
                preserveScroll: true,
                preserveUrl: true,
            }),
        );
        expect(done).toHaveBeenCalledOnce();
    });
});

describe('areaCounts', () => {
    it('counts the ten areas, the empty ones too', () => {
        const counts = areaCounts(agendaEntries, null, agendaAreas);

        expect(Object.keys(counts)).toHaveLength(10);
        expect(counts).toEqual({
            home: 3,
            vehicles: 0,
            insurance: 1,
            banks: 2,
            health: 0,
            school: 0,
            work: 2,
            subscriptions: 2,
            paperwork: 0,
            general: 2,
        });
    });

    it('counts the entries of the person chosen', () => {
        expect(areaCounts(agendaEntries, 2, agendaAreas).work).toBe(1);
    });
});

describe('visibleEntries', () => {
    it('hides the entries of the areas turned off', () => {
        const visible = visibleEntries(agendaEntries, {
            ...defaults,
            hidden_areas: ['work'],
        });

        expect(visible).toHaveLength(10);
    });

    it('keeps the entries without a person only for the whole family', () => {
        const withoutPerson = [
            ...agendaEntries,
            { ...agendaEntries[2], key: 'x', person_id: null, person: null },
        ];

        expect(visibleEntries(withoutPerson, defaults)).toHaveLength(13);
        expect(
            visibleEntries(withoutPerson, { ...defaults, person_id: 2 }),
        ).toHaveLength(3);
    });
});

describe('withoutPayQuery', () => {
    it('takes the pay flag off an address, keeping the rest', () => {
        expect(
            withoutPayQuery(
                'http://localhost:8090/budget/planned/expense/3/occurrences/2026-10-28?pay=1',
            ),
        ).toBe(
            'http://localhost:8090/budget/planned/expense/3/occurrences/2026-10-28',
        );
        expect(withoutPayQuery('/budget/x?pay=1&month=2026-10#top')).toBe(
            '/budget/x?month=2026-10#top',
        );
    });

    it('leaves an address without the flag as it is', () => {
        expect(withoutPayQuery('/cards/4')).toBe('/cards/4');
        expect(withoutPayQuery('/a?month=2026-10')).toBe('/a?month=2026-10');
    });
});
