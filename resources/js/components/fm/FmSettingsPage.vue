<script setup lang="ts">
/**
 * The frame of a page of the *Settings*: the title on top and the content under it.
 * In the wide layout *Settings* is the title on top, the sections of the settings
 * stand beside the content in the groups of the index (with the section of the page
 * marked), and the title of the page is the title of its section, above its content,
 * as the wide boards of the settings draw them; on the phone there are no sections
 * (the page has *Back* and the bottom bar) and the title of the page is the title.
 *
 * @group sections
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    CalendarSync,
    FolderTree,
    House,
    Landmark,
    LayoutGrid,
    LifeBuoy,
    Mail,
    Sparkles,
    Tags,
    User,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, provide } from 'vue';
import { useBankFeed } from '@/composables/useBankFeed';
import { useWideLayout } from '@/composables/useWideLayout';
import { edit as account } from '@/routes/account';
import { index as banks } from '@/routes/banks';
import { edit as calendar } from '@/routes/calendar';
import { index as categories } from '@/routes/categories';
import { index as expenseCategories } from '@/routes/expense-categories';
import { edit as mail } from '@/routes/mail-settings';
import { edit as areas } from '@/routes/settings/areas';
import { edit as family } from '@/routes/settings/family';
import { news } from '@/routes/settings';
import { edit as notices } from '@/routes/settings/notices';
import { edit as support } from '@/routes/settings/support';
import { SECTION_TITLE } from '@/lib/titles';
import FmIcon from './FmIcon.vue';

/** A section of the settings, as the page names itself. */
type SettingsSection =
    | 'account'
    | 'family'
    | 'notices'
    | 'mail'
    | 'banks'
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

/** The link to the bank: its section is in the list only while the family has it on. */
const bankFeed = useBankFeed();

provide(SECTION_TITLE, wide);

/** A section of the settings in the list beside the page. */
interface SectionLink {
    key: SettingsSection;
    label: string;
    icon: LucideIcon;
    href: string;
    /** A short note after the label, on the right: the version that runs, for *What's new*. */
    note?: string;
}

/** The sections of the settings in the groups of the index: mine first, without a name. */
const groups = computed(
    (): { label: string | null; sections: SectionLink[] }[] => [
        {
            label: null,
            sections: [
                {
                    key: 'account',
                    label: trans('My account'),
                    icon: User,
                    href: account().url,
                },
                {
                    key: 'calendar',
                    label: trans('Calendar'),
                    icon: CalendarSync,
                    href: calendar().url,
                },
            ],
        },
        {
            label: trans('The family'),
            sections: [
                {
                    key: 'family',
                    label: trans('Family name'),
                    icon: House,
                    href: family().url,
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
                ...(bankFeed.value === null
                    ? []
                    : [
                          {
                              key: 'banks' as const,
                              label: trans('Linked banks'),
                              icon: Landmark,
                              href: banks().url,
                          },
                      ]),
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
                },
            ],
        },
        {
            label: trans('The app'),
            sections: [
                {
                    key: 'news',
                    label: trans("What's new"),
                    icon: Sparkles,
                    href: news().url,
                    note: __APP_BUILD__,
                },
            ],
        },
    ],
);
</script>

<template>
    <div v-if="wide" class="flex flex-col gap-6">
        <!-- Not an FmTitle: the titles under this frame are those of the sections. -->
        <h1 class="m-0 text-title-mobile computer:text-title">
            {{ $t('Settings') }}
        </h1>
        <div class="flex flex-wrap items-start gap-6">
            <nav
                :aria-label="$t('Settings sections')"
                class="flex min-w-60 flex-[0_1_270px] flex-col gap-0.5 rounded-card bg-card p-2.5 shadow-card"
            >
                <div
                    v-for="group in groups"
                    :key="group.label ?? 'mine'"
                    :role="group.label ? 'group' : undefined"
                    :aria-label="group.label ?? undefined"
                    class="flex flex-col gap-0.5"
                >
                    <span
                        v-if="group.label"
                        aria-hidden="true"
                        class="block px-3 pt-3.5 pb-1 text-meta-strong text-ink-soft"
                        >{{ group.label }}</span
                    >
                    <Link
                        v-for="section in group.sections"
                        :key="section.key"
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
                        <span
                            v-if="section.note"
                            class="ml-auto text-meta text-ink-soft"
                            >{{ section.note }}</span
                        >
                    </Link>
                </div>
            </nav>
            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                <slot name="title" />
                <slot />
            </div>
        </div>
    </div>
    <div v-else class="flex max-w-[720px] flex-col gap-5">
        <slot name="title" />
        <slot />
    </div>
</template>
