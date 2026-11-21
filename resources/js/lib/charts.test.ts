import { describe, expect, it } from 'vite-plus/test';
import { gridStep, toChartMonth } from './charts';

describe('gridStep', () => {
    it('keeps the steps in euros the chart of the budget always had', () => {
        expect(gridStep(1710.3, 100)).toBe(500);
        expect(gridStep(1, 100)).toBe(100);
        expect(gridStep(380, 100)).toBe(100);
        expect(gridStep(9000, 100)).toBe(2500);
    });

    it('finds round steps for small quantities too', () => {
        expect(gridStep(290)).toBe(100);
        expect(gridStep(120)).toBe(50);
        expect(gridStep(18)).toBe(5);
        expect(gridStep(3.2)).toBe(1);
        expect(gridStep(0.8)).toBe(0.25);
    });
});

describe('toChartMonth', () => {
    it('turns a month of *Planned and spent* into a month of the chart', () => {
        expect(
            toChartMonth({
                month: '2026-10',
                spent: 1284.2,
                planned: 1469.3,
                current: true,
            }),
        ).toEqual({
            month: '2026-10',
            value: 1284.2,
            mark: 1469.3,
            current: true,
        });
    });

    it('leaves a month of the chart as it is', () => {
        const month = { month: '2026-01', value: 290, mark: null };

        expect(toChartMonth(month)).toBe(month);
    });
});
