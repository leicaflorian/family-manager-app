/**
 * The words and the bars of the Consumption page: the name and the dates of a bill, the months of the chart in
 * consumption or in euros, the line under the chart about the months a bill covers only in part or not at all, and
 * what reading the bills costs.
 */
import { trans } from 'laravel-vue-i18n';
import { formatDate, formatDayMonth } from '@/lib/format';
import { capitalized, longMonth, monthName } from '@/lib/budget';
import type { ChartMonth } from '@/lib/charts';

/** What the bars of the chart are: the consumption, or the euros. */
export type ConsumptionView = 'quantity' | 'amount';

/**
 * The months a bill covers, in words.
 *
 * @param start - its first day, `yyyy-mm-dd`
 * @param end - its last day, `yyyy-mm-dd`
 * @returns «settembre 2026», «agosto – ottobre 2026» or «dicembre 2025 – gennaio 2026»
 */
export function billMonths(start: string, end: string): string {
    const first = start.slice(0, 7);
    const last = end.slice(0, 7);

    if (first === last) {
        return monthName(first, false);
    }

    return first.slice(0, 4) === last.slice(0, 4)
        ? `${longMonth(first)} – ${monthName(last, false)}`
        : `${monthName(first, false)} – ${monthName(last, false)}`;
}

/**
 * The name of a bill, from the months it covers.
 *
 * @param bill - the bill
 * @returns «Bill of September 2026»
 */
export function billTitle(
    bill: Pick<
        App.Data.Consumption.ConsumptionBillData,
        'period_start' | 'period_end'
    >,
): string {
    return trans('Bill of :period', {
        period: billMonths(bill.period_start, bill.period_end),
    });
}

/**
 * The days of a bill, the year written once when it is the same.
 *
 * @param start - its first day, `yyyy-mm-dd`
 * @param end - its last day, `yyyy-mm-dd`
 * @returns «01/09 – 30/09/2026», or «01/12/2025 – 31/01/2026»
 */
export function billDates(start: string, end: string): string {
    return start.slice(0, 4) === end.slice(0, 4)
        ? `${formatDayMonth(start)} – ${formatDate(end)}`
        : `${formatDate(start)} – ${formatDate(end)}`;
}

/**
 * The months of the page as bars of the chart: the consumption against the same month of the year before (with
 * its euros said beside), or the euros against the euros of the year before. A month no bill covers has no bar.
 *
 * @param months - the months of the page, oldest first
 * @param view - what the bars are
 * @returns the months of the chart
 */
export function chartMonths(
    months: readonly App.Data.Consumption.ConsumptionMonthData[],
    view: ConsumptionView,
): ChartMonth[] {
    return months.map((month): ChartMonth => {
        const empty = month.coverage === 'none';

        return {
            month: month.month,
            value: empty
                ? null
                : view === 'quantity'
                  ? month.quantity
                  : month.amount,
            mark:
                view === 'quantity'
                    ? month.previousQuantity
                    : month.previousAmount,
            amount: view === 'quantity' ? month.amount : undefined,
            partial: month.coverage === 'partial',
            estimated: month.estimated,
            note:
                month.coveredUntil === null
                    ? undefined
                    : trans('until day :day', {
                          day: String(month.coveredUntil),
                      }),
        };
    });
}

/**
 * The line under the chart: the months covered only up to a day, and the months without bills, each run of them
 * said once.
 *
 * @param months - the months of the page, oldest first
 * @returns for example «October: until day 14. From November to January there are no bills of this utility.»,
 *     or an empty text when every month is covered
 */
export function chartNote(
    months: readonly App.Data.Consumption.ConsumptionMonthData[],
): string {
    const parts: string[] = [];
    let run: string[] = [];

    /** Says the run of months without bills, if there is one, and starts a new one. */
    const closeRun = (): void => {
        const [first] = run;
        const last = run.at(-1);

        if (first !== undefined && last !== undefined) {
            parts.push(
                first === last
                    ? trans('In :month there are no bills of this utility.', {
                          month: longMonth(first),
                      })
                    : trans(
                          'From :from to :to there are no bills of this utility.',
                          { from: longMonth(first), to: longMonth(last) },
                      ),
            );
        }

        run = [];
    };

    for (const month of months) {
        if (month.coverage === 'none') {
            run.push(month.month);
            continue;
        }

        closeRun();

        if (month.coveredUntil !== null) {
            parts.push(
                trans(':month: until day :day.', {
                    month: capitalized(longMonth(month.month)),
                    day: String(month.coveredUntil),
                }),
            );
        }
    }

    closeRun();

    return parts.join(' ');
}

/** Two decimals, the Italian way. */
const dollars = new Intl.NumberFormat('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * Writes what the readings of the bills probably cost: the services that read them are paid in dollars.
 *
 * @param cost - the dollars
 * @returns for example `0,09 $`
 */
export function formatDollars(cost: number): string {
    return `${dollars.format(cost)} $`;
}
