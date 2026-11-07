<script setup lang="ts">
/**
 * The panel that rises from the bottom over the darkened page, for short actions
 * and filters. On the computer it is a window in the middle. It closes with the
 * handle, with the ×, with Esc and by tapping outside; while it is open the focus
 * stays inside. With `beforeClose` these ways ask first, as a form with changes
 * not saved does. Esc in an open menu of a field closes the menu only.
 *
 * @group panels
 */
import { X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { provideOnColor } from '@/composables/useOnColor';
import FmIcon from './FmIcon.vue';

const { title, beforeClose, above } = defineProps<{
    /** The title of the panel. */
    title: string;
    /**
     * Above every other panel, whatever their order in the page: a question
     * asked while a panel is open («Leave without saving?», «Delete…?»).
     */
    above?: boolean;
    /**
     * Asked before closing with the handle, the ×, Esc or a tap outside: the
     * panel stays open when it gives false. The buttons of the panel close it
     * without asking.
     */
    beforeClose?: () => Promise<boolean>;
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });

defineSlots<{
    /** The content of the panel. */
    default: () => unknown;
}>();

/**
 * Opens or closes the panel, asking first when it closes and `beforeClose` is there.
 *
 * @param next - open or closed
 */
async function change(next: boolean): Promise<void> {
    if (!next && beforeClose && !(await beforeClose())) {
        return;
    }

    open.value = next;
}

// The panel is white, also when it opens from the coloured top of a card
provideOnColor(false);

/** The Esc being pressed started in an open menu of a field. */
let escapeInMenu = false;

/**
 * Notes whether Esc starts in an open menu of a field (a FormKit list marks
 * its field with `data-expanded`). It runs before the field: FormKit closes
 * its list on the same key, and by the time the panel hears it the mark is
 * gone.
 *
 * @param event - the key pressed, in the capture phase
 */
function noteEscape(event: KeyboardEvent): void {
    escapeInMenu =
        event.key === 'Escape' &&
        event.target instanceof Element &&
        event.target.closest('[data-expanded]') !== null;
}

/**
 * Esc in an open menu of a field closes only the menu, not the panel.
 *
 * @param event - the Esc the panel would close with
 */
function keepOpenForMenu(event: KeyboardEvent): void {
    if (escapeInMenu) {
        event.preventDefault();
    }

    escapeInMenu = false;
}
</script>

<template>
    <DialogRoot :open="open" @update:open="change">
        <DialogPortal>
            <DialogOverlay
                :class="above ? 'z-[60]' : 'z-40'"
                class="fixed inset-0 bg-overlay duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
            />
            <DialogContent
                :aria-describedby="undefined"
                @keydown.capture="noteEscape"
                @escape-key-down="keepOpenForMenu"
                :class="above ? 'z-[70]' : 'z-50'"
                class="fixed inset-x-0 bottom-0 flex max-h-[90dvh] flex-col gap-3.5 overflow-y-auto rounded-t-sheet bg-card px-5 pt-2.5 pb-[max(20px,env(safe-area-inset-bottom))] text-ink duration-200 outline-none slide-in-from-bottom slide-out-to-bottom data-[state=closed]:animate-out data-[state=open]:animate-in motion-reduce:animate-none lg:inset-x-auto lg:top-1/2 lg:bottom-auto lg:left-1/2 lg:w-full lg:max-w-[560px] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-sheet lg:p-7 lg:shadow-dialog lg:fade-in-0 lg:fade-out-0 lg:zoom-in-95 lg:zoom-out-95 lg:slide-in-from-bottom-2 lg:slide-out-to-bottom-2"
            >
                <DialogClose
                    :aria-label="$t('Close the panel')"
                    tabindex="-1"
                    class="mx-auto flex h-5 w-16 items-center justify-center lg:hidden"
                >
                    <span class="h-[5px] w-10 rounded-full bg-edge" />
                </DialogClose>
                <div class="flex items-center justify-between gap-3">
                    <DialogTitle
                        class="m-0 text-[19px] font-extrabold lg:text-[22px]"
                        >{{ title }}</DialogTitle
                    >
                    <DialogClose
                        :aria-label="$t('Close')"
                        class="-mr-2.5 flex size-11 shrink-0 items-center justify-center text-ink-soft"
                    >
                        <FmIcon :icon="X" :size="22" />
                    </DialogClose>
                </div>
                <slot />
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
