import { describe, expect, it } from 'vite-plus/test';
import { budgetTabs } from './budgetTabs';

describe('budgetTabs', () => {
    it('lists the three pages of the budget', () => {
        expect(budgetTabs('movements').map((tab) => tab.href)).toEqual([
            '/budget/movements',
            '/budget/comparison',
            '/budget/planned',
        ]);
        expect(budgetTabs('movements').map((tab) => tab.label)).toEqual([
            'Movements',
            'Planned and spent',
            'Recurring',
        ]);
    });

    it('marks only the page that is open', () => {
        expect(budgetTabs('comparison').map((tab) => tab.current)).toEqual([
            false,
            true,
            false,
        ]);
    });

    it('adds From the bank with the number to sort while the link to the bank is on', () => {
        const tabs = budgetTabs('bank', { unsorted: 6 });

        expect(tabs.map((tab) => tab.href)).toEqual([
            '/budget/movements',
            '/budget/comparison',
            '/budget/planned',
            '/budget/bank',
        ]);
        expect(tabs[3]).toMatchObject({
            label: 'From the bank',
            count: 6,
            current: true,
        });
    });

    it('shows no number when nothing is to sort', () => {
        expect(budgetTabs('movements', { unsorted: 0 })[3]!.count).toBe(
            undefined,
        );
    });

    it('has no such tab while the link to the bank is off', () => {
        expect(budgetTabs('movements', null)).toHaveLength(3);
        expect(budgetTabs('movements')).toHaveLength(3);
    });
});
