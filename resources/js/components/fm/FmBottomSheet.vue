<script setup lang="ts">
/**
 * The panel that rises from the bottom over the darkened page, for short actions
 * and filters. On the computer it is a window in the middle. It closes with the
 * handle, with the ×, with Esc and by tapping outside; while it is open the focus
 * stays inside.
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

const { title } = defineProps<{
    /** The title of the panel. */
    title: string;
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });

defineSlots<{
    /** The content of the panel. */
    default: () => unknown;
}>();

// The panel is white, also when it opens from the coloured top of a card
provideOnColor(false);
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-40 bg-[rgba(24,33,47,0.45)] duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
            />
            <DialogContent
                :aria-describedby="undefined"
                class="fixed inset-x-0 bottom-0 z-50 flex max-h-[90dvh] flex-col gap-3.5 overflow-y-auto rounded-t-3xl bg-white px-5 pt-2.5 pb-[max(20px,env(safe-area-inset-bottom))] text-ink duration-200 outline-none slide-in-from-bottom slide-out-to-bottom data-[state=closed]:animate-out data-[state=open]:animate-in motion-reduce:animate-none lg:inset-x-auto lg:top-1/2 lg:bottom-auto lg:left-1/2 lg:w-full lg:max-w-[560px] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-3xl lg:p-7 lg:shadow-[0_24px_60px_rgba(24,33,47,0.25)] lg:fade-in-0 lg:fade-out-0 lg:zoom-in-95 lg:zoom-out-95 lg:slide-in-from-bottom-2 lg:slide-out-to-bottom-2"
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
