<script setup lang="ts">
/**
 * The bar on top of every page of the tablet and of the computer, 68 px high
 * and sticky, with a 1 px line under it, as the card *Barra in alto* draws it.
 *
 * On the computer it starts with Back (only on an inner page, when a page of
 * the app comes before or the trail has a page above) and the trail
 * (`FmTrail`). On the tablet it starts with the button of the menu, with the
 * files to sort on a blue badge, then the logo with the name of the family;
 * there is no Back and no trail. On the right, in both, the search of the
 * whole app (300 px, 420 px while it has the focus or some text, with the menu
 * of the first results under it; Enter opens the *Search* page) and the bell,
 * whose panel opens 8 px under the line.
 *
 * @group navigation
 */
import { router } from '@inertiajs/vue3';
import { ArrowLeft, Menu } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import { SEARCH_MENU_ID, useSearchBox } from '@/composables/useSearchBox';
import { countBadge } from '@/lib/badge';
import type { TrailPiece } from '@/lib/pageTrail';
import { cn } from '@/lib/utils';
import { canGoBack } from '@/lib/visitHistory';
import { search } from '@/routes';
import type { BellNotices } from '@/lib/notices';
import FmBell from './FmBell.vue';
import FmIcon from './FmIcon.vue';
import FmLogo from './FmLogo.vue';
import FmSearchField from './FmSearchField.vue';
import FmSearchMenu from './FmSearchMenu.vue';
import FmTrail from './FmTrail.vue';

const {
    mode,
    trail,
    section,
    family,
    uploadCount = 0,
    unreadNotices,
    notices,
    query = '',
    menuOpen = false,
} = defineProps<{
    /** The layout of the window: the tablet has the menu button, the computer Back and the trail. */
    mode: 'tablet' | 'computer';
    /** The pieces of the trail; the last one is the open page. */
    trail: TrailPiece[];
    /** The open page is the page of a section: no Back. */
    section: boolean;
    /** The name of the family, under the logo on the tablet. */
    family: string;
    /** How many files wait in *To sort*, on the badge of the menu button. */
    uploadCount?: number;
    /** How many notices the account has not seen yet, for the dot of the bell. */
    unreadNotices: number;
    /** The notices of the bell; missing until the bell asks for them. */
    notices?: BellNotices;
    /** The text to put in the box, on the *Search* page. */
    query?: string;
    /** The menu of the tablet is open. */
    menuOpen?: boolean;
}>();

const emit = defineEmits<{
    /** The menu button of the tablet was pressed. */
    openMenu: [];
    /** The bell opened: the layout loads the notices. */
    loadNotices: [];
}>();

/** The id of the menu of the tablet, which the menu button opens. */
const MENU_ID = 'app-menu';

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

/** The nearest page above the open one in the trail, where Back leads without a history. */
const parentHref = computed(
    (): string | undefined =>
        trail
            .slice(0, -1)
            .reverse()
            .find((piece) => piece.href !== undefined)?.href,
);

/**
 * Whether Back is shown: on an inner page of the computer, when it can go
 * back in the history or up in the trail. `canGoBack()` reads a reactive
 * depth, so the template that calls this renders again after each visit.
 *
 * @returns true when Back is shown
 */
function showBack(): boolean {
    return (
        mode === 'computer' &&
        !section &&
        (canGoBack() || parentHref.value !== undefined)
    );
}

/**
 * Back: the previous page of the history when it is a page of the app,
 * otherwise the page above in the trail. With a modifier key on the link
 * the browser opens it as it wants (a new tab, a new window); the button
 * without a page above always goes back.
 *
 * @param event - the click on Back
 */
function back(event: MouseEvent): void {
    const modified =
        event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

    if (modified && parentHref.value !== undefined) {
        return;
    }

    event.preventDefault();

    if (canGoBack()) {
        window.history.back();
    } else if (parentHref.value !== undefined) {
        router.visit(parentHref.value);
    }
}

/** The name of the menu button, with the files to sort. */
const menuLabel = computed((): string =>
    uploadCount > 0
        ? transChoice(
              '{1} Menu, :count file to sort|[2,*] Menu, :count files to sort',
              uploadCount,
          )
        : trans('Menu'),
);

/** The square buttons of the bar: Back, the menu and the bell. */
const squareButton = cn(
    'relative flex size-11 shrink-0 items-center justify-center rounded-md border border-edge-soft bg-card text-ink',
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
        :class="mode === 'computer' ? 'px-10' : 'px-8'"
    >
        <div
            class="mx-auto flex h-full max-w-(--main-width) items-center"
            :class="mode === 'computer' ? 'gap-3' : 'gap-3.5'"
        >
            <div
                v-if="mode === 'computer'"
                class="flex min-w-0 grow items-center gap-3"
            >
                <component
                    :is="parentHref ? 'a' : 'button'"
                    v-if="showBack()"
                    :href="parentHref"
                    :type="parentHref ? undefined : 'button'"
                    :aria-label="$t('Back')"
                    :title="$t('Back')"
                    :class="squareButton"
                    @click="back"
                >
                    <FmIcon :icon="ArrowLeft" :size="20" />
                </component>
                <FmTrail :pieces="trail" />
            </div>
            <template v-else>
                <button
                    type="button"
                    :aria-label="menuLabel"
                    :aria-expanded="menuOpen"
                    :aria-controls="MENU_ID"
                    :class="squareButton"
                    class="cursor-pointer"
                    @click="emit('openMenu')"
                >
                    <FmIcon :icon="Menu" :size="22" />
                    <span
                        v-if="uploadCount > 0"
                        aria-hidden="true"
                        class="absolute -top-1.5 -right-1.5 flex h-[22px] min-w-[22px] items-center justify-center rounded-full border-2 border-canvas bg-action px-1.5 text-tiny text-on-action"
                        >{{ countBadge(uploadCount) }}</span
                    >
                </button>
                <span class="flex items-center gap-2.5">
                    <FmLogo :size="36" />
                    <span class="flex flex-col">
                        <span class="text-amount text-ink">Family Manager</span>
                        <span class="text-meta text-ink-soft">{{
                            $t(':family family', { family })
                        }}</span>
                    </span>
                </span>
                <span class="grow" />
            </template>
            <div
                class="relative max-w-full min-w-60 transition-[width] duration-200 motion-reduce:transition-none"
                :class="focused || text !== '' ? 'w-105' : 'w-75'"
                @focusin="onFocusIn"
                @focusout="onFocusOut"
            >
                <FmSearchField
                    ref="field"
                    v-model="text"
                    :label="$t('Search in Family Manager')"
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
            <FmBell
                :count="unreadNotices"
                :notices="notices"
                class="rounded-md border border-edge-soft bg-card"
                @open="emit('loadNotices')"
            />
        </div>
    </header>
</template>
