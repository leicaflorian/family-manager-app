import { describe, expect, it } from 'vite-plus/test';
import { budgetTabs } from './budgetTabs';

describe('budgetTabs', () => {
    it('lists the pages of the budget', () => {
        expect(budgetTabs('movements').map((tab) => tab.href)).toEqual([
            '/budget/movements',
            '/budget/planned',
        ]);
        expect(budgetTabs('movements').map((tab) => tab.label)).toEqual([
            'Movements',
            'Recurring',
        ]);
    });

    it('marks only the page that is open', () => {
        expect(budgetTabs('planned').map((tab) => tab.current)).toEqual([
            false,
            true,
        ]);
    });

    it('adds From the bank with the number to sort while the link to the bank is on', () => {
        const tabs = budgetTabs('bank', { unsorted: 6 });

        expect(tabs.map((tab) => tab.href)).toEqual([
            '/budget/movements',
            '/budget/planned',
            '/budget/bank',
        ]);
        expect(tabs[2]).toMatchObject({
            label: 'From the bank',
            count: 6,
            countLabel: 'From the bank, 6 to sort',
            countInfo: true,
            current: true,
        });
    });

    it('shows no number when nothing is to sort', () => {
        expect(budgetTabs('movements', { unsorted: 0 })[2]!.count).toBe(
            undefined,
        );
    });

    it('has no such tab while the link to the bank is off', () => {
        expect(budgetTabs('movements', null)).toHaveLength(2);
        expect(budgetTabs('movements')).toHaveLength(2);
    });
});
