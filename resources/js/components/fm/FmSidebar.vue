<script setup lang="ts">
/**
 * The sidebar of the computer (from 1024 px): the logo and the family, Upload,
 * the sections, and at the bottom *My account*, which opens Settings and Log out.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Plus } from '@lucide/vue';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import { getInitials } from '@/composables/useInitials';
import FmIcon from './FmIcon.vue';
import FmLogo from './FmLogo.vue';
import type { NavigationItems, Section } from './navigation';

const { active, items, family, user } = defineProps<{
    /** The section of the page that is open. */
    active: Section;
    /** The navigation items. */
    items: NavigationItems;
    /** The name of the family, for example «Bianchi». */
    family: string;
    /** The name of who is signed in. */
    user: string;
}>();
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
            <Link
                v-for="item in items.sections"
                :key="item.section"
                :href="item.href"
                :aria-current="item.section === active ? 'page' : undefined"
                class="flex h-11 items-center gap-3 rounded-xl px-3 text-[15px] no-underline"
                :class="
                    item.section === active
                        ? 'bg-action-soft font-bold text-action-dark'
                        : 'font-medium text-ink hover:bg-canvas'
                "
            >
                <FmIcon
                    :icon="item.icon"
                    :size="22"
                    :class="item.section === active ? '' : 'text-ink-soft'"
                />
                {{ item.label }}
            </Link>
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
