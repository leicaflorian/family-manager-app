<script setup lang="ts">
/**
 * The frame of a page of the *Settings*: the title on top and the content under it.
 * In the wide layout the sections of the settings stand beside the content, with the
 * section of the page marked, as the wide boards of the settings draw them; on the
 * phone there are none (the page has *Back* and the bottom bar).
 *
 * @group sections
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    CalendarSync,
    FolderTree,
    LayoutGrid,
    LifeBuoy,
    Mail,
    Sparkles,
    Tags,
    User,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useWideLayout } from '@/composables/useWideLayout';
import { edit as account } from '@/routes/account';
import { edit as calendar } from '@/routes/calendar';
import { index as categories } from '@/routes/categories';
import { index as expenseCategories } from '@/routes/expense-categories';
import { edit as mail } from '@/routes/mail-settings';
import { edit as areas } from '@/routes/settings/areas';
import { news } from '@/routes/settings';
import { edit as notices } from '@/routes/settings/notices';
import { edit as support } from '@/routes/settings/support';
import FmIcon from './FmIcon.vue';

/** A section of the settings, as the page names itself. */
type SettingsSection =
    | 'account'
    | 'notices'
    | 'mail'
    | 'calendar'
    | 'categories'
    | 'expense-categories'
    | 'areas'
    | 'support'
    | 'news';

const { current } = defineProps<{
    /** The section the page is, marked in the list. */
    current: SettingsSection;
}>();

defineSlots<{
    /** The title of the page (`FmTitle`). */
    title(): unknown;
    /** The content of the page. */
    default(): unknown;
}>();

const wide = useWideLayout();

/** The sections of the settings, in the order of the boards; *Support* and *What's new* after a line. */
const sections = computed(
    (): {
        key: SettingsSection;
        label: string;
        icon: LucideIcon;
        href: string;
        apart?: boolean;
    }[] => [
        {
            key: 'account',
            label: trans('My account'),
            icon: User,
            href: account().url,
        },
        {
            key: 'notices',
            label: trans('Notices'),
            icon: Bell,
            href: notices().url,
        },
        {
            key: 'mail',
            label: trans('Address for the bills'),
            icon: Mail,
            href: mail().url,
        },
        {
            key: 'calendar',
            label: trans('Calendar'),
            icon: CalendarSync,
            href: calendar().url,
        },
        {
            key: 'categories',
            label: trans('Document categories'),
            icon: FolderTree,
            href: categories().url,
        },
        {
            key: 'expense-categories',
            label: trans('Expense categories'),
            icon: Tags,
            href: expenseCategories().url,
        },
        {
            key: 'areas',
            label: trans('Areas'),
            icon: LayoutGrid,
            href: areas().url,
        },
        {
            key: 'support',
            label: trans('Support'),
            icon: LifeBuoy,
            href: support().url,
            apart: true,
        },
        {
            key: 'news',
            label: trans("What's new"),
            icon: Sparkles,
            href: news().url,
        },
    ],
);
</script>

<template>
    <div v-if="wide" class="flex flex-col gap-6">
        <slot name="title" />
        <div class="flex flex-wrap items-start gap-6">
            <nav
                :aria-label="$t('Settings sections')"
                class="flex min-w-60 flex-[0_1_270px] flex-col gap-0.5 rounded-card bg-card p-2.5 shadow-card"
            >
                <template v-for="section in sections" :key="section.key">
                    <span
                        v-if="section.apart"
                        aria-hidden="true"
                        class="mx-2 my-1.5 h-px bg-divider"
                    />
                    <Link
                        :href="section.href"
                        :aria-current="
                            section.key === current ? 'page' : undefined
                        "
                        class="flex min-h-touch items-center gap-3 rounded-md px-3 no-underline"
                        :class="
                            section.key === current
                                ? 'bg-action-soft text-label text-action-dark'
                                : 'text-body-sm text-ink hover:bg-canvas'
                        "
                    >
                        <FmIcon :icon="section.icon" :size="20" />
                        {{ section.label }}
                    </Link>
                </template>
            </nav>
            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                <slot />
            </div>
        </div>
    </div>
    <div v-else class="flex max-w-[720px] flex-col gap-5">
        <slot name="title" />
        <slot />
    </div>
</template>
