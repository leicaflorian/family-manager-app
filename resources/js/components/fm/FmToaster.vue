<script setup lang="ts">
/**
 * The short messages that confirm what was just done («Saved», «Done», «Thanks»)
 * or say it did not work: dark with light text, so they stand out on any page
 * (in the dark theme the two colors swap), and gone by themselves after a few
 * seconds. The icon tells the kind of message in the color of the text, and an
 * action («Undo») sits on the right. The layout holds one, and the pages send
 * the messages with `toast()` of `vue-sonner`.
 *
 * @group panels
 */
import {
    CircleCheckIcon,
    InfoIcon,
    Loader2Icon,
    OctagonXIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import { computed } from 'vue';
import type { ToasterProps } from 'vue-sonner';
import { Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';
import { cn } from '@/lib/utils';

const { aboveBottomBar = false } = defineProps<{
    /** Keeps the messages above the bottom bar of the phone. */
    aboveBottomBar?: boolean;
}>();

/** The bottom bar of the phone is 84 px tall, and its + rises 28 px above it. */
const BOTTOM_BAR = 112;

/** The distance from the edges of the window on tablet and computer. */
const offset = computed((): ToasterProps['offset'] => ({
    bottom: aboveBottomBar ? BOTTOM_BAR + 24 : 24,
    right: 24,
}));

/** The distance from the edges on a narrow phone, where a message is as wide as the page. */
const mobileOffset = computed((): ToasterProps['mobileOffset'] => ({
    bottom: aboveBottomBar ? BOTTOM_BAR + 16 : 16,
    left: 16,
    right: 16,
}));

/** The look of every message: sonner's own styles are off, the design system's classes take their place. */
const toastOptions: ToasterProps['toastOptions'] = {
    unstyled: true,
    classes: {
        toast: cn(
            'flex w-full items-center gap-3 rounded-lg bg-ink px-4 py-3.5 text-canvas shadow-menu',
        ),
        content: cn('min-w-0 flex-1'),
        title: cn('text-body-sm'),
        description: cn('text-meta'),
        icon: cn('flex size-icon-row shrink-0 items-center justify-center'),
        actionButton: cn(
            '-my-2 shrink-0 cursor-pointer rounded-tag px-2 py-2 text-label underline focus-visible:ring-2 focus-visible:ring-canvas focus-visible:outline-none',
        ),
    },
};
</script>

<template>
    <Toaster
        position="bottom-right"
        :offset="offset"
        :mobile-offset="mobileOffset"
        :toast-options="toastOptions"
    >
        <template #success-icon>
            <CircleCheckIcon class="size-5" aria-hidden="true" />
        </template>
        <template #info-icon>
            <InfoIcon class="size-5" aria-hidden="true" />
        </template>
        <template #warning-icon>
            <TriangleAlertIcon class="size-5" aria-hidden="true" />
        </template>
        <template #error-icon>
            <OctagonXIcon class="size-5" aria-hidden="true" />
        </template>
        <template #loading-icon>
            <Loader2Icon class="size-5 animate-spin" aria-hidden="true" />
        </template>
    </Toaster>
</template>
