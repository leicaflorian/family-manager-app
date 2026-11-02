<script setup lang="ts">
/**
 * *Notices*, the page of the bell, for whoever arrives with its address: the
 * title with *Mark all as read* while something is new, then the notices
 * (`FmNoticeList`), the same the menu of the bell shows on the Home.
 */
import { Head, router } from '@inertiajs/vue3';
import FmNoticeList from '@/components/fm/FmNoticeList.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { dashboard } from '@/routes';
import { readAll } from '@/routes/notices';

const { unread, seen } = defineProps<{
    /** The notices the person has not seen yet, the most recent first. */
    unread: App.Data.ReminderNoticeData[];
    /** The notices already seen, the most recent first. */
    seen: App.Data.ReminderNoticeData[];
}>();

/** Marks every notice as read. */
function markAll(): void {
    router.post(readAll.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Notices')" />

    <div class="flex flex-col gap-5">
        <FmTitle
            :title="$t('Notices')"
            :back="dashboard().url"
            :back-label="$t('Home')"
        >
            <template v-if="unread.length > 0" #actions>
                <FmSectionLink @click="markAll">{{
                    $t('Mark all as read')
                }}</FmSectionLink>
            </template>
        </FmTitle>

        <FmNoticeList :unread="unread" :seen="seen" />
    </div>
</template>
