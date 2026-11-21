import { trans } from 'laravel-vue-i18n';
import type { Tab } from '@/components/fm/FmTabs.vue';
import type { BankFeedShare } from '@/composables/useBankFeed';
import { comparison } from '@/routes/budget';
import { index as bankTransactions } from '@/routes/budget/bank';
import { index as movements } from '@/routes/budget/movements';
import { index as plannedExpenses } from '@/routes/budget/planned';

/** A page of the budget. */
export type BudgetPage = 'movements' | 'comparison' | 'planned' | 'bank';

/**
 * The tabs of the budget pages, shared by all so none builds its own. *From the bank* comes last, and only while the
 * family has the link to the bank on, with the number still to sort as a blue badge.
 *
 * @param current - the page that is open
 * @param bankFeed - what the server shares about the link to the bank (`useBankFeed()`); null or missing while it is off
 * @returns the tabs, in order, with the open one marked
 */
export function budgetTabs(
    current: BudgetPage,
    bankFeed: BankFeedShare | null = null,
): Tab[] {
    const pages: {
        page: BudgetPage;
        label: string;
        href: string;
        count?: number;
        countLabel?: string;
    }[] = [
        { page: 'movements', label: trans('Movements'), href: movements().url },
        {
            page: 'comparison',
            label: trans('Planned and spent'),
            href: comparison().url,
        },
        {
            page: 'planned',
            label: trans('Recurring'),
            href: plannedExpenses().url,
        },
    ];

    if (bankFeed !== null) {
        pages.push({
            page: 'bank',
            label: trans('From the bank'),
            href: bankTransactions().url,
            ...(bankFeed.unsorted > 0
                ? {
                      count: bankFeed.unsorted,
                      countLabel: trans('From the bank, :count to sort', {
                          count: String(bankFeed.unsorted),
                      }),
                  }
                : {}),
        });
    }

    return pages.map(({ page, label, href, count, countLabel }) => ({
        label,
        href,
        ...(count === undefined ? {} : { count }),
        // The movements to sort are a blue badge, as the board draws it
        ...(countLabel === undefined ? {} : { countLabel, countInfo: true }),
        current: page === current,
    }));
}
