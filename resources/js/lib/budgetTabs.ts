import { trans } from 'laravel-vue-i18n';
import type { Tab } from '@/components/fm/FmTabs.vue';
import { index as movements } from '@/routes/budget/movements';
import { index as plannedExpenses } from '@/routes/budget/planned';

/** A page of the budget that has its tab. */
export type BudgetPage = 'movements' | 'planned';

/**
 * The tabs of the budget pages, shared by all so none builds its own. *From the bank* is a page of its own under the
 * Budget (the sidebar and *More in the budget* lead there), so it is not one of them.
 *
 * @param current - the page that is open
 * @returns the tabs, in order, with the open one marked
 */
export function budgetTabs(current: BudgetPage): Tab[] {
    const pages: { page: BudgetPage; label: string; href: string }[] = [
        { page: 'movements', label: trans('Movements'), href: movements().url },
        {
            page: 'planned',
            label: trans('Recurring'),
            href: plannedExpenses().url,
        },
    ];

    return pages.map(({ page, label, href }) => ({
        label,
        href,
        current: page === current,
    }));
}
