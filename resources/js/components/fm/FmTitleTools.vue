<script setup lang="ts">
/**
 * The tools beside the title of a page on the phone: the round magnifier that opens *Search* and the
 * round bell of the notices, 44 px each, 8 px apart, on `bg-card`. The tablet and the computer have
 * both in the top bar, so the pages show this only when `useLayoutMode()` says 'phone'. It reads the
 * new notices from the shared props and loads the notices of the menu when the bell opens.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { useBellNotices } from '@/composables/useBellNotices';
import { search } from '@/routes';
import FmBell from './FmBell.vue';
import FmIcon from './FmIcon.vue';

const { unread, notices, loadNotices } = useBellNotices();
</script>

<template>
    <div class="flex shrink-0 items-center gap-2">
        <Link
            :href="search.url()"
            :aria-label="$t('Search in Family Manager')"
            class="flex size-11 items-center justify-center rounded-full bg-card text-ink"
        >
            <FmIcon :icon="Search" :size="22" />
        </Link>
        <FmBell
            :count="unread"
            :notices="notices"
            class="rounded-full bg-card"
            @open="loadNotices"
        />
    </div>
</template>
