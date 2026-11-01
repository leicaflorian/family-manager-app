<script setup lang="ts">
/**
 * The tabs at the top of a set of pages (the three of the budget, *Deadlines*
 * and *Reminders* of the Agenda). The page lists its tabs; the one of the open
 * page is marked, and a tab can show a number after its name.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

/** One tab. */
export type Tab = {
    /** The name of the tab. */
    label: string;
    /** The address of its page. */
    href: string;
    /** A number shown after the name, for example the reminders to do. */
    count?: number;
    /** The tab is the one of the open page. */
    current: boolean;
};

const { tabs, label } = defineProps<{
    /** The tabs, in order. */
    tabs: Tab[];
    /** What the set of tabs is, for those who cannot see it: «Budget». */
    label: string;
}>();

/**
 * The classes of a tab, dark with a line under it if it is the open one.
 *
 * @param marked - the tab is the one of the open page
 */
function tabClasses(marked: boolean): string {
    return cn(
        'flex min-h-11 shrink-0 items-center gap-1.5 border-b-2 px-3 text-[15px] no-underline',
        marked
            ? 'border-action font-bold text-action-dark'
            : 'border-transparent font-medium text-ink-soft hover:text-ink',
    );
}
</script>

<template>
    <nav
        :aria-label="label"
        class="-mx-1 flex gap-1 overflow-x-auto border-b border-divider"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            :aria-current="tab.current ? 'page' : undefined"
            :class="tabClasses(tab.current)"
        >
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="font-semibold">{{
                tab.count
            }}</span>
        </Link>
    </nav>
</template>
