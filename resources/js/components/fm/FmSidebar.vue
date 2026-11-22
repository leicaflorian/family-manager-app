<script setup lang="ts">
/**
 * The sidebar of the tablet and of the computer: the bar of its head (the same box as the top bar, white) with the
 * logo and the family; Upload (which only uploads), Home and the
 * sections in three groups: *Incoming* (*To sort*, with the files waiting in a blue pill, nothing at zero, and
 * *Received mail*), *Every day* (Agenda, Budget) and *Archive* (Areas, Documents, Family). Areas, Budget and, with a
 * drawings gallery, Documents (its gallery category) are accordions: a
 * button with a chevron next to the link opens the list under it (the areas; *Planned and spent* and *Usage*, the
 * latter when the family has a utility with consumption), and the list opens by itself while the page is one of
 * theirs; while a page under Budget is the open one, Budget is drawn plain),
 * and at the bottom *My account*, which opens Settings, *Report a problem or an idea* (it emits `report`: the layout
 * opens the panel) and Log out. When the sections do not fit the window, only they scroll: the head, Upload and
 * *My account* stay in place.
 * Collapsed (`collapsed`, always on the tablet) it is 76 px wide: the logo alone in the head, only the icons with
 * their names for screen readers, lines in place of the names of the groups, no chevrons, no lists under Areas and Budget, and the avatar
 * alone at the bottom; it opens over the page while the pointer or the focus is on it. The computer, with room for
 * it, always has it open: there is no button to collapse it. The button that opens it in a panel is in the top bar,
 * before the trail (`FmTopBar`).
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
import type { Ref } from 'vue';
import { computed, reactive, ref, useAttrs, watch } from 'vue';
import { useAppName } from '@/composables/useAppName';
import { getInitials } from '@/composables/useInitials';
import { areaFill, areaOf } from '@/lib/areas';
import { show as areaPage } from '@/routes/areas';
import { comparison, consumption } from '@/routes/budget';
import { gallery } from '@/routes/documents';
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
    collapsed = false,
    hasConsumption = false,
    galleryName = null,
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
    /** Collapsed to the icons, 76 px wide. */
    collapsed?: boolean;
    /** The family has an electricity, gas or water utility: *Usage* shows under *Budget*. */
    hasConsumption?: boolean;
    /** The name of the gallery category: it shows under *Documents*, which then opens like *Budget*; null without a gallery. */
    galleryName?: string | null;
}>();

const emit = defineEmits<{
    /** *Report a problem or an idea* was chosen: the layout opens its panel. */
    report: [];
}>();

/** The pointer is over the collapsed sidebar. */
const pointerOver = ref(false);

/** The focus is inside the collapsed sidebar, for who moves with the keyboard. */
const focusInside = ref(false);

/** The menu of *My account* is open: the sidebar stays open under it. */
const accountOpen = ref(false);

/** Whether the collapsed sidebar is open over the page. */
const floating = computed(
    (): boolean =>
        collapsed &&
        (pointerOver.value || focusInside.value || accountOpen.value),
);

/** Whether only the icons are shown. */
const small = computed((): boolean => collapsed && !floating.value);

/** The pointer leaves the sidebar: it collapses again, also when a click left the focus inside it. */
function pointerLeft(): void {
    pointerOver.value = false;
    focusInside.value = false;
}

/**
 * Follows the focus leaving the sidebar: it collapses only when the focus goes outside it.
 *
 * @param event - the focusout of the sidebar
 */
function focusLeft(event: FocusEvent): void {
    const aside = event.currentTarget as HTMLElement;
    focusInside.value =
        event.relatedTarget instanceof Node &&
        aside.contains(event.relatedTarget);
}

// A new page collapses it: a touch on a section opens it (mouseenter and focus) and never leaves it
watch(
    () => path,
    (): void => {
        pointerLeft();
    },
);

// The classes of the page are merged with the sidebar's (the layout makes it sticky, as tall as the window)
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
        'flex w-[260px] shrink-0 flex-col gap-1.5 overflow-x-hidden border-r border-edge-soft bg-card px-3.5 pb-5 transition-[width,margin] duration-200 ease-out motion-reduce:transition-none',
        small.value && 'w-[76px]',
        // 260 px wide but 76 px in the row of the page: it covers the page instead of pushing it
        floating.value && 'z-40 -mr-[184px] shadow-menu',
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

/**
 * An accordion list of the sidebar: closed by hand or open, and it opens by itself when the page enters its section.
 *
 * @param inside - whether the page is in the section of the list
 * @returns whether the list is open, writable by the button
 */
function useAccordion(inside: () => boolean): Ref<boolean> {
    const isOpen = ref(inside());

    watch(inside, (now): void => {
        if (now) {
            isOpen.value = true;
        }
    });

    return isOpen;
}

/** Whether the list of the areas is open: it opens by itself inside Areas. */
const open = useAccordion((): boolean => active === 'areas');

/** Whether the list of the pages under *Budget* is open: it opens by itself on any page of the Budget. */
const budgetOpen = useAccordion((): boolean => active === 'budget');

/** The address of *Usage*, under *Budget*. */
const consumptionUrl = consumption().url;

/** The address of *Planned and spent*, under *Budget*. */
const comparisonUrl = comparison().url;

/** Whether the page is *Usage*. */
const onConsumption = computed((): boolean => path === consumptionUrl);

/** Whether the page is *Planned and spent*. */
const onComparison = computed((): boolean => path === comparisonUrl);

/** The pages under *Budget*: *Planned and spent*, and *Usage* when the family has a utility with consumption. */
const budgetChildren = computed((): SubPage[] => [
    {
        label: trans('Planned and spent'),
        href: comparisonUrl,
        current: onComparison.value,
    },
    ...(hasConsumption
        ? [
              {
                  label: trans('Usage'),
                  href: consumptionUrl,
                  current: onConsumption.value,
              },
          ]
        : []),
]);

/** Whether the list under *Documents* is open: it opens by itself on any page of the Documents. */
const documentsOpen = useAccordion((): boolean => active === 'documents');

/** The address of the drawings gallery, under *Documents*. */
const galleryUrl = gallery().url;

/** A page in the list under a section. */
type SubPage = { label: string; href: string; current: boolean };

/** The pages under *Documents*: the gallery category, when the family has one. */
const documentChildren = computed((): SubPage[] =>
    galleryName
        ? [
              {
                  label: galleryName,
                  href: galleryUrl,
                  current: path === galleryUrl,
              },
          ]
        : [],
);

/** The sections with a list of pages under them, each with its pages, its open state and the name of its arrow. */
const subLists = reactive({
    budget: {
        pages: budgetChildren,
        open: budgetOpen,
        toggle: trans('Show the budget pages'),
    },
    documents: {
        pages: documentChildren,
        open: documentsOpen,
        toggle: trans('Show the documents pages'),
    },
});

/**
 * The list under a section, when it has pages.
 *
 * @param section - the section of the item
 * @returns its list, or null for a section without pages under it
 */
function subListOf(section: Section): (typeof subLists)['budget'] | null {
    if (section !== 'budget' && section !== 'documents') {
        return null;
    }

    return subLists[section].pages.length > 0 ? subLists[section] : null;
}

/**
 * Opens or closes the list under a section.
 *
 * @param section - the section of the item
 */
function toggleList(section: Section): void {
    const list = subListOf(section);

    if (list) {
        list.open = !list.open;
    }
}

/**
 * Whether one of the pages under a section is the page, so the section is drawn plain.
 *
 * @param section - the section of the item
 * @returns true while a page of its list is open
 */
function onChild(section: Section): boolean {
    return subListOf(section)?.pages.some((page) => page.current) ?? false;
}

/**
 * Whether a section is drawn as the one of the page, in blue: not a section
 * while one of the pages under it is open.
 *
 * @param section - the section of the item
 * @returns true for the section of the page
 */
function isActive(section: Section): boolean {
    return section === active && !onChild(section);
}

/**
 * Whether a section is the page that is open.
 *
 * @param section - the section of the item
 * @returns true for the open section, unless one of its areas or a page under it is the page
 */
function isPage(section: Section): boolean {
    return (
        section === active &&
        !(section === 'areas' && onAreaLink.value) &&
        !onChild(section)
    );
}
</script>

<template>
    <aside
        v-bind="otherAttrs()"
        :class="rootClass()"
        @mouseenter="pointerOver = collapsed"
        @mouseleave="pointerLeft"
        @focusin="focusInside = collapsed"
        @focusout="focusLeft"
    >
        <!-- The box of the top bar, across the whole sidebar, 20 px above Upload: the logo stays 20 px from the edge, open or collapsed -->
        <div
            class="-mx-3.5 mb-3.5 flex h-topbar shrink-0 items-center border-b border-edge-soft bg-card pr-4 pl-5"
        >
            <span class="flex min-w-0 grow items-center gap-2.5">
                <FmLogo :size="36" />
                <span
                    class="flex min-w-0 grow flex-col transition-opacity duration-200 motion-reduce:transition-none"
                    :class="small && 'opacity-0'"
                >
                    <span class="text-amount whitespace-nowrap text-ink">{{
                        appName
                    }}</span>
                    <span class="text-meta whitespace-nowrap text-ink-soft">{{
                        $t(':family family', { family })
                    }}</span>
                </span>
            </span>
        </div>

        <Link
            :href="items.upload"
            :title="small ? $t('Upload') : undefined"
            class="mb-3.5 flex h-control-md shrink-0 items-center justify-center rounded-md bg-action text-body text-on-action no-underline hover:bg-action-dark"
        >
            <FmIcon :icon="Plus" :size="20" :stroke="2.5" />
            <!-- The name shrinks with the button, so the + slides to the middle instead of jumping -->
            <span
                class="overflow-hidden whitespace-nowrap transition-[max-width,margin,opacity] duration-200 motion-reduce:transition-none"
                :class="small ? 'ml-0 max-w-0 opacity-0' : 'ml-2 max-w-40'"
                >{{ $t('Upload') }}</span
            >
        </Link>

        <!-- Only the sections scroll, when the open areas make them taller than the window;
             they reach the edges of the sidebar, so the scroll bar sits on its border -->
        <nav
            :aria-label="$t('Sections')"
            class="-mx-3.5 flex min-h-0 flex-col gap-0.5 overflow-x-hidden overflow-y-auto px-3.5"
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
                <!-- Collapsed, a line takes the place of the name of the group, which keeps its height: nothing moves -->
                <div v-if="group.label" class="relative">
                    <span
                        :id="`sidebar-group-${group.key}`"
                        class="block px-3 pb-1 text-meta-strong whitespace-nowrap text-ink-soft transition-opacity duration-200 motion-reduce:transition-none"
                        :class="[
                            index === 1 ? 'pt-2.5' : 'pt-4.5',
                            small && 'opacity-0',
                        ]"
                        >{{ group.label }}</span
                    >
                    <span
                        aria-hidden="true"
                        class="absolute inset-x-1.5 bottom-[11px] h-px bg-divider opacity-0 transition-opacity duration-200 motion-reduce:transition-none"
                        :class="small && 'opacity-100'"
                    />
                </div>
                <template v-for="item in group.items" :key="item.section">
                    <div class="relative flex items-center">
                        <Link
                            :href="item.href"
                            :aria-current="
                                isPage(item.section) ? 'page' : undefined
                            "
                            :title="small ? item.label : undefined"
                            class="relative flex h-touch grow items-center gap-3 rounded-lg px-3 text-body-sm no-underline"
                            :class="[
                                isPage(item.section)
                                    ? 'bg-action-soft text-label text-action-dark'
                                    : isActive(item.section)
                                      ? 'text-label text-action-dark hover:bg-canvas'
                                      : 'text-ink hover:bg-canvas',
                                (item.section === 'areas' ||
                                    subListOf(item.section)) &&
                                    !small &&
                                    'pr-12',
                            ]"
                        >
                            <FmIcon
                                :icon="item.icon"
                                :size="22"
                                :class="
                                    isActive(item.section)
                                        ? ''
                                        : 'text-ink-soft'
                                "
                            />
                            <span
                                class="grow whitespace-nowrap transition-opacity duration-200 motion-reduce:transition-none"
                                :class="small && 'opacity-0'"
                                >{{ item.label }}</span
                            >
                            <template
                                v-if="
                                    item.section === 'toSort' && uploadCount > 0
                                "
                            >
                                <!-- A space, so the link reads «To sort 4» -->
                                {{ ' ' }}
                                <span
                                    class="inline-flex h-[22px] min-w-[22px] shrink-0 items-center justify-center rounded-full bg-action px-[7px] text-badge text-on-action"
                                    :class="
                                        small &&
                                        'absolute top-px right-px border-2 border-card'
                                    "
                                    >{{ countBadge(uploadCount) }}</span
                                >
                            </template>
                        </Link>
                        <button
                            v-if="item.section === 'areas' && !small"
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
                        <button
                            v-else-if="subListOf(item.section) && !small"
                            type="button"
                            :aria-label="subListOf(item.section)?.toggle"
                            :aria-expanded="subListOf(item.section)?.open"
                            :aria-controls="`sidebar-${item.section}`"
                            class="absolute right-1 flex size-9 items-center justify-center rounded-tag text-ink-soft hover:bg-card/70 hover:text-ink"
                            @click="toggleList(item.section)"
                        >
                            <FmIcon
                                :icon="
                                    subListOf(item.section)?.open
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                :size="18"
                            />
                        </button>
                    </div>
                    <div v-if="subListOf(item.section)" v-auto-animate>
                        <ul
                            v-if="subListOf(item.section)?.open && !small"
                            :id="`sidebar-${item.section}`"
                            :aria-label="item.label"
                            class="mt-0 mb-1.5 ml-[23px] flex flex-col gap-px border-l-[1.5px] border-edge-soft pl-2.5"
                        >
                            <li
                                v-for="child in subListOf(item.section)?.pages"
                                :key="child.href"
                            >
                                <Link
                                    :href="child.href"
                                    :aria-current="
                                        child.current ? 'page' : undefined
                                    "
                                    class="flex min-h-[34px] items-center rounded-md px-2.5 text-body-sm no-underline"
                                    :class="
                                        child.current
                                            ? 'bg-action-soft text-label text-action-dark'
                                            : 'text-ink hover:bg-canvas'
                                    "
                                >
                                    <span class="min-w-0 grow truncate">{{
                                        child.label
                                    }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <!-- The box stays also collapsed, with its gap: only the list goes, so nothing under it moves -->
                    <div v-if="item.section === 'areas'" v-auto-animate>
                        <ul
                            v-if="open && !small"
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
            <DropdownMenuRoot v-model:open="accountOpen">
                <DropdownMenuTrigger
                    :title="small ? user : undefined"
                    class="flex min-h-13 w-full items-center gap-2.5 rounded-lg px-2 text-left hover:bg-canvas data-[state=open]:bg-canvas"
                >
                    <span
                        class="flex size-[34px] shrink-0 items-center justify-center rounded-full bg-action-soft text-label text-action-dark"
                        >{{ getInitials(user) }}</span
                    >
                    <span
                        class="flex grow flex-col transition-opacity duration-200 motion-reduce:transition-none"
                        :class="small && 'opacity-0'"
                    >
                        <span class="text-label whitespace-nowrap">{{
                            user
                        }}</span>
                        <span class="text-caption text-ink-soft">{{
                            $t('My account')
                        }}</span>
                    </span>
                    <FmIcon
                        v-if="!small"
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
