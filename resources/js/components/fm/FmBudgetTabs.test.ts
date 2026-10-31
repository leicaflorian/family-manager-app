import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmBudgetTabs from './FmBudgetTabs.vue';

describe('FmBudgetTabs', () => {
    it('leads to the three pages of the budget', () => {
        render(FmBudgetTabs, { props: { current: 'movements' } });

        expect(screen.getByRole('link', { name: 'Movements' })).toHaveAttribute(
            'href',
            '/budget/movements',
        );
        expect(
            screen.getByRole('link', { name: 'Planned and spent' }),
        ).toHaveAttribute('href', '/budget/comparison');
        expect(
            screen.getByRole('link', { name: 'Planned expenses' }),
        ).toHaveAttribute('href', '/budget/planned');
    });

    it('marks the page that is open and only that', () => {
        render(FmBudgetTabs, { props: { current: 'comparison' } });

        expect(
            screen.getByRole('link', { name: 'Planned and spent' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: 'Movements' }),
        ).not.toHaveAttribute('aria-current');
        expect(
            screen.getByRole('link', { name: 'Planned expenses' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('has no tab for the consumption yet', () => {
        render(FmBudgetTabs, { props: { current: 'planned' } });

        expect(screen.getAllByRole('link')).toHaveLength(3);
    });
});
