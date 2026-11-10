<script setup lang="ts">
/**
 * The layout of the pages of the app for who is signed in, in the three
 * layouts of `useLayoutMode()`: on the phone the bottom bar; on the tablet the
 * top bar, whose Menu button opens the sidebar in a panel from the left; on
 * the computer the sidebar and, over the page, the top bar with Back and the
 * trail. The marked section comes from the address of the page; the trail is
 * the name of the section on the page of a section, otherwise the pieces the
 * page writes with `usePageTrail`. While a form has changes not saved, it asks
 * before the page changes or the tab closes. ⌘K, Ctrl K or «/» put the focus
 * in the box of the top bar on the tablet and the computer; on the phone they
 * open the *Search* page, or focus its box when it is already open. During a
 * visit of the owner (*Enter as…*) a strip at the top says whose eyes the app
 * is seen with, and *Leave* ends the visit.
 */
import { router, usePage } from '@inertiajs/vue3';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, provide, ref, useTemplateRef, watch } from 'vue';
import FmBottomBar from '@/components/fm/FmBottomBar.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmSidebar from '@/components/fm/FmSidebar.vue';
import FmTopBar from '@/components/fm/FmTopBar.vue';
import type { Section } from '@/components/fm/navigation';
import { navigationItems, sectionOf } from '@/components/fm/navigation';
import LeaveGuard from '@/components/layout/LeaveGuard.vue';
import { Toaster } from '@/components/ui/sonner';
import { useBellNotices } from '@/composables/useBellNotices';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { useLeavePage } from '@/composables/useLeavePage';
import { SEARCH_PAGE_FIELD } from '@/composables/useSearchBox';
import { useSearchShortcut } from '@/composables/useSearchShortcut';
import type { TrailPiece } from '@/lib/pageTrail';
import { createTrailState, isSectionPage, TRAIL_KEY } from '@/lib/pageTrail';
import { search } from '@/routes';
import { leave } from '@/routes/visit';

defineSlots<{
    /** The page. */
    default: () => unknown;
}>();

const page = usePage();
const items = navigationItems();
const mode = useLayoutMode();

const path = computed((): string => page.url.split('?')[0] ?? '/');
const active = computed((): Section => sectionOf(path.value, items));
const areas = computed((): App.Data.AreaCountData[] => page.props.areas ?? []);
const user = computed((): string => page.props.auth?.user?.name ?? '');
const toSort = computed((): number => page.props.to_sort_count ?? 0);
const family = computed((): string => page.props.family?.name ?? '');
const { unread: unreadNotices, notices, loadNotices } = useBellNotices();
const impersonating = computed(
    (): string | null => page.props.impersonating ?? null,
);

// The pages write their trail here with usePageTrail, the top bar reads it
const trailState = createTrailState();
provide(TRAIL_KEY, trailState);

/** The open page is the page of a section, or one of its top tabs. */
const sectionPage = computed((): boolean => isSectionPage(page.component));

/** The name of the marked section, as the navigation writes it. */
const sectionLabel = computed((): string => {
    const all = [...items.sections, items.toSort, items.settings];

    return all.find((item) => item.section === active.value)?.label ?? '';
});

/** The trail of the top bar: the section alone on its page, otherwise the pieces of the page. */
const trail = computed((): TrailPiece[] =>
    sectionPage.value
        ? [{ label: sectionLabel.value }]
        : trailState.pieces.value,
);

/** The text searched, from the address of the *Search* page. */
const query = computed((): string =>
    page.component === 'Search'
        ? (new URLSearchParams(page.url.split('?')[1] ?? '').get('q') ?? '')
        : '',
);

/** The menu of the tablet is open. */
const menuOpen = ref(false);

// A visit closes the menu, so it never covers the new page (also a visit to the open page)
useLeavePage(() => {
    menuOpen.value = false;
});

// The menu belongs to the tablet: another layout closes it
watch(mode, () => {
    menuOpen.value = false;
});

/** Ends the visit of the owner: the server sends the browser back to the panel. */
function leaveVisit(): void {
    router.post(leave.url());
}

const topBar = useTemplateRef<InstanceType<typeof FmTopBar>>('topBar');

/**
 * What the search shortcut does: the box of the top bar on the tablet and the
 * computer; on the phone the box of the *Search* page when it is open,
 * otherwise a visit to it.
 */
function focusSearch(): void {
    if (mode.value !== 'phone' && topBar.value) {
        topBar.value.focusSearch();

        return;
    }

    const field =
        page.component === 'Search'
            ? document.getElementById(SEARCH_PAGE_FIELD)
            : null;

    if (field) {
        field.focus();
    } else {
        router.visit(search.url());
    }
}

useSearchShortcut(focusSearch);
</script>

<template>
    <div class="flex min-h-dvh bg-canvas text-ink">
        <FmSidebar
            v-if="mode === 'computer'"
            class="sticky top-0 h-dvh"
            :active="active"
            :path="path"
            :items="items"
            :areas="areas"
            :family="family"
            :user="user"
            :upload-count="toSort"
        />
        <div class="flex min-w-0 grow flex-col">
            <div class="sticky top-0 z-30">
                <div
                    v-if="impersonating"
                    role="status"
                    class="flex items-center justify-between gap-3 bg-warning-soft px-(--main-gutter) py-2 text-label text-warning [--main-gutter:--spacing(5)] lg:[--main-gutter:--spacing(10)]"
                >
                    <span>{{
                        $t('You are looking as :name', { name: impersonating })
                    }}</span>
                    <FmButton variant="outline" @click="leaveVisit">{{
                        $t('Leave')
                    }}</FmButton>
                </div>
                <FmTopBar
                    v-if="mode !== 'phone'"
                    ref="topBar"
                    :mode="mode"
                    :trail="trail"
                    :section="sectionPage"
                    :family="family"
                    :upload-count="toSort"
                    :unread-notices="unreadNotices"
                    :notices="notices"
                    :query="query"
                    :menu-open="menuOpen"
                    @open-menu="menuOpen = true"
                    @load-notices="loadNotices"
                />
            </div>
            <main
                class="@container grow px-(--main-gutter) pt-4 pb-6 [--main-gutter:--spacing(5)] lg:pt-8 lg:pb-12 lg:[--main-gutter:--spacing(10)]"
            >
                <div
                    class="mx-auto flex max-w-(--main-width) flex-col gap-4 lg:gap-6"
                >
                    <slot />
                </div>
            </main>
            <div v-if="mode === 'phone'" class="sticky bottom-0 z-30">
                <FmBottomBar
                    :active="active"
                    :items="items"
                    :upload-count="toSort"
                />
            </div>
        </div>
        <DialogRoot
            v-if="mode === 'tablet'"
            :open="menuOpen"
            @update:open="menuOpen = $event"
        >
            <DialogPortal>
                <DialogOverlay
                    class="fixed inset-0 z-40 bg-overlay duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
                />
                <DialogContent
                    id="app-menu"
                    :aria-describedby="undefined"
                    class="fixed inset-y-0 left-0 z-50 flex w-[280px] bg-card shadow-dialog outline-none data-[state=closed]:animate-out data-[state=closed]:slide-out-to-left data-[state=open]:animate-in data-[state=open]:slide-in-from-left motion-reduce:animate-none"
                >
                    <DialogTitle class="sr-only">{{ $t('Menu') }}</DialogTitle>
                    <FmSidebar
                        class="h-full w-full overflow-y-auto"
                        :active="active"
                        :path="path"
                        :items="items"
                        :areas="areas"
                        :family="family"
                        :user="user"
                        :upload-count="toSort"
                    />
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
        <Toaster />
        <LeaveGuard />
    </div>
</template>
