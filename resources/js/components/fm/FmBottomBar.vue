<script setup lang="ts">
/**
 * The bottom bar of the phone (below 1024 px): Home, Budget, the + of Upload in
 * the middle, Areas and More. More opens a panel with Agenda, Documents, Family,
 * Settings and Log out, and stays marked when one of those pages is open.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { LogOut, Menu, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';
import FmBottomSheet from './FmBottomSheet.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';
import type { NavigationItem, NavigationItems, Section } from './navigation';

const {
    active,
    items,
    uploadCount = 0,
} = defineProps<{
    /** The section of the page that is open. */
    active: Section;
    /** The navigation items. */
    items: NavigationItems;
    /** The files waiting to be sorted: the number on the +; nothing at zero, 99+ above 99. */
    uploadCount?: number;
}>();

/** The number on the +: «99+» above 99. */
const uploadBadge = computed(() =>
    uploadCount > 99 ? '99+' : String(uploadCount),
);

const moreOpen = ref(false);

/** The sections that stay in the bar, in the order of the board. */
const barSections: Section[] = ['home', 'budget', 'areas'];

/** The items that stay in the bar: Home, Budget, Areas. */
const inBar = computed((): NavigationItem[] =>
    barSections.flatMap((section) =>
        items.sections.filter((item) => item.section === section),
    ),
);

/** The items inside More: the sections that are not in the bar, and Settings. */
const inMore = computed((): NavigationItem[] => [
    ...items.sections.filter((item) => !barSections.includes(item.section)),
    items.settings,
]);

const moreActive = computed((): boolean =>
    inMore.value.some((item) => item.section === active),
);

/**
 * The classes of an item, blue if it is the one of the page.
 *
 * @param marked - the item is the one of the page that is open
 */
function itemClasses(marked: boolean): string {
    return cn(
        'flex min-h-13 flex-col items-center justify-center gap-1 text-xs no-underline',
        marked ? 'font-semibold text-action' : 'font-medium text-ink-soft',
    );
}
</script>

<template>
    <nav
        :aria-label="$t('Sections')"
        class="grid h-[84px] shrink-0 grid-cols-5 items-center border-t border-[#E3E8EF] bg-white px-1 pb-3"
    >
        <template v-for="(item, i) in inBar" :key="item.section">
            <span v-if="i === 2" class="flex justify-center">
                <Link
                    :href="items.upload"
                    :aria-label="
                        uploadCount > 0
                            ? $t('Upload a document, :count to sort', {
                                  count: uploadBadge,
                              })
                            : $t('Upload a document')
                    "
                    class="relative -mt-7 flex size-15 items-center justify-center rounded-full bg-action text-white shadow-[0_6px_16px_rgba(0,112,224,0.35)]"
                >
                    <FmIcon :icon="Plus" :size="28" :stroke="2.5" />
                    <span
                        v-if="uploadCount > 0"
                        aria-hidden="true"
                        class="absolute -top-1 -right-1 min-w-5 rounded-full bg-overdue px-1 text-center text-xs font-semibold text-white"
                        >{{ uploadBadge }}</span
                    >
                </Link>
            </span>
            <Link
                :href="item.href"
                :aria-current="item.section === active ? 'page' : undefined"
                :class="itemClasses(item.section === active)"
            >
                <FmIcon :icon="item.icon" />
                {{ item.label }}
            </Link>
        </template>
        <button
            type="button"
            :aria-current="moreActive ? 'page' : undefined"
            :class="itemClasses(moreActive)"
            @click="moreOpen = true"
        >
            <FmIcon :icon="Menu" />
            {{ $t('More') }}
        </button>
    </nav>

    <FmBottomSheet v-model:open="moreOpen" :title="$t('More')">
        <div
            class="flex flex-col divide-y divide-divider rounded-2xl border border-divider"
        >
            <FmRow
                v-for="item in inMore"
                :key="item.section"
                :title="item.label"
                :subtitle="item.hint"
                :href="item.href"
            >
                <template #before>
                    <FmIcon
                        :icon="item.icon"
                        :size="22"
                        class="text-ink-soft"
                    />
                </template>
            </FmRow>
            <Link
                :href="items.logout"
                method="post"
                as="button"
                class="flex min-h-13 items-center gap-3 px-4 text-left text-base font-semibold text-overdue"
            >
                <FmIcon :icon="LogOut" :size="22" />
                {{ $t('Log out') }}
            </Link>
        </div>
    </FmBottomSheet>
</template>
