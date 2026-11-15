<script setup lang="ts">
/**
 * The sidebar of the computer (from 1024 px), and the content of the menu that
 * the Menu button of the tablet opens from the left: the logo and the family,
 * Upload (which only uploads), Home and the sections in three groups: *Incoming* (*To sort*, with the files
 * waiting in a blue pill, nothing at zero, and *Received mail*), *Every day* (Agenda, Budget) and
 * *Archive* (Areas, with the areas under it, Documents, Family), and at
 * the bottom *My account*, which opens Settings, *Report a problem or an idea*
 * (it emits `report`: the layout opens the panel), the version (it emits `news`: the layout opens
 * *What's new*) and Log out. When the sections do not fit
 * the window, only they scroll: the logo, Upload and *My account* stay in place.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, LogOut, Plus } from '@lucide/vue';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import type { ClassValue } from 'clsx';
import { computed, ref, useAttrs, watch } from 'vue';
import { useAppName } from '@/composables/useAppName';
import { getInitials } from '@/composables/useInitials';
import { areaFill, areaOf } from '@/lib/areas';
import { show as areaPage } from '@/routes/areas';
import FmIcon from './FmIcon.vue';
import { trans } from 'laravel-vue-i18n';
import { countBadge } from '@/lib/badge';
import { cn } from '@/lib/utils';
import FmLogo from './FmLogo.vue';
import type { NavigationItem, NavigationItems, Section } from './navigation';

/** The name of the app, for the wordmark. */
const appName = useAppName();

const {
    active,
    path,
    items,
    areas,
    family,
    user,
    uploadCount = 0,
} = defineProps<{
    /** The section of the page that is open. */
    active: Section;
    /** The path of the page, for example `/areas/home`, to mark its area. */
    path: string;
    /** The navigation items. */
    items: NavigationItems;
    /** The areas with their active cards, in order. */
    areas: App.Data.AreaCountData[];
    /** The name of the family, for example «Bianchi». */
    family: string;
    /** The name of who is signed in. */
    user: string;
    /** The files waiting to be sorted: the number in the pill of *To sort*; nothing at zero, 99+ above 99. */
    uploadCount?: number;
}>();

const emit = defineEmits<{
    /** *Report a problem or an idea* was chosen: the layout opens its panel. */
    report: [];
    /** The version was chosen: the layout opens *What's new*. */
    news: [];
}>();

// The classes of the page are merged with the sidebar's (the menu of the tablet makes it `w-full`)
defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

/**
 * The classes of the sidebar, merged with the page's. Called from the
 * template, so it follows the page at each render.
 *
 * @returns the merged classes
 */
function rootClass(): string {
    return cn(
        'flex w-[260px] shrink-0 flex-col gap-1.5 border-r border-edge-soft bg-card px-3.5 py-5',
        attrs.class as ClassValue,
    );
}

/**
 * The other attributes of the page, without the classes.
 *
 * @returns the attributes for the sidebar
 */
function otherAttrs(): Record<string, unknown> {
    const { class: _class, ...rest } = attrs;

    return rest;
}

/** A group of sections in the sidebar. */
type SectionGroup = {
    /** The key of the group, for the id of its name. */
    key: string;
    /** The name of the group, already translated; null for Home, which has none. */
    label: string | null;
    /** The sections of the group, in order. */
    items: NavigationItem[];
};

/**
 * The items of the given sections, in that order.
 *
 * @param names - the sections to take
 * @returns their items, without the ones the navigation does not have
 */
function pick(...names: Section[]): NavigationItem[] {
    const all = [...items.sections, items.toSort, items.mail];

    return names.flatMap((name) => all.filter((item) => item.section === name));
}

/** Home alone, then what arrives, what is used every day and what is kept. */
const groups = computed((): SectionGroup[] => [
    { key: 'home', label: null, items: pick('home') },
    {
        key: 'incoming',
        label: trans('Incoming'),
        items: pick('toSort', 'mail'),
    },
    {
        key: 'every-day',
        label: trans('Every day'),
        items: pick('agenda', 'budget'),
    },
    {
        key: 'archive',
        label: trans('Archive'),
        items: pick('areas', 'documents', 'family'),
    },
]);

/** A link of the list under *Areas*. */
type AreaLink = {
    /** The name of the area, or *All areas*. */
    label: string;
    /** The address of the page. */
    href: string;
    /** The active cards; null for *All areas*. */
    count: number | null;
    /** The colour of the dot; null for *All areas*. */
    dot: string | null;
};

/** *All areas*, then one link per area. */
const areaLinks = computed((): AreaLink[] => [
    {
        label: trans('All areas'),
        href:
            items.sections.find((item) => item.section === 'areas')?.href ?? '',
        count: null,
        dot: null,
    },
    ...areas.map((area): AreaLink => ({
        label: area.label,
        href: areaPage(area.area).url,
        count: area.count,
        dot: areaFill[areaOf(area.area) ?? 'general'],
    })),
]);

/** Whether the page is one of the list, so *Areas* itself is not the page. */
const onAreaLink = computed((): boolean =>
    areaLinks.value.some((link) => link.href === path),
);

/** Whether the list of the areas is open: it opens by itself inside Areas. */
const open = ref(active === 'areas');

watch(
    () => active,
    (section): void => {
        if (section === 'areas') {
            open.value = true;
        }
    },
);

/**
 * Whether a section is the page that is open.
 *
 * @param section - the section of the item
 * @returns true for the open section, unless one of its areas is the page
 */
function isPage(section: Section): boolean {
    return section === active && !(section === 'areas' && onAreaLink.value);
}
</script>

<template>
    <aside v-bind="otherAttrs()" :class="rootClass()">
        <div class="flex shrink-0 items-center gap-2.5 px-2 pt-1 pb-4">
            <FmLogo :size="36" />
            <span class="flex flex-col">
                <span class="text-amount">{{ appName }}</span>
                <span class="text-meta text-ink-soft">{{
                    $t(':family family', { family })
                }}</span>
            </span>
        </div>

        <Link
            :href="items.upload"
            class="mb-3.5 flex h-control-md shrink-0 items-center justify-center gap-2 rounded-md bg-action text-body text-on-action no-underline hover:bg-action-dark"
        >
            <FmIcon :icon="Plus" :size="20" :stroke="2.5" />
            {{ $t('Upload') }}
        </Link>

        <!-- Only the sections scroll, when the open areas make them taller than the window;
             they reach the edges of the sidebar, so the scroll bar sits on its border -->
        <nav
            :aria-label="$t('Sections')"
            class="-mx-3.5 flex min-h-0 flex-col gap-0.5 overflow-y-auto px-3.5"
        >
            <div
                v-for="(group, index) in groups"
                :key="group.key"
                :role="group.label ? 'group' : undefined"
                :aria-labelledby="
                    group.label ? `sidebar-group-${group.key}` : undefined
                "
                class="flex flex-col gap-0.5"
            >
                <span
                    v-if="group.label"
                    :id="`sidebar-group-${group.key}`"
                    class="block px-3 pb-1 text-meta-strong text-ink-soft"
                    :class="index === 1 ? 'pt-2.5' : 'pt-4.5'"
                    >{{ group.label }}</span
                >
                <template v-for="item in group.items" :key="item.section">
                    <div class="relative flex items-center">
                        <Link
                            :href="item.href"
                            :aria-current="
                                isPage(item.section) ? 'page' : undefined
                            "
                            class="flex h-touch grow items-center gap-3 rounded-lg px-3 text-body-sm no-underline"
                            :class="[
                                isPage(item.section)
                                    ? 'bg-action-soft text-label text-action-dark'
                                    : item.section === active
                                      ? 'text-label text-action-dark hover:bg-canvas'
                                      : 'text-ink hover:bg-canvas',
                                item.section === 'areas' && 'pr-12',
                            ]"
                        >
                            <FmIcon
                                :icon="item.icon"
                                :size="22"
                                :class="
                                    item.section === active
                                        ? ''
                                        : 'text-ink-soft'
                                "
                            />
                            <span class="grow">{{ item.label }}</span>
                            <template
                                v-if="
                                    item.section === 'toSort' && uploadCount > 0
                                "
                            >
                                <!-- A space, so the link reads «To sort 4» -->
                                {{ ' ' }}
                                <span
                                    class="inline-flex h-[22px] min-w-[22px] shrink-0 items-center justify-center rounded-full bg-action px-[7px] text-badge text-on-action"
                                    >{{ countBadge(uploadCount) }}</span
                                >
                            </template>
                        </Link>
                        <button
                            v-if="item.section === 'areas'"
                            type="button"
                            :aria-label="$t('Show the areas')"
                            :aria-expanded="open"
                            aria-controls="sidebar-areas"
                            class="absolute right-1 flex size-9 items-center justify-center rounded-tag text-ink-soft hover:bg-card/70 hover:text-ink"
                            @click="open = !open"
                        >
                            <FmIcon
                                :icon="open ? ChevronUp : ChevronDown"
                                :size="18"
                            />
                        </button>
                    </div>
                    <div v-if="item.section === 'areas'" v-auto-animate>
                        <ul
                            v-if="open"
                            id="sidebar-areas"
                            :aria-label="item.label"
                            class="my-0.5 ml-[21px] flex flex-col gap-0.5 border-l border-divider pl-1.5"
                        >
                            <li v-for="link in areaLinks" :key="link.href">
                                <Link
                                    :href="link.href"
                                    :aria-current="
                                        link.href === path ? 'page' : undefined
                                    "
                                    class="flex h-9 items-center gap-2 rounded-tag px-2.5 text-meta no-underline"
                                    :class="
                                        link.href === path
                                            ? 'bg-action-soft text-meta-strong text-action-dark'
                                            : 'text-ink hover:bg-canvas'
                                    "
                                >
                                    <span
                                        v-if="link.dot"
                                        class="size-2.5 shrink-0 rounded-full"
                                        :class="link.dot"
                                    />
                                    <span class="grow truncate">{{
                                        link.label
                                    }}</span>
                                    <!-- A space, so the link reads «Home 3» -->
                                    {{ ' ' }}
                                    <span
                                        v-if="link.count"
                                        class="text-caption text-ink-soft"
                                        >{{ link.count }}</span
                                    >
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>
            </div>
        </nav>

        <!-- At the bottom when there is room, right under the sections when they scroll -->
        <div class="mt-auto shrink-0 border-t border-divider pt-2">
            <DropdownMenuRoot>
                <DropdownMenuTrigger
                    class="flex min-h-13 w-full items-center gap-2.5 rounded-lg px-2 text-left hover:bg-canvas data-[state=open]:bg-canvas"
                >
                    <span
                        class="flex size-[34px] shrink-0 items-center justify-center rounded-full bg-action-soft text-label text-action-dark"
                        >{{ getInitials(user) }}</span
                    >
                    <span class="flex grow flex-col">
                        <span class="text-label">{{ user }}</span>
                        <span class="text-caption text-ink-soft">{{
                            $t('My account')
                        }}</span>
                    </span>
                    <FmIcon
                        :icon="ChevronDown"
                        :size="18"
                        class="text-ink-soft"
                    />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent
                        side="top"
                        :side-offset="8"
                        class="z-50 flex w-[232px] flex-col gap-0.5 rounded-xl border border-edge-soft bg-card p-1.5 text-ink shadow-menu duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=open]:slide-in-from-bottom-1 motion-reduce:animate-none"
                    >
                        <DropdownMenuItem as-child>
                            <Link
                                :href="items.settings.href"
                                class="flex min-h-11 items-center gap-3 rounded-md px-3 text-body-sm no-underline outline-none data-[highlighted]:bg-canvas"
                            >
                                <FmIcon
                                    :icon="items.settings.icon"
                                    :size="20"
                                    class="text-ink-soft"
                                />
                                {{ items.settings.label }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 text-body-sm outline-none data-[highlighted]:bg-canvas"
                            @select="emit('report')"
                        >
                            <FmIcon
                                :icon="items.feedback.icon"
                                :size="20"
                                class="text-ink-soft"
                            />
                            {{ items.feedback.label }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 text-body-sm outline-none data-[highlighted]:bg-canvas"
                            @select="emit('news')"
                        >
                            <FmIcon
                                :icon="items.release.icon"
                                :size="20"
                                class="text-ink-soft"
                            />
                            {{ items.release.label }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator
                            class="mx-1.5 my-1 h-px bg-divider"
                        />
                        <DropdownMenuItem as-child>
                            <Link
                                :href="items.logout"
                                method="post"
                                as="button"
                                class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-left text-body-sm text-overdue outline-none data-[highlighted]:bg-canvas"
                            >
                                <FmIcon :icon="LogOut" :size="20" />
                                {{ $t('Log out') }}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </div>
    </aside>
</template>
