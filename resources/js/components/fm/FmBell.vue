<script setup lang="ts">
/**
 * The bell in the header of the Home. When there are notices not yet seen it
 * shows how many in a red dot (up to 99). A tap opens the menu of the notices
 * under it, on the computer and on the phone, as the board *Avvisi-pc* draws
 * it: the title *Notices* with *Mark all as read* while something is new,
 * then the last notices (`FmNoticeList`, at most 10, the server chooses them)
 * and *See all the notices*, which leads to the page with the search. The notices are not in the page until the
 * menu asks for them: opening it emits `open`, and until `notices` arrives the
 * menu says it is loading.
 *
 * @group navigation
 */
import { router } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed } from 'vue';
import { notices as noticesPage } from '@/routes';
import { readAll } from '@/routes/notices';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmNoticeList from './FmNoticeList.vue';
import FmSectionLink from './FmSectionLink.vue';

/** The notices of the menu, as the server sends them. */
export type BellNotices = {
    /** The notices not seen yet, the most recent first. */
    unread: App.Data.ReminderNoticeData[];
    /** The notices already seen, the most recent first. */
    seen: App.Data.ReminderNoticeData[];
};

const { count, notices } = defineProps<{
    /** How many notices the person has not seen yet. */
    count: number;
    /** The notices of the menu; missing until the page loads them. */
    notices?: BellNotices;
}>();

const emit = defineEmits<{
    /** The menu opened: the page loads the notices. */
    open: [];
}>();

/** What the bell says to those who cannot see the dot. */
const label = computed((): string =>
    count > 0
        ? transChoice(
              '{1} Notices: :count new|[2,*] Notices: :count new',
              count,
              {
                  count: String(count),
              },
          )
        : trans('Notices'),
);

/**
 * Asks for the notices when the menu opens.
 *
 * @param open - whether the menu is now open
 */
function toggled(open: boolean): void {
    if (open) {
        emit('open');
    }
}

/** Marks every notice as read, staying on the page with the menu open. */
function markAll(): void {
    router.post(
        readAll.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notices', 'unread_notices'],
        },
    );
}
</script>

<template>
    <PopoverRoot @update:open="toggled">
        <PopoverTrigger
            :aria-label="label"
            class="relative flex size-11 cursor-pointer items-center justify-center border-0 text-inherit focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
        >
            <FmIcon :icon="Bell" :size="24" />
            <span
                v-if="count > 0"
                aria-hidden="true"
                class="absolute top-1 right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-overdue px-1 text-[11px] font-bold text-on-action"
                >{{ count > 99 ? '99+' : count }}</span
            >
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                align="end"
                :side-offset="8"
                :collision-padding="16"
                class="z-30 flex max-h-[min(720px,var(--reka-popover-content-available-height))] w-[min(380px,calc(100vw-32px))] flex-col gap-4 overflow-y-auto rounded-[18px] border border-edge bg-canvas p-5 text-ink shadow-menu duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class="m-0 text-[22px] font-extrabold">
                        {{ $t('Notices') }}
                    </h2>
                    <FmSectionLink
                        v-if="notices && notices.unread.length > 0"
                        @click="markAll"
                        >{{ $t('Mark all as read') }}</FmSectionLink
                    >
                </div>
                <FmNoticeList
                    v-if="notices"
                    :unread="notices.unread"
                    :seen="notices.seen"
                />
                <p v-else class="m-0 py-6 text-center text-sm text-ink-soft">
                    {{ $t('Loading…') }}
                </p>
                <FmButton variant="outline" wide :href="noticesPage().url">{{
                    $t('See all the notices')
                }}</FmButton>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
