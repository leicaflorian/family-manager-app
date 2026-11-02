import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { createApp } from 'vue';
import { beforeAll, describe, expect, it } from 'vite-plus/test';
import { agendaAreas, agendaEntries } from '@/test/fixtures';
import {
    agendaTotals,
    areaCounts,
    groupByWeek,
    visibleEntries,
} from './agenda';
import { i18nOptions } from './i18n';

const today = '2026-10-26';
const defaults: App.Data.AgendaFiltersData = {
    months: 1,
    person_id: null,
    hidden_areas: [],
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
            'To sort out',
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
            'To sort out',
            'This week',
            'Next week',
            'November 2026',
            'December 2026',
        ]);
    });

    it('sums the payments of a group, not the incomes', () => {
        const [overdue, week, next, later] = groupByWeek(
            agendaEntries,
            today,
            '2026-11-26',
            1,
        );

        expect(overdue.outgoing).toBeNull();
        expect(week.outgoing).toBe(122.1);
        expect(next.outgoing).toBe(76.39);
        expect(later.outgoing).toBe(1219);
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
            'Da sistemare',
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

describe('agendaTotals', () => {
    it('counts the passed deadlines', () => {
        expect(agendaTotals(agendaEntries, today).overdue).toBe(1);
    });

    it('sums the payments of this week, up to Sunday, and counts what goes out', () => {
        expect(agendaTotals(agendaEntries, today).week).toEqual({
            amount: 122.1,
            count: 2,
            from: '2026-10-26',
            to: '2026-11-01',
        });
    });

    it('sums the payments of the whole period and counts what goes out, the passed deadlines too', () => {
        expect(agendaTotals(agendaEntries, today).period).toEqual({
            amount: 1417.49,
            count: 10,
        });
    });

    it('is all zero without entries', () => {
        expect(agendaTotals([], today)).toEqual({
            overdue: 0,
            week: { amount: 0, count: 0, from: today, to: '2026-11-01' },
            period: { amount: 0, count: 0 },
        });
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
