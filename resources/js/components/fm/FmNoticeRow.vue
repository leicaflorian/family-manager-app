<script setup lang="ts">
/**
 * A notice of the bell in *Notices*: the icon with the colour of the area (grey
 * once seen), what is due, the line of the deadline as the notice said it, when
 * it went out and, if the reminder is late or done today, a label. A dot marks
 * a notice not yet seen. The whole row leads to the card or to *Reminders*: a
 * new notice does so with one POST to `readHref`, which marks it seen and
 * sends the person on; a seen one is a plain link.
 *
 * @group rows
 */
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import type { NoticeItem } from '@/lib/reminders';
import { sentAtText } from '@/lib/reminders';
import { rowLink } from '@/lib/rows';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const { notice, readHref } = defineProps<{
    /** The notice, as the server sends it. */
    notice: NoticeItem;
    /** Where a new notice is posted to be marked as seen; the server then redirects to the notice's `href`. */
    readHref?: string;
}>();

/** Whether tapping the row must mark the notice as seen on the way. */
const marks = computed((): boolean => !notice.read && readHref !== undefined);

/** The classes of the icon tile: the area while new, grey once seen. */
const tile = computed(() =>
    cn(
        'flex size-10 shrink-0 items-center justify-center rounded-lg',
        notice.read
            ? 'bg-canvas text-ink-soft'
            : areaTile[areaOf(notice.area) ?? 'general'],
    ),
);
</script>

<template>
    <Link
        :href="marks ? readHref! : notice.href"
        :method="marks ? 'post' : 'get'"
        :as="marks ? 'button' : 'a'"
        :class="
            cn(
                'relative flex w-full items-center gap-3 px-4 py-3.5 text-left text-inherit no-underline',
                marks &&
                    'cursor-pointer border-0 bg-transparent font-[inherit]',
                rowLink,
            )
        "
    >
        <span :class="tile"
            ><FmIcon :icon="areaIcon(notice.area ?? 'general')" :size="20"
        /></span>
        <span class="flex min-w-0 grow flex-col gap-0.5">
            <span class="truncate text-base font-semibold">{{
                notice.title
            }}</span>
            <span class="truncate text-[13px] text-ink-soft">{{
                notice.due_text
            }}</span>
            <span class="flex items-center gap-2 text-xs text-ink-soft">
                {{ sentAtText(notice.sent_at) }}
                <span
                    v-if="notice.state === 'overdue'"
                    class="font-semibold text-overdue"
                    >{{ $t('Overdue') }}</span
                >
                <span
                    v-else-if="notice.state === 'done'"
                    class="inline-flex items-center gap-0.5 font-semibold text-paid"
                    ><FmIcon :icon="Check" :size="12" :stroke="3" />{{
                        $t('Done')
                    }}</span
                >
            </span>
        </span>
        <span
            v-if="!notice.read"
            :aria-label="$t('New notice')"
            role="img"
            class="size-2.5 shrink-0 rounded-full bg-action"
        />
    </Link>
</template>
