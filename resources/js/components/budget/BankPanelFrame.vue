<script setup lang="ts">
/**
 * The frame of the panels of *From the bank* that open beside the list: the group (*Dalla banca, il gruppo*) and the
 * movements like the one just sorted (*Dalla banca, altri movimenti come questo*). On the phone a bottom sheet; when
 * wide a panel in the flow of the page, in the column the page gives it, with the title and the × at the top. It is not
 * modal: it is a region named by its title, Esc closes it (a click on its text keeps the focus in it), at the opening
 * the focus goes to the title and at the closing it goes back to what opened it (or to `returnFocus` when that is
 * gone). It is never taller than the screen under the top bar (`--app-header`, with the 16 px the sticky column of
 * the page keeps above and below): the rest scrolls inside. The content goes in the slot.
 *
 * @group panels
 */
import { X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import { useWideLayout } from '@/composables/useWideLayout';
import { cn } from '@/lib/utils';

const {
    title,
    look = 'sheet',
    returnFocus,
} = defineProps<{
    /** The title of the panel. */
    title: string;
    /**
     * How it looks when wide, as the boards draw the two panels: `card` is the group (a card with the bigger title),
     * `sheet` the panel that follows an action (the radius and the shadow of a sheet).
     */
    look?: 'card' | 'sheet';
    /** Where the focus goes at the closing when what opened the panel is not in the page any more. */
    returnFocus?: () => HTMLElement | null;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

defineSlots<{
    /** The content under the title. */
    default: () => unknown;
}>();

const wide = useWideLayout();

/** The id of the title, which names the panel. */
const titleId = useId();

/** The title, which gets the focus at the opening. */
const heading = ref<HTMLElement | null>(null);

/** What had the focus when the panel opened. */
let opener: HTMLElement | null = null;

/** The classes of the panel, for each look. */
const panelClass: Record<'card' | 'sheet', string> = {
    card: cn('rounded-card p-6 shadow-card'),
    sheet: cn('rounded-sheet px-6 pt-5 pb-6 shadow-float'),
};

/** The classes of the title, for each look. */
const titleClass: Record<'card' | 'sheet', string> = {
    card: cn('text-stat'),
    sheet: cn('text-sheet-title'),
};

/** Remembers what had the focus and moves it to the title, once the panel is in the page. */
function focusTitle(): void {
    const active = document.activeElement;

    // The button that opened it may already be gone (the cards of the groups become a list): then `returnFocus`
    opener =
        active instanceof HTMLElement && active !== document.body
            ? active
            : null;
    void nextTick(() => heading.value?.focus());
}

/**
 * Gives the focus back to what opened the panel, or to `returnFocus` when that is gone, once the panel has left the
 * page; only when the focus was lost with it (a click elsewhere keeps where it went).
 */
function giveFocusBack(): void {
    const before = opener;

    opener = null;
    void nextTick(() => {
        const active = document.activeElement;

        if (active !== null && active !== document.body) {
            return;
        }

        const target = before?.isConnected ? before : (returnFocus?.() ?? null);

        target?.focus();
    });
}

watch(
    open,
    (now, was) => {
        if (!wide.value) {
            return;
        }

        if (now) {
            focusTitle();
        } else if (was) {
            giveFocusBack();
        }
    },
    { immediate: true },
);

// The page can take the panel away while it is open (the group closed): the focus goes back all the same
onBeforeUnmount(() => {
    if (wide.value && open.value) {
        giveFocusBack();
    }
});
</script>

<template>
    <FmBottomSheet v-if="!wide" v-model:open="open" :title="title">
        <slot />
    </FmBottomSheet>
    <aside
        v-else-if="open"
        :aria-labelledby="titleId"
        tabindex="-1"
        :class="
            cn(
                'flex max-h-[calc(100dvh-var(--app-header,0px)-(--spacing(8)))] w-full min-w-0 flex-col gap-3.5 overflow-y-auto bg-card outline-none *:shrink-0',
                panelClass[look],
            )
        "
        @keydown.esc.stop="open = false"
    >
        <div class="flex items-center justify-between gap-3">
            <h2
                :id="titleId"
                ref="heading"
                tabindex="-1"
                :class="cn('m-0 text-ink outline-none', titleClass[look])"
            >
                {{ title }}
            </h2>
            <button
                type="button"
                :aria-label="$t('Close')"
                class="-mr-2.5 flex size-11 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-ink-soft"
                @click="open = false"
            >
                <X aria-hidden="true" :size="look === 'card' ? 20 : 22" />
            </button>
        </div>
        <slot />
    </aside>
</template>
