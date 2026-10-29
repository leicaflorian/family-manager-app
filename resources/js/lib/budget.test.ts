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
    comparisonComment,
    deadlineStatus,
    deadlineUrls,
    monthName,
    recurrenceText,
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
