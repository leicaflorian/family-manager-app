<script setup lang="ts">
/**
 * A reminder in the list of *Reminders*: the circle *Mark as done*, the icon
 * of the area, what to do, the thing it refers to (a link), who takes care of
 * it, the day with how far it is (red if late) and the next notice. A little
 * hand marks a reminder written by hand, and a label says when the planned
 * expense of the card is missing. A reminder that is done has *Reopen* instead
 * of the circle.
 *
 * @group rows
 */
import { Link } from '@inertiajs/vue3';
import { Check, PenLine, TriangleAlert } from '@lucide/vue';
import { transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { daysFromToday, relativeDays } from '@/lib/format';
import type { ReminderItem } from '@/lib/reminders';
import { nextNoticeText } from '@/lib/reminders';
import { cn } from '@/lib/utils';
import FmAvatar from './FmAvatar.vue';
import FmIcon from './FmIcon.vue';

const { reminder } = defineProps<{
    /** The reminder, as the server sends it. */
    reminder: ReminderItem;
}>();

const emit = defineEmits<{
    /** The circle was tapped: the reminder is done. */
    done: [];
    /** The title was tapped: the card or the form opens. */
    open: [];
    /** *Reopen* was tapped, on a reminder that is done. */
    reopen: [];
}>();

const { date } = useFormat();

/** The days from today, negative when the day is passed. */
const days = computed(() => daysFromToday(reminder.due_date));

/** It is still open and its day is passed. */
const late = computed(() => !reminder.done && days.value < 0);

/** How far the day is: «in 5 days», or «overdue by 26 days» in red. */
const distance = computed((): string =>
    late.value
        ? transChoice(
              '{1} overdue by :count day|[2,*] overdue by :count days',
              -days.value,
              { count: String(-days.value) },
          )
        : relativeDays(reminder.due_date),
);

/** The classes of the icon tile, the colour of the area. */
const tile = computed(() =>
    cn(
        'flex size-9 shrink-0 items-center justify-center rounded-[10px]',
        areaTile[areaOf(reminder.area) ?? 'general'],
    ),
);
</script>

<template>
    <!-- The row measures its own list, not the screen: next to the panel of a
         reminder the list is narrow, and the columns of person, date and notice
         would leave the title a few letters. -->
    <div class="@container/reminder">
        <div
            class="grid grid-cols-[28px_36px_minmax(0,1fr)] items-center gap-x-3.5 gap-y-1.5 px-4.5 py-3 @4xl/reminder:grid-cols-[28px_36px_minmax(0,1fr)_110px_150px_170px]"
        >
            <button
                v-if="!reminder.done"
                type="button"
                :aria-label="$t('Mark as done')"
                class="-m-2.5 flex size-11 cursor-pointer items-center justify-center border-0 bg-transparent"
                @click="emit('done')"
            >
                <span
                    class="size-[22px] rounded-full border-2"
                    :class="late ? 'border-overdue' : 'border-[#9AA6B6]'"
                />
            </button>
            <span
                v-else
                class="flex size-[22px] items-center justify-center rounded-full bg-paid text-white"
            >
                <FmIcon :icon="Check" :size="14" :stroke="3" />
            </span>
            <span :class="tile"
                ><FmIcon
                    :icon="areaIcon(reminder.area ?? 'general')"
                    :size="20"
            /></span>
            <span class="flex min-w-0 flex-col gap-[3px]">
                <Link
                    :href="reminder.href"
                    class="truncate text-base font-semibold text-inherit no-underline"
                    :class="reminder.done && 'text-ink-soft line-through'"
                    @click="emit('open')"
                    >{{ reminder.title }}</Link
                >
                <span
                    class="flex min-w-0 flex-wrap items-center gap-x-1.5 text-[13px] text-ink-soft"
                >
                    <Link
                        v-if="reminder.subject"
                        :href="reminder.subject.href"
                        class="truncate text-inherit"
                        >{{ reminder.subject.label }}</Link
                    >
                    <FmIcon
                        v-if="reminder.manual"
                        :icon="PenLine"
                        :size="14"
                        :label="$t('Written by hand')"
                    />
                    <span
                        v-if="reminder.missing_planned_expense"
                        class="inline-flex items-center gap-1 font-semibold text-warning"
                    >
                        <FmIcon :icon="TriangleAlert" :size="14" />
                        {{ $t('The planned expense is missing') }}
                    </span>
                </span>
            </span>
            <span
                class="col-start-3 flex flex-wrap items-center gap-x-4 gap-y-1 @4xl/reminder:contents"
            >
                <span
                    v-if="reminder.person"
                    class="flex items-center gap-2 text-sm"
                >
                    <FmAvatar
                        :initials="reminder.person.initials"
                        :photo="reminder.person.photo"
                        :size="26"
                        aria-hidden="true"
                    />
                    {{ reminder.person.name }}
                </span>
                <span v-else class="hidden @4xl/reminder:block" />
                <span class="flex flex-col gap-0.5 text-sm">
                    <span
                        class="font-semibold"
                        :class="late && 'text-overdue'"
                        >{{ date(reminder.due_date) }}</span
                    >
                    <span
                        v-if="!reminder.done"
                        class="text-[13px]"
                        :class="
                            late
                                ? 'font-semibold text-overdue'
                                : 'text-ink-soft'
                        "
                        >{{ distance }}</span
                    >
                </span>
                <button
                    v-if="reminder.done"
                    type="button"
                    class="cursor-pointer justify-self-start border-0 bg-transparent p-0 text-sm font-semibold text-action-dark"
                    @click="emit('reopen')"
                >
                    {{ $t('Reopen') }}
                </button>
                <span v-else class="text-[13px] text-ink-soft">{{
                    nextNoticeText(
                        reminder.next_notice_on,
                        reminder.notices_sent,
                    )
                }}</span>
            </span>
        </div>
    </div>
</template>
