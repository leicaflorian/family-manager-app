<script setup lang="ts">
/**
 * The layout of the pages of the app for who is signed in: below 1024 px the
 * bottom bar of the phone, from 1024 px the sidebar. The marked section comes
 * from the address of the page. While a form has changes not saved, it asks
 * before the page changes or the tab closes.
 */
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';
import FmBottomBar from '@/components/fm/FmBottomBar.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import FmSidebar from '@/components/fm/FmSidebar.vue';
import type { Section } from '@/components/fm/navigation';
import { navigationItems, sectionOf } from '@/components/fm/navigation';
import { Toaster } from '@/components/ui/sonner';
import { answerLeave, guardVisits, leaveQuestion } from '@/lib/unsavedChanges';

defineSlots<{
    /** The page. */
    default: () => unknown;
}>();

const page = usePage();
const items = navigationItems();

const path = computed((): string => page.url.split('?')[0] ?? '/');
const active = computed((): Section => sectionOf(path.value, items));
const areas = computed((): App.Data.AreaCountData[] => page.props.areas ?? []);
const user = computed((): string => page.props.auth?.user?.name ?? '');
const family = computed((): string => page.props.family?.name ?? '');

let stopGuard: (() => void) | undefined;
onMounted(() => {
    stopGuard = guardVisits();
});
onUnmounted(() => stopGuard?.());

/**
 * Closing the question in any way but «Leave without saving» is staying.
 *
 * @param open - the question is open
 */
function onQuestion(open: boolean): void {
    if (!open && leaveQuestion.open) {
        answerLeave(false);
    }
}
</script>

<template>
    <div class="flex min-h-dvh bg-canvas text-ink">
        <FmSidebar
            class="sticky top-0 hidden h-dvh lg:flex"
            :active="active"
            :path="path"
            :items="items"
            :areas="areas"
            :family="family"
            :user="user"
        />
        <div class="flex min-w-0 grow flex-col">
            <main
                class="@container grow px-(--main-gutter) pt-4 pb-6 [--main-gutter:--spacing(5)] lg:pt-8 lg:pb-12 lg:[--main-gutter:--spacing(10)]"
            >
                <div
                    class="mx-auto flex max-w-(--main-width) flex-col gap-4 lg:gap-6"
                >
                    <slot />
                </div>
            </main>
            <div class="sticky bottom-0 z-30 lg:hidden">
                <FmBottomBar :active="active" :items="items" />
            </div>
        </div>
        <Toaster />
        <FmConfirm
            :open="leaveQuestion.open"
            :question="$t('Leave without saving?')"
            :text="
                $t('You have changes not saved: if you leave, you lose them.')
            "
            :confirm-label="$t('Leave without saving')"
            @update:open="onQuestion"
            @confirm="answerLeave(true)"
        />
    </div>
</template>
