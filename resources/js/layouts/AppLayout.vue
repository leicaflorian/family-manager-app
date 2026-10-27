<script setup lang="ts">
/**
 * The layout of the pages of the app for who is signed in: below 1024 px the
 * bottom bar of the phone, from 1024 px the sidebar. The marked section comes
 * from the address of the page.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FmBottomBar from '@/components/fm/FmBottomBar.vue';
import FmSidebar from '@/components/fm/FmSidebar.vue';
import type { Section } from '@/components/fm/navigation';
import { navigationItems, sectionOf } from '@/components/fm/navigation';
import { Toaster } from '@/components/ui/sonner';

defineSlots<{
    /** The page. */
    default: () => unknown;
}>();

const page = usePage();
const items = navigationItems();

const active = computed((): Section =>
    sectionOf(page.url.split('?')[0] ?? '/', items),
);
const user = computed((): string => page.props.auth?.user?.name ?? '');
const family = computed((): string => page.props.family?.name ?? '');
</script>

<template>
    <div class="flex min-h-dvh bg-canvas text-ink">
        <FmSidebar
            class="sticky top-0 hidden h-dvh lg:flex"
            :active="active"
            :items="items"
            :family="family"
            :user="user"
        />
        <div class="flex min-w-0 grow flex-col">
            <main class="grow px-5 pt-4 pb-6 lg:px-10 lg:pt-8 lg:pb-12">
                <div
                    class="mx-auto flex max-w-[1120px] flex-col gap-4 lg:gap-6"
                >
                    <slot />
                </div>
            </main>
            <div class="sticky bottom-0 z-30 lg:hidden">
                <FmBottomBar :active="active" :items="items" />
            </div>
        </div>
        <Toaster />
    </div>
</template>
