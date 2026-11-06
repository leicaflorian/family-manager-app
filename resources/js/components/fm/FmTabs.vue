<script setup lang="ts">
/**
 * The tabs at the top of a set of pages (the three of the budget, *Deadlines*
 * and *Reminders* of the Agenda). The page lists its tabs; the one of the open
 * page is marked, and a tab can show a number after its name, and one with nothing behind it can be shown soft and without a link (`disabled`). They are lines
 * under the names (`underline`) or, as in the Agenda, a grey pill with the open
 * tab in white (`pill`).
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
    /** The tab cannot be chosen (no files for it): its name is shown without a link. */
    disabled?: boolean;
};

const {
    tabs,
    label,
    variant = 'underline',
} = defineProps<{
    /** The tabs, in order. */
    tabs: Tab[];
    /** What the set of tabs is, for those who cannot see it: «Budget». */
    label: string;
    /** `underline` (the default) or `pill`, a grey strip with the open tab in white. */
    variant?: 'underline' | 'pill';
}>();

/**
 * The classes of a tab, dark with a line under it if it is the open one.
 *
 * @param marked - the tab is the one of the open page
 * @param disabled - the tab cannot be chosen: soft, with no hover
 */
function tabClasses(marked: boolean, disabled = false): string {
    if (variant === 'pill') {
        return cn(
            'flex h-10 flex-1 items-center justify-center gap-1 rounded-[9px] text-[15px] no-underline',
            marked
                ? 'bg-card font-bold text-ink shadow-raised'
                : cn(
                      'font-medium text-ink-soft',
                      disabled ? 'opacity-50' : 'hover:text-ink',
                  ),
        );
    }

    return cn(
        'flex min-h-11 shrink-0 items-center gap-1.5 border-b-2 px-3 text-[15px] no-underline',
        marked
            ? 'border-action font-bold text-action-dark'
            : cn(
                  'border-transparent font-medium text-ink-soft',
                  disabled ? 'opacity-50' : 'hover:text-ink',
              ),
    );
}
</script>

<template>
    <nav
        :aria-label="label"
        :class="
            variant === 'pill'
                ? 'flex gap-1.5 rounded-lg bg-segment p-1'
                : '-mx-1 flex gap-1 overflow-x-auto border-b border-divider'
        "
    >
        <template v-for="tab in tabs" :key="tab.href">
            <span
                v-if="tab.disabled && !tab.current"
                aria-disabled="true"
                :class="tabClasses(false, true)"
            >
                {{ tab.label }}
                <span v-if="tab.count !== undefined" class="font-semibold">{{
                    tab.count
                }}</span>
            </span>
            <Link
                v-else
                :href="tab.href"
                :aria-current="tab.current ? 'page' : undefined"
                :class="tabClasses(tab.current)"
            >
                {{ tab.label }}
                <span v-if="tab.count !== undefined" class="font-semibold">{{
                    tab.count
                }}</span>
            </Link>
        </template>
    </nav>
</template>
