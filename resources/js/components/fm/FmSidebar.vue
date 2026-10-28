<script setup lang="ts">
/**
 * The sidebar of the computer (from 1024 px): the logo and the family, Upload,
 * the sections with the areas under *Areas*, and at the bottom *My account*,
 * which opens Settings and Log out.
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
import { computed, ref, watch } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { areaFill, areaOf } from '@/lib/areas';
import { show as areaPage } from '@/routes/areas';
import FmIcon from './FmIcon.vue';
import { trans } from 'laravel-vue-i18n';
import FmLogo from './FmLogo.vue';
import type { NavigationItems, Section } from './navigation';

const { active, path, items, areas, family, user } = defineProps<{
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
}>();

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
    <aside
        class="flex w-[260px] shrink-0 flex-col gap-1.5 border-r border-[#E3E8EF] bg-white px-3.5 py-5"
    >
        <div class="flex items-center gap-2.5 px-2 pt-1 pb-4">
            <FmLogo :size="36" />
            <span class="flex flex-col">
                <span class="text-base font-extrabold">Family Manager</span>
                <span class="text-[13px] text-ink-soft">{{
                    $t(':family family', { family })
                }}</span>
            </span>
        </div>

        <Link
            :href="items.upload"
            class="mb-3.5 flex h-11.5 items-center justify-center gap-2 rounded-[10px] bg-action text-base font-normal text-white no-underline hover:bg-action-dark"
        >
            <FmIcon :icon="Plus" :size="20" :stroke="2.5" />
            {{ $t('Upload') }}
        </Link>

        <nav :aria-label="$t('Sections')" class="flex flex-col gap-0.5">
            <template v-for="item in items.sections" :key="item.section">
                <div class="relative flex items-center">
                    <Link
                        :href="item.href"
                        :aria-current="
                            isPage(item.section) ? 'page' : undefined
                        "
                        class="flex h-11 grow items-center gap-3 rounded-xl px-3 text-[15px] no-underline"
                        :class="[
                            isPage(item.section)
                                ? 'bg-action-soft font-bold text-action-dark'
                                : item.section === active
                                  ? 'font-bold text-action-dark hover:bg-canvas'
                                  : 'font-medium text-ink hover:bg-canvas',
                            item.section === 'areas' && 'pr-12',
                        ]"
                    >
                        <FmIcon
                            :icon="item.icon"
                            :size="22"
                            :class="
                                item.section === active ? '' : 'text-ink-soft'
                            "
                        />
                        {{ item.label }}
                    </Link>
                    <button
                        v-if="item.section === 'areas'"
                        type="button"
                        :aria-label="$t('Show the areas')"
                        :aria-expanded="open"
                        aria-controls="sidebar-areas"
                        class="absolute right-1 flex size-9 items-center justify-center rounded-lg text-ink-soft hover:bg-white/70 hover:text-ink"
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
                                class="flex h-9 items-center gap-2 rounded-lg px-2.5 text-[13px] no-underline"
                                :class="
                                    link.href === path
                                        ? 'bg-action-soft font-bold text-action-dark'
                                        : 'font-medium text-ink hover:bg-canvas'
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
                                    class="text-xs font-semibold text-ink-soft"
                                    >{{ link.count }}</span
                                >
                            </Link>
                        </li>
                    </ul>
                </div>
            </template>
        </nav>

        <span class="min-h-6 grow" />

        <div class="border-t border-divider pt-2">
            <DropdownMenuRoot>
                <DropdownMenuTrigger
                    class="flex min-h-13 w-full items-center gap-2.5 rounded-xl px-2 text-left hover:bg-canvas data-[state=open]:bg-canvas"
                >
                    <span
                        class="flex size-[34px] shrink-0 items-center justify-center rounded-full bg-action-soft text-sm font-bold text-action-dark"
                        >{{ getInitials(user) }}</span
                    >
                    <span class="flex grow flex-col">
                        <span class="text-sm font-semibold">{{ user }}</span>
                        <span class="text-xs text-ink-soft">{{
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
                        class="z-50 flex w-[232px] flex-col gap-0.5 rounded-[14px] border border-[#E3E8EF] bg-white p-1.5 text-ink shadow-menu duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=open]:slide-in-from-bottom-1 motion-reduce:animate-none"
                    >
                        <DropdownMenuItem as-child>
                            <Link
                                :href="items.settings.href"
                                class="flex min-h-11 items-center gap-3 rounded-[10px] px-3 text-[15px] font-medium no-underline outline-none data-[highlighted]:bg-canvas"
                            >
                                <FmIcon
                                    :icon="items.settings.icon"
                                    :size="20"
                                    class="text-ink-soft"
                                />
                                {{ items.settings.label }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator
                            class="mx-1.5 my-1 h-px bg-divider"
                        />
                        <DropdownMenuItem as-child>
                            <Link
                                :href="items.logout"
                                method="post"
                                as="button"
                                class="flex min-h-11 w-full items-center gap-3 rounded-[10px] px-3 text-left text-[15px] font-medium text-overdue outline-none data-[highlighted]:bg-canvas"
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
