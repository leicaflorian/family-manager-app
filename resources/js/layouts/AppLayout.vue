<script setup lang="ts">
/**
 * The layout of the pages of the app for who is signed in, in the three
 * layouts of `useLayoutMode()`: on the phone the bottom bar; on the tablet the
 * sidebar collapsed to its icons and the top bar, whose *Expand the menu* opens
 * the sidebar in a panel from the left; on the computer, with room for it, the
 * sidebar always open. On the tablet and the computer, over the page, the top bar;
 * Back and the trail are in the top bar on the computer, and on the phone and the
 * tablet in `FmPageTrail` above the page (the link to the page above on the
 * phone, the row with Back and the trail on the tablet). The marked section comes from the address of the page; the trail is
 * the name of the section on the page of a section, otherwise the pieces the
 * page writes with `usePageTrail`. While a form has changes not saved, it asks
 * before the page changes or the tab closes. ⌘K, Ctrl K or «/» put the focus
 * in the box of the top bar on the tablet and the computer; on the phone they
 * open the *Search* page, or focus its box when it is already open. During a
 * visit of the owner (*Enter as…*) a strip at the top says whose eyes the app
 * is seen with, and *Leave* ends the visit. The height of the sticky top (the
 * strip and the top bar) is in `--app-header`, for what the pages keep sticky
 * or fixed under it. *Report a problem or an idea*, chosen in the account menu
 * of the sidebar or in More of the phone, opens one panel kept here (the menu
 * of the tablet closes first). `bare` puts the page straight under the top bar,
 * without the centred column and its margins, for a page with its own columns and
 * bars (*Scan* on the tablet, through `ScanLayout`).
 */
import { router, usePage } from '@inertiajs/vue3';
import { useElementSize } from '@vueuse/core';
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
import { captureScreenshot } from '@/lib/screenshot';
import FmFeedbackSheet from '@/components/fm/FmFeedbackSheet.vue';
import FmPageTrail from '@/components/fm/FmPageTrail.vue';
import FmSidebar from '@/components/fm/FmSidebar.vue';
import FmTopBar from '@/components/fm/FmTopBar.vue';
import type { Section } from '@/components/fm/navigation';
import {
    allItems,
    navigationItems,
    sectionOf,
} from '@/components/fm/navigation';
import LeaveGuard from '@/components/layout/LeaveGuard.vue';
import FmToaster from '@/components/fm/FmToaster.vue';
import { useBankFeed } from '@/composables/useBankFeed';
import { useBellNotices } from '@/composables/useBellNotices';
import { useFeatures } from '@/composables/useFeatures';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { useLeavePage } from '@/composables/useLeavePage';
import { useNewVersion } from '@/composables/useNewVersion';
import { SEARCH_PAGE_FIELD } from '@/composables/useSearchBox';
import { useSearchShortcut } from '@/composables/useSearchShortcut';
import type { TrailPiece } from '@/lib/pageTrail';
import { createTrailState, isSectionPage, TRAIL_KEY } from '@/lib/pageTrail';
import { search } from '@/routes';
import { leave } from '@/routes/visit';

const { bare = false } = defineProps<{
    /** The page goes straight under the top bar, without the column and its margins. */
    bare?: boolean;
}>();

defineSlots<{
    /** The page. */
    default: () => unknown;
}>();

const page = usePage();
const mode = useLayoutMode();

/** The features of the family: they decide the sections of the menus and the pages under them. */
const features = useFeatures();

/** The link to the bank: the number to sort for *From the bank* in the sidebar; null while it is off. */
const bankFeed = useBankFeed();

/** The navigation items, without the sections of a feature the family does not have. */
const items = computed(() => navigationItems(features.value));

const path = computed((): string => page.url.split('?')[0] ?? '/');
const active = computed((): Section => sectionOf(path.value, items.value));
const areas = computed((): App.Data.AreaCountData[] => page.props.areas ?? []);
const user = computed((): string => page.props.auth?.user?.name ?? '');
const toSort = computed((): number => page.props.to_sort_count ?? 0);
const family = computed((): string => page.props.family?.name ?? '');

const { unread: unreadNotices, notices, loadNotices } = useBellNotices();
// A release while the page is open: a message with Reload, in the toaster below
useNewVersion();
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
    return (
        allItems(items.value).find((item) => item.section === active.value)
            ?.label ?? ''
    );
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

/** The sidebar of the tablet is open over the page. */
const menuOpen = ref(false);

/** Whether the sidebar in the page is collapsed: on the tablet, which opens it over the page; the computer has room for it open. */
const collapsed = computed((): boolean => mode.value === 'tablet');

/** The panel *Report a problem or an idea* is open. */
const feedbackOpen = ref(false);

/** The photo of the page taken when the panel opened, `null` until it is ready. */
const feedbackScreenshot = ref<File | null>(null);

/** Counts the openings, so a photo that arrives after the panel closed is ignored. */
let feedbackOpening = 0;

/**
 * Opens the panel of the report at once, closing the menu of the tablet first
 * (so two panels never stack), and takes the photo of the page meanwhile.
 */
function openFeedback(): void {
    menuOpen.value = false;
    const opening = ++feedbackOpening;
    feedbackScreenshot.value = null;
    // The photo is taken before the panel is drawn over the page, which the
    // library also skips: the panel is marked `data-no-screenshot`
    const capture = captureScreenshot();
    feedbackOpen.value = true;
    void capture.then((file) => {
        if (opening === feedbackOpening && feedbackOpen.value) {
            feedbackScreenshot.value = file;
        }
    });
}

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

const header = useTemplateRef<HTMLElement>('header');
const { height: headerHeight } = useElementSize(header, undefined, {
    box: 'border-box',
});

/**
 * The height of the sticky top, measured once it is drawn; until then the top
 * bar of the tablet and the computer, nothing on the phone.
 */
const appHeader = computed((): string => {
    if (headerHeight.value > 0) {
        return `${headerHeight.value}px`;
    }

    return mode.value === 'phone' ? '0px' : 'var(--spacing-topbar)';
});

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
    <div
        class="flex min-h-dvh bg-canvas text-ink"
        :style="{ '--app-header': appHeader }"
    >
        <FmSidebar
            v-if="mode !== 'phone'"
            class="sticky top-0 h-dvh"
            :collapsed="collapsed"
            :active="active"
            :path="path"
            :items="items"
            :areas="areas"
            :family="family"
            :user="user"
            :upload-count="toSort"
            :features="features"
            :bank-to-sort="bankFeed?.unsorted ?? null"
            @report="openFeedback"
        />
        <div class="flex min-w-0 grow flex-col">
            <div ref="header" class="sticky top-0 z-30">
                <div
                    v-if="impersonating"
                    role="status"
                    class="flex items-center justify-between gap-3 bg-warning-soft px-(--main-gutter) py-2 text-label text-warning [--main-gutter:--spacing(5)] computer:[--main-gutter:--spacing(10)]"
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
                    :collapsed="collapsed"
                    :unread-notices="unreadNotices"
                    :notices="notices"
                    :query="query"
                    @expand="menuOpen = true"
                    @load-notices="loadNotices"
                />
            </div>
            <main v-if="bare" class="flex min-h-0 grow flex-col">
                <slot />
            </main>
            <main
                v-else
                class="@container grow px-(--main-gutter) pt-4 pb-6 [--main-gutter:--spacing(5)] computer:pt-8 computer:pb-12 computer:[--main-gutter:--spacing(10)]"
            >
                <div
                    class="mx-auto flex max-w-(--main-width) flex-col gap-4 computer:gap-6"
                >
                    <FmPageTrail
                        v-if="mode !== 'computer'"
                        :mode="mode"
                        :trail="trail"
                        :section="sectionPage"
                        class="-mb-2"
                    />
                    <slot />
                </div>
            </main>
            <div v-if="mode === 'phone'" class="sticky bottom-0 z-30">
                <FmBottomBar
                    :active="active"
                    :items="items"
                    :upload-count="toSort"
                    @report="openFeedback"
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
                    data-no-screenshot
                    class="fixed inset-0 z-40 bg-overlay duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
                />
                <DialogContent
                    id="app-menu"
                    data-no-screenshot
                    :aria-describedby="undefined"
                    class="fixed inset-y-0 left-0 z-50 flex bg-card shadow-dialog outline-none data-[state=closed]:animate-out data-[state=closed]:slide-out-to-left data-[state=open]:animate-in data-[state=open]:slide-in-from-left motion-reduce:animate-none"
                >
                    <DialogTitle class="sr-only">{{ $t('Menu') }}</DialogTitle>
                    <FmSidebar
                        class="h-full overflow-y-auto"
                        :active="active"
                        :path="path"
                        :items="items"
                        :areas="areas"
                        :family="family"
                        :user="user"
                        :upload-count="toSort"
                        :features="features"
                        :bank-to-sort="bankFeed?.unsorted ?? null"
                        @report="openFeedback"
                    />
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
        <FmFeedbackSheet
            v-model:open="feedbackOpen"
            :screenshot="feedbackScreenshot"
        />
        <div data-no-screenshot>
            <FmToaster :above-bottom-bar="mode === 'phone'" />
        </div>
        <LeaveGuard />
    </div>
</template>
