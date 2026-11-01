import { trans } from 'laravel-vue-i18n';
import type { Tab } from '@/components/fm/FmTabs.vue';
import { comparison } from '@/routes/budget';
import { index as movements } from '@/routes/budget/movements';
import { index as plannedExpenses } from '@/routes/budget/planned';

/** A page of the budget. */
export type BudgetPage = 'movements' | 'comparison' | 'planned';

/**
 * The tabs of the budget pages, shared by the three so none builds its own.
 *
 * @param current - the page that is open
 * @returns the tabs, in order, with the open one marked
 */
export function budgetTabs(current: BudgetPage): Tab[] {
    const pages: { page: BudgetPage; label: string; href: string }[] = [
        { page: 'movements', label: trans('Movements'), href: movements().url },
        {
            page: 'comparison',
            label: trans('Planned and spent'),
            href: comparison().url,
        },
        {
            page: 'planned',
            label: trans('Planned expenses'),
            href: plannedExpenses().url,
        },
    ];

    return pages.map(({ page, label, href }) => ({
        label,
        href,
        current: page === current,
    }));
}
