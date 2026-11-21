<script setup lang="ts">
/**
 * The tabs at the top of a set of pages (the three of the budget, *To do* and
 * *Completed* of the Agenda). The page lists its tabs; the one of the open
 * page is marked, and a tab can show a number after its name, and one with nothing behind it can be shown soft and without a link (`disabled`). They are lines
 * under the names (`underline`) or, as in the Agenda, a grey pill with the open
 * tab in white (`pill`). A number of things late (`countLabel`) is a red badge,
 * as the *Agenda* shows the late ones beside *To do*; a number of things to look
 * at (`countInfo`, with `countLabel`) is a blue one, as *From the bank* shows the
 * movements to sort. A tab with an `icon`
 * shows only the icon, in a square, and its name is for screen readers and the
 * tooltip: the toggle «List / Calendar» of the Agenda.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

/** One tab. */
export type Tab = {
    /** The name of the tab. */
    label: string;
    /** The address of its page. */
    href: string;
    /** A number shown after the name, for example the reminders to do. */
    count?: number;
    /**
     * The number is of things late: it shows as a red badge, and these words
     * name the tab with it for screen readers («To do, 3 late»).
     */
    countLabel?: string;
    /**
     * With `countLabel`: the number is of things to look at, not late, and the
     * badge is blue (`--action`) instead of red.
     */
    countInfo?: boolean;
    /** The tab is the one of the open page. */
    current: boolean;
    /** An icon: the tab shows only it (18 px, in a square), and `label` becomes its name and tooltip. */
    icon?: LucideIcon;
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

/** Every tab is an icon: the set does not grow to the width of its container. */
const iconsOnly = computed(
    (): boolean => tabs.length > 0 && tabs.every((tab) => tab.icon),
);

/**
 * The classes of a tab, dark with a line under it if it is the open one.
 *
 * @param marked - the tab is the one of the open page
 * @param disabled - the tab cannot be chosen: soft, with no hover
 * @param icon - the tab shows only an icon, in a fixed square
 */
function tabClasses(marked: boolean, disabled = false, icon = false): string {
    if (variant === 'pill') {
        return cn(
            // Equal tabs while they fit; a longer name widens its tab instead of going on two lines
            icon
                ? 'size-9 shrink-0 lg:size-10'
                : 'h-10 min-w-fit flex-1 px-2 whitespace-nowrap',
            'flex items-center justify-center gap-1 rounded-tag text-body-sm no-underline',
            marked
                ? 'bg-card text-label text-ink shadow-raised'
                : cn(
                      'text-ink-soft',
                      disabled ? 'opacity-50' : 'hover:text-ink',
                  ),
        );
    }

    return cn(
        'flex min-h-11 shrink-0 items-center gap-1.5 border-b-2 px-3 text-body-sm no-underline',
        marked
            ? 'border-action text-label text-action-dark'
            : cn(
                  'border-transparent text-ink-soft',
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
                ? cn(
                      'flex gap-1.5 rounded-lg bg-segment p-1',
                      iconsOnly && 'flex-none',
                  )
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
                :aria-label="tab.icon ? tab.label : tab.countLabel"
                :title="tab.icon ? tab.label : undefined"
                :class="tabClasses(tab.current, false, !!tab.icon)"
            >
                <FmIcon v-if="tab.icon" :icon="tab.icon" :size="18" />
                {{ tab.icon ? '' : tab.label }}
                <span
                    v-if="tab.count !== undefined && tab.countLabel"
                    aria-hidden="true"
                    :class="
                        cn(
                            'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-badge',
                            tab.countInfo
                                ? 'bg-action text-on-action'
                                : 'bg-overdue text-on-hero',
                        )
                    "
                    >{{ tab.count }}</span
                >
                <span
                    v-else-if="tab.count !== undefined"
                    class="font-semibold"
                    >{{ tab.count }}</span
                >
            </Link>
        </template>
    </nav>
</template>
