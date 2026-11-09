<script setup lang="ts">
/**
 * The layout of the *Scan*: the whole screen to the page, which has its own bars at the top and at the bottom,
 * without the bar of the sections (a linked phone sees nothing else of the app). While pages wait to be
 * uploaded, it asks before the page changes or the tab closes.
 */
import LeaveGuard from '@/components/layout/LeaveGuard.vue';
import { Toaster } from '@/components/ui/sonner';

defineSlots<{
    /** The page. */
    default: () => unknown;
}>();
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-canvas text-ink">
        <slot />
        <Toaster />
        <LeaveGuard />
    </div>
</template>
