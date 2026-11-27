import { describe, expect, it } from 'vite-plus/test';
import { budgetTabs } from './budgetTabs';

describe('budgetTabs', () => {
    it('has only Movements and Recurring', () => {
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
        expect(budgetTabs('movements').map((tab) => tab.current)).toEqual([
            true,
            false,
        ]);
    });
});
