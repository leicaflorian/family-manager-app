import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FilterPills from './FilterPills.vue';

const pills = [
    { key: 'amount', label: 'From 10,00 € to 100,00 €' },
    { key: 'search', label: '“luce”' },
];

describe('FilterPills', () => {
    it('has a pill for each filter, and tells which one its × removes', async () => {
        const view = renderPage(FilterPills, { pills });

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filter “luce”' }),
        );

        expect(view.emitted('remove')).toEqual([['search']]);
    });

    it('removes them all, and says what was found only when told', async () => {
        const view = renderPage(FilterPills, {
            pills,
            found: '2 planned expenses, by due date',
        });

        expect(
            screen.getByText('2 planned expenses, by due date'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Remove the filters' }),
        );
        expect(view.emitted('removeAll')).toHaveLength(1);

        view.unmount();
        renderPage(FilterPills, { pills });
        expect(screen.queryByText(/by due date/)).not.toBeInTheDocument();
    });
});
