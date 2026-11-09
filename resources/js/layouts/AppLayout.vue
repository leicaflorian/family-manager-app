<script setup lang="ts">
/**
 * The layout of the pages of the app for who is signed in: below 1024 px the
 * bottom bar of the phone, from 1024 px the sidebar. The marked section comes
 * from the address of the page. While a form has changes not saved, it asks
 * before the page changes or the tab closes. During a visit of the owner
 * (*Enter as…*) a strip at the top says whose eyes the app is seen with, and
 * *Leave* ends the visit.
 */
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FmBottomBar from '@/components/fm/FmBottomBar.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmSidebar from '@/components/fm/FmSidebar.vue';
import type { Section } from '@/components/fm/navigation';
import { navigationItems, sectionOf } from '@/components/fm/navigation';
import LeaveGuard from '@/components/layout/LeaveGuard.vue';
import { Toaster } from '@/components/ui/sonner';
import { leave } from '@/routes/visit';

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
const toSort = computed((): number => page.props.to_sort_count ?? 0);
const family = computed((): string => page.props.family?.name ?? '');
const impersonating = computed(
    (): string | null => page.props.impersonating ?? null,
);

/** Ends the visit of the owner: the server sends the browser back to the panel. */
function leaveVisit(): void {
    router.post(leave.url());
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
            :upload-count="toSort"
        />
        <div class="flex min-w-0 grow flex-col">
            <div
                v-if="impersonating"
                role="status"
                class="sticky top-0 z-40 flex items-center justify-between gap-3 bg-warning-soft px-(--main-gutter) py-2 text-label text-warning [--main-gutter:--spacing(5)] lg:[--main-gutter:--spacing(10)]"
            >
                <span>{{
                    $t('You are looking as :name', { name: impersonating })
                }}</span>
                <FmButton variant="outline" @click="leaveVisit">{{
                    $t('Leave')
                }}</FmButton>
            </div>
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
                <FmBottomBar
                    :active="active"
                    :items="items"
                    :upload-count="toSort"
                />
            </div>
        </div>
        <Toaster />
        <LeaveGuard />
    </div>
</template>
