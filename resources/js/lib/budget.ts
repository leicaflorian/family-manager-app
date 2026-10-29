/**
 * The texts of the budget pages: how a due date repeats, how a category
 * compares with its plan, and where a due date stands.
 */
import { format } from '@formkit/tempo';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';
import { exceptions, payment, show } from '@/routes/budget/deadlines';
import { index as movementsIndex } from '@/routes/budget/movements';
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
        // A name like «Luce» reads in the middle of the sentence; an acronym like «IMU» keeps its capitals
        const title = /^\p{Lu}\p{Ll}/u.test(lateTitle)
            ? lateTitle.charAt(0).toLowerCase() + lateTitle.slice(1)
            : lateTitle;
        parts.push(trans(':title late', { title }));
    }

    return parts.join(' · ');
}

/**
 * Says where a due date stands: skipped, when it falls, how late it is, or when it was paid.
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

    if (deadline.skipped && deadline.payment === null) {
        return trans('Skipped');
    }

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
    /** The card type of each category that has one, by the id of the category. */
    category_card_types: Record<string, string>;
    /** The name of each card type, by type. */
    card_type_labels: Record<string, string>;
    employments: App.Data.OptionData[];
    income_types: App.Data.OptionData[];
}

/** The choices of the form of a movement (`BudgetFormOptions::forMovement()`). */
export interface MovementFormOptions {
    accounts: App.Data.OptionData[];
    categories: App.Data.OptionData[];
    organizations: App.Data.OptionData[];
    people: App.Data.OptionData[];
    planned: App.Data.PlannedChoiceData[];
    cards: App.Data.OptionData[];
    /** The card type of each category that has one, by the id of the category. */
    category_card_types: Record<string, string>;
    /** The name of each card type, by type. */
    card_type_labels: Record<string, string>;
    employments: App.Data.OptionData[];
    income_types: App.Data.OptionData[];
}

/** What a new movement starts from: its kind, the planned item it settles and the day. */
export interface MovementStart {
    /** `expense` or `income`. */
    kind: string;
    /** The planned item, as text, or null. */
    planned_id: string | null;
    /** The day, `yyyy-mm-dd`. */
    date: string;
    /** The card a new expense comes from (*Add expense* of a card), `type:id`. */
    card?: string | null;
    /** The category of the type of that card, as text. */
    category_id?: string | null;
}

/**
 * The short name of the weekday of a date, as a list of movements shows it.
 *
 * @param date - `yyyy-mm-dd`
 * @returns for example «sab» in Italian, «Sat» in English
 */
export function weekdayShort(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return format(new Date(year, month - 1, day), 'ddd', getActiveLanguage());
}

/** What *Movements* shows: the month and, if chosen, the kind, the account and the movement open beside the list. */
export interface MovementsQuery {
    /** `yyyy-mm`. */
    month: string;
    /** `expense` or `income`, or null for both. */
    kind?: string | null;
    /** The id of the account, or null for all. */
    account?: number | null;
    /** The movement open beside the list, `kind:id`. */
    movement?: string | null;
}

/**
 * The address of *Movements* for a month and the filters in use. What is empty is left out.
 *
 * @param query - the month, the filters and the open movement
 * @returns for example `/budget/movements?month=2026-10&kind=expense`
 */
export function movementsUrl(query: MovementsQuery): string {
    return movementsIndex.url({
        query: Object.fromEntries(
            Object.entries(query).filter(
                ([, value]) => value !== null && value !== undefined,
            ),
        ),
    });
}
