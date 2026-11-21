import { describe, expect, it } from 'vite-plus/test';
import { consumptionPage } from '@/test/fixtures';
import {
    billDates,
    billMonths,
    billTitle,
    chartMonths,
    chartNote,
    formatDollars,
} from './consumption';

const [october, november, december] = consumptionPage.months as [
    App.Data.Consumption.ConsumptionMonthData,
    App.Data.Consumption.ConsumptionMonthData,
    App.Data.Consumption.ConsumptionMonthData,
];

/** A month no bill covers. */
const none = (
    month: App.Data.Consumption.ConsumptionMonthData,
): App.Data.Consumption.ConsumptionMonthData => ({
    ...month,
    quantity: null,
    amount: null,
    price: null,
    coverage: 'none',
    billIds: [],
});

describe('billMonths', () => {
    it('names one month, the months of a year, or the months across two years', () => {
        expect(billMonths('2026-09-01', '2026-09-30')).toBe('September 2026');
        expect(billMonths('2026-08-01', '2026-10-14')).toBe(
            'August – October 2026',
        );
        expect(billMonths('2025-12-01', '2026-01-31')).toBe(
            'December 2025 – January 2026',
        );
    });
});

describe('billTitle', () => {
    it('names the bill by its months', () => {
        expect(
            billTitle({ period_start: '2026-01-01', period_end: '2026-01-31' }),
        ).toBe('Bill of January 2026');
    });
});

describe('billDates', () => {
    it('writes the year once when it is the same', () => {
        expect(billDates('2026-09-01', '2026-09-30')).toBe(
            '01/09 – 30/09/2026',
        );
        expect(billDates('2025-12-01', '2026-01-31')).toBe(
            '01/12/2025 – 31/01/2026',
        );
    });
});

describe('chartMonths', () => {
    it('makes the consumption the bar, last year the mark and the euros said beside', () => {
        expect(chartMonths([october], 'quantity')).toEqual([
            {
                month: '2025-10',
                value: 214,
                mark: 198,
                amount: 65.27,
                partial: false,
                estimated: false,
                note: undefined,
            },
        ]);
    });

    it('makes the euros the bar and last year’s euros the mark', () => {
        expect(chartMonths([october], 'amount')[0]).toMatchObject({
            value: 65.27,
            mark: 55.84,
            amount: undefined,
        });
    });

    it('draws no bar for a month without bills, and tells a month covered up to a day', () => {
        const [empty, part] = chartMonths(
            [
                none(october),
                {
                    ...november,
                    coverage: 'partial',
                    coveredUntil: 14,
                    estimated: true,
                },
            ],
            'quantity',
        );

        expect(empty!.value).toBeNull();
        expect(part).toMatchObject({
            partial: true,
            estimated: true,
            note: 'until day 14',
        });
    });
});

describe('chartNote', () => {
    it('says nothing when every month is covered', () => {
        expect(chartNote([october, november, december])).toBe('');
    });

    it('tells the month covered up to a day and each run of months without bills', () => {
        expect(
            chartNote([
                none(october),
                none(november),
                { ...december, coverage: 'partial', coveredUntil: 14 },
                none({ ...december, month: '2026-01' }),
            ]),
        ).toBe(
            'From October to November there are no bills of this utility. December: until day 14. In January there are no bills of this utility.',
        );
    });
});

describe('formatDollars', () => {
    it('writes what the readings cost, in dollars with two decimals', () => {
        expect(formatDollars(0.09)).toBe('0,09 $');
        expect(formatDollars(0.016)).toBe('0,02 $');
    });
});
