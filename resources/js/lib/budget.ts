/**
 * The texts of the budget pages: how a due date repeats, how a category
 * compares with its plan, and where a due date stands.
 */
import { format } from '@formkit/tempo';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';
import { exceptions, payment, show } from '@/routes/budget/deadlines';
import { daysFromToday, formatDate, formatEuro, relativeDays } from './format';
import { listNames } from './names';

/** The unit of a recurrence. */
export type RecurrenceUnit = 'week' | 'month' | 'year';

/**
 * The day and month of a date, as `21/11`.
 *
 * @param date - a date of the server
 * @returns the date without the year
 */
export function shortDate(date: string): string {
    return formatDate(date).slice(0, 5);
}

/**
 * Says how a planned expense or income repeats.
 *
 * @param unit - week, month or year
 * @param interval - every how many units
 * @param start - the first due date, `yyyy-mm-dd`
 * @param recurring - whether it repeats; if not, it happens once
 * @param end - the last day it repeats, `yyyy-mm-dd`, or null
 * @returns for example «every month, on day 5 · until 10/02/2028»
 */
export function recurrenceText(
    unit: RecurrenceUnit,
    interval: number,
    start: string,
    recurring: boolean,
    end: string | null,
): string {
    if (!recurring) {
        return trans('once, on :date', { date: formatDate(start) });
    }

    const params = {
        count: String(interval),
        day: String(Number(start.slice(8, 10))),
        date: shortDate(start),
    };
    const rule = {
        week: () =>
            transChoice(
                '{1} every week|[2,*] every :count weeks',
                interval,
                params,
            ),
        month: () =>
            transChoice(
                '{1} every month, on day :day|[2,*] every :count months, on day :day',
                interval,
                params,
            ),
        year: () =>
            transChoice(
                '{1} every year, on :date|[2,*] every :count years, on :date',
                interval,
                params,
            ),
    }[unit]();

    return end === null
        ? rule
        : `${rule} · ${trans('until :date', { date: formatDate(end) })}`;
}

/**
 * Compares what was spent with what was planned, in a few words.
 *
 * @param spent - what was spent
 * @param planned - what was planned
 * @param estimated - whether the plan is only an estimate
 * @param lateTitle - the title of a due date that is late, if there is one
 * @returns for example «193,40 € less · luce late»
 */
export function comparisonComment(
    spent: number,
    planned: number,
    estimated: boolean,
    lateTitle: string | null,
): string {
    const difference = Math.round((spent - planned) * 100) / 100;
    const amount = formatEuro(Math.abs(difference));
    const parts = [
        difference === 0
            ? trans('same')
            : difference < 0
              ? trans(':amount less', { amount })
              : trans(':amount more', { amount }),
    ];

    if (planned === 0 && spent > 0) {
        parts.push(trans('not planned'));
    }

    if (estimated) {
        parts.push(trans('planned amount estimated'));
    }

    if (lateTitle !== null) {
        parts.push(trans(':title late', { title: lateTitle }));
    }

    return parts.join(' · ');
}

/**
 * Says where a due date stands: when it falls, how late it is, or when it was paid.
 *
 * @param deadline - the due date
 * @param today - the moment taken as now, the clock if missing
 * @returns for example «Due on 21/11/2026 · in 26 days»
 */
export function deadlineStatus(
    deadline: App.Data.DeadlineData,
    today: Date = new Date(),
): string {
    const income = deadline.kind === 'income';

    if (deadline.payment !== null) {
        return trans(income ? 'Received on :date' : 'Paid on :date', {
            date: shortDate(deadline.payment.date),
        });
    }

    if (deadline.late) {
        const days = Math.max(1, -daysFromToday(deadline.date, today));

        return transChoice(
            '{1} Overdue by :count day|[2,*] Overdue by :count days',
            days,
            { count: String(days) },
        );
    }

    return trans(income ? 'Arrives on :date · :when' : 'Due on :date · :when', {
        date: formatDate(deadline.date),
        when: relativeDays(deadline.date, today),
    });
}

/**
 * Writes a part of a month in the language of the page.
 *
 * @param month - `yyyy-mm`, or a longer date
 * @param pattern - the Tempo pattern, for example `MMMM YYYY`
 * @returns the text, as the language writes it (lower case in Italian)
 */
function monthPart(month: string, pattern: string): string {
    const [year, number] = month.split('-').map(Number);

    return format(new Date(year, number - 1, 1), pattern, getActiveLanguage());
}

/**
 * Upper-cases the first letter.
 *
 * @param text - the text
 * @returns the text, starting with a capital
 */
export function capitalized(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/**
 * The month and the year in the language of the page.
 *
 * @param month - `yyyy-mm`, or a longer date
 * @param capital - whether to start with a capital, as a title does
 * @returns for example «Ottobre 2026»
 */
export function monthName(month: string, capital = true): string {
    const text = monthPart(month, 'MMMM YYYY');

    return capital ? capitalized(text) : text;
}

/**
 * The name of the month alone.
 *
 * @param month - `yyyy-mm`, or a longer date
 * @returns for example «ottobre»
 */
export function longMonth(month: string): string {
    return monthPart(month, 'MMMM');
}

/**
 * The short name of a month, as under a chart.
 *
 * @param month - `yyyy-mm`
 * @returns for example «Ott»
 */
export function shortMonth(month: string): string {
    return capitalized(monthPart(month, 'MMM'));
}

/**
 * The addresses of a due date: its page, *I paid it* and *Change this due date*.
 * The day in them is the one the rules give, not the one an exception moved it to.
 *
 * @param deadline - the due date
 * @returns the address of the page and the two it sends its forms to
 */
export function deadlineUrls(deadline: App.Data.DeadlineData): {
    show: string;
    payment: string;
    exceptions: string;
} {
    const args = {
        kind: deadline.kind,
        id: deadline.planned_id,
        date: deadline.original_date,
    };

    return {
        show: show.url(args),
        payment: payment.url(args),
        exceptions: exceptions.url(args),
    };
}

/**
 * Names the months a planned item skips, for its head.
 *
 * @param months - the numbers of the months, 1 to 12, in the order they come
 * @returns for example «July and August skipped», empty if none
 */
export function skippedMonthsText(months: number[]): string {
    if (months.length === 0) {
        return '';
    }

    const names = months.map((month) =>
        longMonth(`2000-${String(month).padStart(2, '0')}`),
    );

    return transChoice(
        '{1} :months skipped|[2,*] :months skipped',
        names.length,
        { months: listNames(names) },
    );
}

/**
 * The due date that the exceptions of one day change, built from the planned
 * item: the sheet *Change this due date* needs it for a day that is not among
 * the next ones. The exceptions of the same day are gathered, as the server does.
 *
 * @param row - the planned item
 * @param exceptions - its exceptions
 * @param originalDate - the day the rules give, `yyyy-mm-dd`
 * @returns the due date, open (nothing says it is paid)
 */
export function deadlineOfException(
    row: App.Data.PlannedRowData,
    exceptions: App.Data.ExceptionData[],
    originalDate: string,
): App.Data.DeadlineData {
    const mine = exceptions.filter(
        (exception) => exception.occurrence_date === originalDate,
    );
    const amount = mine.find((exception) => exception.amount_override !== null);
    const moved = mine.find((exception) => exception.date_override !== null);

    return {
        kind: row.kind,
        planned_id: row.id,
        title: row.title,
        planned_title: row.title,
        date: moved?.date_override ?? originalDate,
        original_date: originalDate,
        amount: amount?.amount_override ?? row.amount,
        base_amount: amount ? row.amount : null,
        estimated: row.estimated,
        notes: mine.find((exception) => exception.notes)?.notes ?? null,
        late: false,
        payment: null,
        account_id: null,
        account: row.account,
        card: row.card,
        income_type: null,
        person: row.person,
    };
}

/** The values of the form of a planned expense or income, as the server sends and receives them. */
export interface PlannedFormValues {
    title: string;
    amount: number | string | null;
    is_estimated: boolean;
    notes: string | null;
    start_date: string | null;
    end_date: string | null;
    is_recurring: boolean;
    interval: number | string;
    interval_unit: RecurrenceUnit;
    organization_id: string | null;
    financial_account_id: string | null;
    person_id: string | null;
    /** An expense: the category. */
    category_id?: string | null;
    /** An expense: the card it refers to, `type:id`. */
    card?: string | null;
    /** An income: its type. */
    type?: string;
    /** An income: the employment it comes from. */
    employment_id?: string | null;
}

/** The choices of the form of a planned expense or income (`BudgetFormOptions::for()`). */
export interface PlannedFormOptions {
    accounts: App.Data.OptionData[];
    categories: App.Data.OptionData[];
    organizations: App.Data.OptionData[];
    people: App.Data.OptionData[];
    cards: App.Data.OptionData[];
    employments: App.Data.OptionData[];
    income_types: App.Data.OptionData[];
}
