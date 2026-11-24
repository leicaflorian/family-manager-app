<script setup lang="ts">
/**
 * A panel floating on the right of the page, over the list, on a computer that
 * is not wide enough for a third column: the preview of what is chosen in the
 * list. The page behind stays usable, so choosing another row changes what the
 * panel shows. It closes with Esc (unless a menu inside it is open) and with
 * the × the page puts in its content. It floats 16 px under the sticky top of
 * the layout (`--app-header`). Used by *Documents* between 1280 and 1439 px.
 * Inside it the buttons are those of a white surface, also when it opens from
 * the coloured top of a card.
 *
 * @group panels
 */
import { useEscapeClose } from '@/composables/useEscapeClose';
import { provideOnColor } from '@/composables/useOnColor';

const { label } = defineProps<{
    /** What the panel shows, said to screen readers. */
    label: string;
}>();

const emit = defineEmits<{
    /** Esc was pressed: the page closes the panel. */
    close: [];
}>();

defineSlots<{
    /** The content of the panel. */
    default: () => unknown;
}>();

// The panel is white, also when it opens from the coloured top of a card
provideOnColor(false);

// Esc closes the panel, but not when it is meant for an open menu inside it
useEscapeClose(() => emit('close'));
</script>

<template>
    <aside
        role="dialog"
        aria-modal="false"
        :aria-label="label"
        class="fixed top-[calc(var(--app-header,0px)+--spacing(4))] right-4 z-30 max-h-[calc(100dvh-var(--app-header,0px)-(--spacing(8)))] w-[420px] max-w-[calc(100vw-2rem)] animate-in overflow-y-auto rounded-card-lg shadow-dialog duration-200 fade-in-0 slide-in-from-right-4 motion-reduce:animate-none"
    >
        <slot />
    </aside>
</template>
