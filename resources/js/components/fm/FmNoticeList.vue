<script setup lang="ts">
/**
 * The notices of the bell: the ones not seen yet in the box *New notices*,
 * then the ones already seen, each an `FmNoticeRow`; with no notice at all, a
 * word that they arrive here. It ends saying the same notices arrive by email,
 * with the way to *Settings*; what the page adds (its pages) goes before
 * that line. Used by the page *Notices* and by the menu of
 * the bell (`FmBell`), which put their title above it.
 *
 * @group sections
 */
import { Link } from '@inertiajs/vue3';
import { BellOff, Mail } from '@lucide/vue';
import { computed } from 'vue';
import type { NoticeItem } from '@/lib/reminders';
import { toNoticeItem } from '@/lib/reminders';
import { settings } from '@/routes';
import { read } from '@/routes/notices';
import FmCard from './FmCard.vue';
import FmEmpty from './FmEmpty.vue';
import FmIcon from './FmIcon.vue';
import FmNoticeRow from './FmNoticeRow.vue';

const { unread, seen } = defineProps<{
    /** The notices the person has not seen yet, the most recent first. */
    unread: App.Data.ReminderNoticeData[];
    /** The notices already seen, the most recent first. */
    seen: App.Data.ReminderNoticeData[];
}>();

defineSlots<{
    /** What goes under the notices, before the line about email: the pages of *Notices*. */
    default?: () => unknown;
}>();

/** The new notices, as rows. */
const fresh = computed((): NoticeItem[] => unread.map(toNoticeItem));

/** The seen notices, as rows. */
const old = computed((): NoticeItem[] => seen.map(toNoticeItem));
</script>

<template>
    <div class="flex flex-col gap-5">
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
                :read-href="read.url(notice.id)"
            />
        </FmCard>

        <FmCard v-if="old.length > 0" :title="$t('Already seen')" list>
            <FmNoticeRow
                v-for="notice in old"
                :key="notice.id"
                :notice="notice"
                :read-href="read.url(notice.id)"
            />
        </FmCard>

        <slot />

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
