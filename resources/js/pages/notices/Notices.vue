<script setup lang="ts">
/**
 * *Notices*, the page of all the notices, where *See all the notices* of the
 * bell leads: the title with *Mark all as read* while something is new, the
 * search (sent with Enter, it goes back to the first page), the notices of the
 * page (`FmNoticeList`, 20 at a time, the new ones first) and *Previous* and
 * *Next* under them. A search that finds nothing says so; with no notice at
 * all the page has only the word that they arrive here.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmNoticeList from '@/components/fm/FmNoticeList.vue';
import FmPager from '@/components/fm/FmPager.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { usePageTrail } from '@/composables/usePageTrail';
import { dashboard, notices } from '@/routes';
import { readAll } from '@/routes/notices';

const props = defineProps<{
    /** The new notices of this page, the most recent first. */
    unread: App.Data.ReminderNoticeData[];
    /** The seen notices of this page, the most recent first. */
    seen: App.Data.ReminderNoticeData[];
    /** The text searched, empty for all. */
    search: string;
    /** The address of the next page, if there is one. */
    nextPage: string | null;
    /** The address of the previous page, if there is one. */
    previousPage: string | null;
    /** The account has at least one notice, whatever the search. */
    hasNotices: boolean;
}>();

usePageTrail(() => [
    { label: trans('Home'), href: dashboard().url },
    { label: trans('Notices') },
]);

const page = usePage();

/** Some notice is new, on this page or on another. */
const anyUnread = computed(() => (page.props.unread_notices ?? 0) > 0);

/** The search found nothing. */
const nothingFound = computed(
    () => props.unread.length === 0 && props.seen.length === 0,
);

/** The text in the search box, sent with Enter. */
const text = ref(props.search);
watch(
    () => props.search,
    (value) => (text.value = value),
);

/** Sends the search, from the first page; an empty one shows all the notices again. */
function searchNotices(): void {
    const search = text.value.trim();

    router.get(notices().url, search === '' ? {} : { search }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

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
            <template v-if="anyUnread" #actions>
                <FmSectionLink @click="markAll">{{
                    $t('Mark all as read')
                }}</FmSectionLink>
            </template>
        </FmTitle>

        <FmNoticeList v-if="!hasNotices" :unread="[]" :seen="[]" />

        <template v-else>
            <form role="search" @submit.prevent="searchNotices">
                <FmSearchField v-model="text" :label="$t('Search a notice')" />
            </form>

            <FmEmpty
                v-if="nothingFound"
                :icon="Search"
                :title="$t('No notice found')"
                :text="$t('Change the search.')"
            />
            <template v-else>
                <FmNoticeList :unread="unread" :seen="seen">
                    <FmPager :previous="previousPage" :next="nextPage" />
                </FmNoticeList>
            </template>
        </template>
    </div>
</template>
