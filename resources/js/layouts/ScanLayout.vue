<script setup lang="ts">
/**
 * The layout of the *Scan*: the whole screen to the page, which has its own bars at the top and at the bottom,
 * without the bar of the sections (a linked phone sees nothing else of the app). On the tablet, for who signed in
 * with the password, the photos keep the top bar of the app over them (`AppLayout` bare), as the board
 * *Scansione-pc*. While pages wait to be uploaded, it asks before the page changes or the tab closes.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LeaveGuard from '@/components/layout/LeaveGuard.vue';
import FmToaster from '@/components/fm/FmToaster.vue';
import { useLayoutMode } from '@/composables/useLayoutMode';
import AppLayout from './AppLayout.vue';

defineSlots<{
    /** The page. */
    default: () => unknown;
}>();

const page = usePage();
const mode = useLayoutMode();

/** The photos on the tablet, not on a linked phone: under the top bar of the app. */
const withTopBar = computed(
    (): boolean =>
        mode.value === 'tablet' &&
        page.component === 'scan/Scan' &&
        page.props.linked_phone !== true,
);
</script>

<template>
    <AppLayout v-if="withTopBar" bare>
        <slot />
    </AppLayout>
    <div v-else class="flex min-h-dvh flex-col bg-canvas text-ink">
        <slot />
        <FmToaster />
        <LeaveGuard />
    </div>
</template>
