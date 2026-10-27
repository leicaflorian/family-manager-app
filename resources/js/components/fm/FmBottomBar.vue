<script setup lang="ts">
/**
 * The bottom bar of the phone (below 1024 px): Home, Areas, the + of Upload in
 * the middle, Documents and More. More opens a panel with Family, Settings and
 * Log out, and stays marked when one of those pages is open.
 */
import { Link } from '@inertiajs/vue3';
import { LogOut, Menu, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmBottomSheet from './FmBottomSheet.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';
import type { NavigationItem, NavigationItems, Section } from './navigation';

const { active, items } = defineProps<{
    /** The section of the page that is open. */
    active: Section;
    /** The navigation items. */
    items: NavigationItems;
}>();

const moreOpen = ref(false);

/** The items that stay in the bar: Home, Areas, Documents. */
const inBar = computed((): NavigationItem[] =>
    items.sections.filter((item) => item.section !== 'family'),
);

/** The items inside More. */
const inMore = computed((): NavigationItem[] => [
    ...items.sections.filter((item) => item.section === 'family'),
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
    return [
        'flex min-h-13 flex-col items-center justify-center gap-1 text-xs no-underline',
        marked ? 'font-semibold text-action' : 'font-medium text-ink-soft',
    ].join(' ');
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
                    :aria-label="$t('Upload a document')"
                    class="-mt-7 flex size-15 items-center justify-center rounded-full bg-action text-white shadow-[0_6px_16px_rgba(0,112,224,0.35)]"
                >
                    <FmIcon :icon="Plus" :size="28" :stroke="2.5" />
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
