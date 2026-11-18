import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { createApp } from 'vue';
import { i18nOptions } from './i18n';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import {
    danceDeadline,
    lateDeadline,
    paidDeadline,
    salaryDeadline,
} from '@/test/fixtures';
import {
    accountChoices,
    comparisonComment,
    deadlineStanding,
    deadlineStatus,
    deadlineUrls,
    monthName,
    movementKinds,
    movementsUrl,
    plannedUrl,
    recurrenceText,
    skippedMonthsText,
    yearlessDate,
} from './budget';

const today = new Date(2026, 9, 26, 10, 0);

/**
 * Sets the language of the texts outside a component, as the app does at start.
 *
 * @param lang - `en` or `it`
 */
async function speak(lang: string): Promise<void> {
    createApp({}).use(i18nVue, i18nOptions(lang));
    await loadLanguageAsync(lang);
}

afterEach(async () => {
    await speak('en');
});

describe('yearlessDate', () => {
    it('leaves out the year of a day of this year', () => {
        expect(yearlessDate('2026-11-15', today)).toBe('15/11');
    });

    it('writes the year of a day of another year', () => {
        expect(yearlessDate('2027-11-15', today)).toBe('15/11/2027');
        expect(yearlessDate('2025-03-02', today)).toBe('02/03/2025');
    });

    it('counts the year on the calendar of Rome', () => {
        // 23:30 UTC of 31 December is already 1 January in Rome
        const newYear = new Date(Date.UTC(2026, 11, 31, 23, 30));

        expect(yearlessDate('2027-01-05', newYear)).toBe('05/01');
    });
});

describe('recurrenceText', () => {
    it('writes the monthly, yearly, weekly and one-off rules', () => {
        expect(recurrenceText('month', 1, '2025-09-05', true, null)).toBe(
            'every month, on day 5',
        );
        expect(recurrenceText('month', 2, '2025-09-05', true, null)).toBe(
            'every 2 months, on day 5',
        );
        expect(recurrenceText('year', 1, '2025-11-21', true, null)).toBe(
            'every year, on 21/11',
        );
        expect(recurrenceText('week', 2, '2026-10-05', true, null)).toBe(
            'every 2 weeks',
        );
        expect(recurrenceText('week', 1, '2026-10-05', true, null)).toBe(
            'every week',
        );
        expect(recurrenceText('month', 1, '2026-11-04', false, null)).toBe(
            'once, on 04/11/2026',
        );
    });

    it('adds the end date', () => {
        expect(
            recurrenceText('month', 1, '2025-09-05', true, '2028-02-10'),
        ).toBe('every month, on day 5 · until 10/02/2028');
    });

    it('speaks Italian', async () => {
        await speak('it');

        expect(recurrenceText('month', 1, '2025-09-05', true, null)).toBe(
            'ogni mese, il 5',
        );
        expect(recurrenceText('year', 1, '2025-11-21', true, null)).toBe(
            'ogni anno, il 21/11',
        );
        expect(recurrenceText('week', 2, '2026-10-05', true, null)).toBe(
            'ogni 2 settimane',
        );
        expect(recurrenceText('month', 1, '2026-11-04', false, null)).toBe(
            'una volta, il 04/11/2026',
        );
        expect(
            recurrenceText('month', 1, '2025-09-05', true, '2028-02-10'),
        ).toBe('ogni mese, il 5 · fino al 10/02/2028');
    });
});

describe('comparisonComment', () => {
    it('says same, less and more', () => {
        expect(comparisonComment(612, 612, false, null)).toBe('same');
        expect(comparisonComment(29.9, 223.3, false, null)).toBe(
            '193,40 € less',
        );
        expect(comparisonComment(90, 45, false, null)).toBe('45,00 € more');
    });

    it('adds what is not planned, estimated or late', () => {
        expect(comparisonComment(108.4, 0, false, null)).toBe(
            '108,40 € more · not planned',
        );
        expect(comparisonComment(150.1, 320, true, null)).toBe(
            '169,90 € less · planned amount estimated',
        );
        expect(comparisonComment(29.9, 223.3, false, 'luce')).toBe(
            '193,40 € less · luce late',
        );
    });

    it('keeps an acronym as it is', () => {
        expect(comparisonComment(29.9, 223.3, false, 'IMU')).toBe(
            '193,40 € less · IMU late',
        );
    });

    it('writes the title of the late due date in lowercase', () => {
        expect(comparisonComment(29.9, 223.3, false, 'Luce')).toBe(
            '193,40 € less · luce late',
        );
    });

    it('speaks Italian', async () => {
        await speak('it');

        expect(comparisonComment(612, 612, false, null)).toBe('uguale');
        expect(comparisonComment(29.9, 223.3, false, null)).toBe(
            '193,40 € in meno',
        );
        expect(comparisonComment(90, 45, false, null)).toBe('45,00 € in più');
        expect(comparisonComment(108.4, 0, false, null)).toBe(
            '108,40 € in più · non prevista',
        );
        expect(comparisonComment(150.1, 320, true, null)).toBe(
            '169,90 € in meno · previsto stimato',
        );
        expect(comparisonComment(29.9, 223.3, false, 'luce')).toBe(
            '193,40 € in meno · luce in ritardo',
        );
    });
});

describe('deadlineStatus', () => {
    it('says when a due date falls, how late it is, or when it was paid', () => {
        const rc = { ...salaryDeadline, kind: 'expense', date: '2026-11-21' };

        expect(deadlineStatus(rc, today)).toBe(
            'Due on 21/11/2026 · in 26 days',
        );
        expect(deadlineStatus(lateDeadline, today)).toBe('Overdue by 3 days');
        expect(deadlineStatus(paidDeadline, today)).toBe('Paid on 20/10');
        expect(deadlineStatus(salaryDeadline, today)).toBe(
            'Arrives on 27/10/2026 · tomorrow',
        );
        expect(deadlineStatus({ ...danceDeadline, skipped: true }, today)).toBe(
            'Skipped',
        );
    });

    it('speaks Italian', async () => {
        await speak('it');

        expect(deadlineStatus(lateDeadline, today)).toBe('Scaduta da 3 giorni');
        expect(deadlineStatus(paidDeadline, today)).toBe('Pagata il 20/10');
        expect(
            deadlineStatus(
                { ...salaryDeadline, kind: 'expense', date: '2026-11-21' },
                today,
            ),
        ).toBe('Scade il 21/11/2026 · tra 26 giorni');
    });
});

describe('deadlineStanding', () => {
    it('says where a due date stands in a list: late, paid, changed, or nothing', () => {
        expect(deadlineStanding(lateDeadline, today)).toEqual({
            text: 'Overdue by 3 days',
            tone: 'overdue',
        });
        expect(deadlineStanding(paidDeadline, today)).toEqual({
            text: 'Paid on 20/10',
            tone: 'paid',
        });
        expect(
            deadlineStanding(
                { ...danceDeadline, base_amount: 60, notes: 'one lesson more' },
                today,
            ),
        ).toEqual({
            text: 'Different amount: one lesson more',
            tone: 'warning',
        });
        expect(
            deadlineStanding(
                {
                    ...danceDeadline,
                    base_amount: null,
                    notes: null,
                    date: '2026-12-07',
                },
                today,
            ),
        ).toEqual({ text: 'Different date', tone: 'warning' });
        expect(
            deadlineStanding(
                { ...danceDeadline, base_amount: null, notes: null },
                today,
            ),
        ).toEqual({ text: null, tone: 'normal' });
    });
});

describe('monthName', () => {
    it('writes a month in the language of the page', async () => {
        expect(monthName('2026-10')).toBe('October 2026');
        await speak('it');
        expect(monthName('2026-10')).toBe('Ottobre 2026');
    });
});

describe('deadlineUrls', () => {
    it('gives the addresses of a due date from its original day', () => {
        expect(deadlineUrls(danceDeadline)).toEqual({
            show: '/budget/planned/expense/4/occurrences/2026-12-05',
            payment: '/budget/planned/expense/4/occurrences/2026-12-05/payment',
            exceptions:
                '/budget/planned/expense/4/occurrences/2026-12-05/exceptions',
        });
    });

    it('keeps the original day when an exception moved the due date', () => {
        expect(
            deadlineUrls({
                ...salaryDeadline,
                date: '2026-10-30',
                original_date: '2026-10-27',
            }).show,
        ).toBe('/budget/planned/income/2/occurrences/2026-10-27');
    });
});

describe('skippedMonthsText', () => {
    it('names the months that are skipped', async () => {
        expect(skippedMonthsText([7, 8])).toBe('July and August skipped');
        expect(skippedMonthsText([8])).toBe('August skipped');
        expect(skippedMonthsText([])).toBe('');
        await speak('it');
        expect(skippedMonthsText([7, 8])).toBe('luglio e agosto saltati');
        await speak('en');
    });
});

describe('movementsUrl', () => {
    it('writes the month and leaves out what is empty', () => {
        expect(
            movementsUrl({
                month: '2026-10',
                kind: null,
                account: null,
                search: null,
                min: null,
                max: null,
                from: null,
                to: null,
            }),
        ).toBe('/budget/movements?month=2026-10');
    });

    it('carries every filter and the movement open, in this order', () => {
        expect(
            movementsUrl({
                month: '2026-10',
                kind: 'expense',
                account: 1,
                search: 'spesa',
                min: 20,
                max: 100.5,
                from: '2026-09-01',
                to: '2026-10-31',
                movement: 'expense:52',
            }),
        ).toBe(
            '/budget/movements?month=2026-10&kind=expense&account=1&search=spesa&min=20&max=100.5&from=2026-09-01&to=2026-10-31&movement=expense%3A52',
        );
    });

    it('keeps an amount of zero', () => {
        expect(movementsUrl({ month: '2026-10', min: 0 })).toBe(
            '/budget/movements?month=2026-10&min=0',
        );
    });
});

describe('plannedUrl', () => {
    it('leaves out what is empty', () => {
        expect(
            plannedUrl({
                search: null,
                min: null,
                max: null,
                from: null,
                to: null,
            }),
        ).toBe('/budget/planned');
    });

    it('carries every filter, in this order', () => {
        expect(
            plannedUrl({
                to: '2026-11-30',
                from: '2026-11-01',
                max: 100,
                min: 10,
                search: 'luce',
            }),
        ).toBe(
            '/budget/planned?search=luce&min=10&max=100&from=2026-11-01&to=2026-11-30',
        );
    });
});

describe('movementKinds', () => {
    it('offers everything first, then the expenses and the incomes', () => {
        expect(movementKinds(null)).toEqual([
            { value: null, label: 'Everything' },
            { value: 'expense', label: 'Expenses' },
            { value: 'income', label: 'Income' },
        ]);
    });

    it('takes the value that means everything', () => {
        expect(movementKinds('all')[0]).toEqual({
            value: 'all',
            label: 'Everything',
        });
    });

    it('speaks the language of the page', async () => {
        await speak('it');

        expect(movementKinds(null).map(({ label }) => label)).toEqual([
            'Tutto',
            'Spese',
            'Entrate',
        ]);
    });
});

describe('accountChoices', () => {
    it('puts «All the accounts» first, with an empty value', () => {
        expect(accountChoices([{ value: '1', label: 'Conto BPER' }])).toEqual([
            { value: '', label: 'All the accounts' },
            { value: '1', label: 'Conto BPER' },
        ]);
    });
});
