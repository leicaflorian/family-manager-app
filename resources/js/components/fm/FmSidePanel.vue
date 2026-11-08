<script setup lang="ts">
/**
 * A panel floating on the right of the page, over the list, on a computer that
 * is not wide enough for a third column: the preview of what is chosen in the
 * list. The page behind stays usable, so choosing another row changes what the
 * panel shows. It closes with Esc (unless a menu inside it is open) and with
 * the × the page puts in its content. Used by *Documents* between 1024 and
 * 1439 px.
 *
 * @group panels
 */
import { useEventListener } from '@vueuse/core';

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

/**
 * Asks to close on Esc, but not when it is meant for an open menu: the menu
 * has the focus while it is open and closes on its own.
 *
 * @param event - the key pressed anywhere in the page
 */
function closeOnEscape(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || event.defaultPrevented) {
        return;
    }

    if (
        event.target instanceof Element &&
        event.target.closest('[role="menu"], [role="listbox"]') !== null
    ) {
        return;
    }

    emit('close');
}

useEventListener(document, 'keydown', closeOnEscape);
</script>

<template>
    <aside
        role="dialog"
        aria-modal="false"
        :aria-label="label"
        class="fixed top-4 right-4 z-30 max-h-[calc(100dvh-2rem)] w-[420px] max-w-[calc(100vw-2rem)] animate-in overflow-y-auto rounded-card-lg shadow-dialog duration-200 fade-in-0 slide-in-from-right-4 motion-reduce:animate-none"
    >
        <slot />
    </aside>
</template>
