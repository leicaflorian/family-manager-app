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
            'Planned expenses',
        ]);
    });

    it('marks only the page that is open', () => {
        expect(budgetTabs('comparison').map((tab) => tab.current)).toEqual([
            false,
            true,
            false,
        ]);
    });
});
