import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { createApp } from 'vue';
import { beforeAll, describe, expect, it } from 'vite-plus/test';
import { agendaAreas, agendaEntries } from '@/test/fixtures';
import { areaCounts, groupByMonth, summary, visibleEntries } from './agenda';
import { i18nOptions } from './i18n';

const today = '2026-10-26';
const labels = Object.fromEntries(
    agendaAreas.map((area) => [area.value, area.label]),
);
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

describe('groupByMonth', () => {
    it('puts the passed deadlines first, then one group for each month', () => {
        const groups = groupByMonth(agendaEntries, today);

        expect(groups.map((group) => group.title)).toEqual([
            'To sort out',
            'October 2026',
            'November 2026',
        ]);
        expect(groups.map((group) => group.overdue)).toEqual([
            true,
            false,
            false,
        ]);
        expect(groups[0].entries.map((entry) => entry.title)).toEqual([
            'Tessera sanitaria di Giulia',
        ]);
    });

    it('sums the payments of a month, not the incomes', () => {
        const [, october, november] = groupByMonth(agendaEntries, today);

        expect(november.outgoing).toBe(1295.39);
        expect(october.outgoing).toBe(122.1);
    });

    it('has no sum for a group without payments', () => {
        const groups = groupByMonth(agendaEntries.slice(0, 2), today);

        expect(groups[0].outgoing).toBeNull();
        expect(groups[1].outgoing).toBeNull();
    });

    it('has no group to sort out when nothing is passed', () => {
        const groups = groupByMonth(agendaEntries.slice(1), today);

        expect(groups.map((group) => group.key)).toEqual([
            '2026-10',
            '2026-11',
        ]);
    });

    it('writes the month in the language of the page', async () => {
        await speak('it');

        expect(groupByMonth(agendaEntries, today)[1].title).toBe(
            'Ottobre 2026',
        );
        expect(groupByMonth(agendaEntries, today)[0].title).toBe(
            'Da sistemare',
        );

        await speak('en');
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

describe('summary', () => {
    it('says how many entries, the period and what is hidden', () => {
        expect(
            summary(10, { work: 2 }, 1, '2026-10-26', '2026-11-26', labels),
        ).toBe(
            '10 entries in the next month, from 26/10 to 26/11 · 2 hidden: Work',
        );
    });

    it('has no hidden part when no area is turned off', () => {
        expect(summary(12, {}, 3, '2026-10-26', '2027-01-26', labels)).toBe(
            '12 entries in the next 3 months, from 26/10 to 26/01',
        );
    });

    it('says nothing about an area that is off but has no entries', () => {
        expect(
            summary(5, { work: 0 }, 1, '2026-10-26', '2026-11-26', labels),
        ).toBe('5 entries in the next month, from 26/10 to 26/11');
    });

    it('lists the names of the hidden areas', () => {
        expect(
            summary(
                1,
                { work: 2, general: 1 },
                1,
                '2026-10-26',
                '2026-11-26',
                labels,
            ),
        ).toBe(
            '1 entry in the next month, from 26/10 to 26/11 · 3 hidden: Work and People and documents',
        );
    });

    it('is written in Italian', async () => {
        await speak('it');

        expect(
            summary(10, { work: 2 }, 1, '2026-10-26', '2026-11-26', {
                work: 'Lavoro',
            }),
        ).toBe(
            '10 voci nel prossimo mese, dal 26/10 al 26/11 · 2 nascoste: Lavoro',
        );

        await speak('en');
    });
});
