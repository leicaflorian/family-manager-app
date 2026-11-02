<script setup lang="ts">
/**
 * *Notices*, the page of the bell: the notices not seen yet on top, then the
 * ones already seen, each with what is due, the words of the deadline of the
 * day it went out and, if the reminder is late or done, a label. A new notice
 * is marked as seen when it is opened, and *Mark all as read* does it for all.
 * The same notices arrive by email.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { BellOff, Mail } from '@lucide/vue';
import { computed } from 'vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNoticeRow from '@/components/fm/FmNoticeRow.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { NoticeItem } from '@/lib/reminders';
import { toNoticeItem } from '@/lib/reminders';
import { dashboard, settings } from '@/routes';
import { read, readAll } from '@/routes/notices';

const { unread, seen } = defineProps<{
    /** The notices the person has not seen yet, the most recent first. */
    unread: App.Data.ReminderNoticeData[];
    /** The notices already seen, the most recent first. */
    seen: App.Data.ReminderNoticeData[];
}>();

/** The new notices, as rows. */
const fresh = computed((): NoticeItem[] => unread.map(toNoticeItem));

/** The seen notices, as rows. */
const old = computed((): NoticeItem[] => seen.map(toNoticeItem));

/** Marks every notice as read. */
function markAll(): void {
    router.post(readAll.url(), {}, { preserveScroll: true });
}

/**
 * Marks a new notice as read: the row leads to its page, and the server marks it on the way.
 *
 * @param notice - the notice that was opened
 */
function opened(notice: NoticeItem): void {
    if (!notice.read) {
        router.post(read.url(notice.id));
    }
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
            <template v-if="fresh.length > 0" #actions>
                <FmSectionLink @click="markAll">{{
                    $t('Mark all as read')
                }}</FmSectionLink>
            </template>
        </FmTitle>

        <FmEmpty
            v-if="fresh.length === 0 && old.length === 0"
            :icon="BellOff"
            :title="$t('No notices yet')"
            :text="
                $t(
                    'The notices of the deadlines arrive here on the morning they are due.',
                )
            "
        />

        <FmCard v-if="fresh.length > 0" :title="$t('New notices')" list>
            <FmNoticeRow
                v-for="notice in fresh"
                :key="notice.id"
                :notice="notice"
                @open="opened(notice)"
            />
        </FmCard>

        <FmCard v-if="old.length > 0" :title="$t('Already seen')" list>
            <FmNoticeRow
                v-for="notice in old"
                :key="notice.id"
                :notice="notice"
                @open="opened(notice)"
            />
        </FmCard>

        <p class="m-0 flex items-start gap-2 text-sm text-ink-soft">
            <FmIcon :icon="Mail" :size="16" class="mt-0.5 shrink-0" />
            <span
                >{{ $t('The same notices arrive by email.') }}
                <Link :href="settings().url" class="font-semibold">{{
                    $t('Choose which ones in Settings')
                }}</Link></span
            >
        </p>
    </div>
</template>
