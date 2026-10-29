<script setup lang="ts">
/**
 * The tabs at the top of the budget pages: *Movements*, *Planned and spent* and
 * *Planned expenses*. The one of the open page is marked.
 */
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { comparison } from '@/routes/budget';
import { index as movements } from '@/routes/budget/movements';
import { index as plannedExpenses } from '@/routes/budget/planned';

/** A page of the budget. */
type BudgetPage = 'movements' | 'comparison' | 'planned';

const { current } = defineProps<{
    /** The page that is open. */
    current: BudgetPage;
}>();

/** The tabs, in order. */
const tabs = computed(
    (): { page: BudgetPage; label: string; href: string }[] => [
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
    ],
);

/**
 * The classes of a tab, dark with a line under it if it is the open one.
 *
 * @param marked - the tab is the one of the open page
 */
function tabClasses(marked: boolean): string {
    return cn(
        'flex min-h-11 shrink-0 items-center border-b-2 px-3 text-[15px] no-underline',
        marked
            ? 'border-action font-bold text-action-dark'
            : 'border-transparent font-medium text-ink-soft hover:text-ink',
    );
}
</script>

<template>
    <nav
        :aria-label="$t('Budget')"
        class="-mx-1 flex gap-1 overflow-x-auto border-b border-divider"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.page"
            :href="tab.href"
            :aria-current="tab.page === current ? 'page' : undefined"
            :class="tabClasses(tab.page === current)"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
