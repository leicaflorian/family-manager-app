<script setup lang="ts">
/**
 * The bar on top of every page of the tablet and of the computer, 68 px high
 * and sticky, with a 1 px line under it, as the card *Barra in alto* draws it.
 *
 * On the tablet (20 px at the sides) and on the computer (40 px): with the
 * sidebar collapsed it starts with the button that expands it (it emits `expand`;
 * the tablet always has the sidebar collapsed). On the computer only, then Back (only on an
 * inner page, when a page of the app comes before or the trail has a page above) and the
 * trail (`FmTrail`); on the tablet Back and the trail are in the row above the
 * page title. The logo and the family are in the sidebar. On the right the search of the
 * whole app (300 px, 420 px while it has the focus or some text, with the menu
 * of the first results under it; Enter opens the *Search* page) and the bell,
 * whose panel opens 8 px under the line. The bar stays across the visits:
 * when the page goes away the menu of the results closes, the box loses the
 * focus and empties, unless the visit goes to the *Search* page, whose query
 * fills it.
 *
 * @group navigation
 */
import { router } from '@inertiajs/vue3';
import { ArrowLeft, PanelLeftOpen } from '@lucide/vue';
import { nextTick, ref, useTemplateRef, watch } from 'vue';
import { useAppName } from '@/composables/useAppName';
import { usePageBack } from '@/composables/usePageBack';
import { useLeavePage } from '@/composables/useLeavePage';
import { SEARCH_MENU_ID, useSearchBox } from '@/composables/useSearchBox';
import type { TrailPiece } from '@/lib/pageTrail';
import { squareButton } from '@/lib/topBar';
import { search } from '@/routes';
import type { BellNotices } from '@/lib/notices';
import FmBell from './FmBell.vue';
import FmIcon from './FmIcon.vue';
import FmSearchField from './FmSearchField.vue';
import FmSearchMenu from './FmSearchMenu.vue';
import FmTrail from './FmTrail.vue';

/** The name of the app, for the name of the search box. */
const appName = useAppName();

const {
    mode,
    trail,
    section,
    unreadNotices,
    notices,
    query = '',
    collapsed = false,
} = defineProps<{
    /** The layout of the window: 20 px margins on the tablet, 40 px and Back with the trail on the computer. */
    mode: 'tablet' | 'computer';
    /** The pieces of the trail; the last one is the open page. */
    trail: TrailPiece[];
    /** The open page is the page of a section: no Back. */
    section: boolean;
    /** How many notices the account has not seen yet, for the dot of the bell. */
    unreadNotices: number;
    /** The notices of the bell; missing until the bell asks for them. */
    notices?: BellNotices;
    /** The text to put in the box, on the *Search* page. */
    query?: string;
    /** The sidebar is collapsed: the bar starts with the button that expands it. */
    collapsed?: boolean;
}>();

const emit = defineEmits<{
    /** *Expand the menu* was pressed: the layout expands the sidebar. */
    expand: [];
    /** The bell opened: the layout loads the notices. */
    loadNotices: [];
}>();

/** The text in the search box. */
const text = ref(query);

const field = useTemplateRef<InstanceType<typeof FmSearchField>>('field');
const menu = useTemplateRef<InstanceType<typeof FmSearchMenu>>('menu');

/** Searches the text of the box on the *Search* page. */
function submit(): void {
    const q = text.value.trim();

    router.visit(q === '' ? search.url() : search.url({ query: { q } }));
}

const {
    menuResults,
    enter,
    onKeydown,
    closeMenu,
    focused,
    onFocusIn,
    onFocusOut,
} = useSearchBox(text, menu, submit);

// A new query from the server (another search) replaces the text without opening the menu
watch(
    () => query,
    (value) => {
        text.value = value;
        void nextTick(closeMenu);
    },
);

// The bar survives the visit: the box starts again on the new page
useLeavePage((path) => {
    field.value?.blur();

    if (path !== search.url()) {
        text.value = '';
    }

    // after the watcher of the text, which opens the menu again
    void nextTick(closeMenu);
});

const {
    parent,
    visible: showBack,
    back,
} = usePageBack(
    () => trail,
    () => section,
);

/** Puts the cursor in the search box: the shortcut ⌘K of the layout calls it. */
function focusSearch(): void {
    field.value?.focus();
}

defineExpose({ focusSearch });
</script>

<template>
    <header
        class="sticky top-0 z-30 h-topbar border-b border-edge-soft bg-canvas"
        :class="mode === 'computer' ? 'px-10' : 'px-5'"
    >
        <div
            class="mx-auto flex h-full max-w-(--main-width) items-center gap-3"
        >
            <div class="flex min-w-0 grow items-center gap-3">
                <button
                    v-if="collapsed"
                    type="button"
                    :aria-label="$t('Expand the menu')"
                    :title="$t('Expand the menu')"
                    :class="squareButton"
                    class="cursor-pointer hover:bg-canvas"
                    @click="emit('expand')"
                >
                    <FmIcon :icon="PanelLeftOpen" :size="22" />
                </button>
                <component
                    :is="parent?.href ? 'a' : 'button'"
                    v-if="mode === 'computer' && showBack()"
                    :href="parent?.href"
                    :type="parent?.href ? undefined : 'button'"
                    :aria-label="$t('Back')"
                    :title="$t('Back')"
                    :class="squareButton"
                    @click="back"
                >
                    <FmIcon :icon="ArrowLeft" :size="20" />
                </component>
                <FmTrail v-if="mode === 'computer'" :pieces="trail" />
            </div>
            <div
                class="relative max-w-full min-w-60 transition-[width] duration-200 motion-reduce:transition-none"
                :class="focused || text !== '' ? 'w-105' : 'w-75'"
                @focusin="onFocusIn"
                @focusout="onFocusOut"
            >
                <FmSearchField
                    ref="field"
                    v-model="text"
                    :label="$t('Search in :app', { app: appName })"
                    shortcut
                    clearable
                    :controls="SEARCH_MENU_ID"
                    :expanded="menuResults !== null"
                    :active-descendant="
                        menuResults ? menu?.activeId : undefined
                    "
                    @submit="enter"
                    @escape="closeMenu"
                    @keydown="onKeydown"
                />
                <FmSearchMenu
                    v-if="menuResults"
                    :id="SEARCH_MENU_ID"
                    ref="menu"
                    :results="menuResults"
                    :query="menuResults.query"
                    class="max-w-none"
                    @mousedown.prevent
                />
            </div>
            <!-- The 44 px bell is centred in the 68 px bar: 20 px under it is 8 px under the line, as the boards draw it -->
            <FmBell
                :count="unreadNotices"
                :notices="notices"
                :side-offset="20"
                class="rounded-md border border-edge-soft bg-card"
                @open="emit('loadNotices')"
            />
        </div>
    </header>
</template>
