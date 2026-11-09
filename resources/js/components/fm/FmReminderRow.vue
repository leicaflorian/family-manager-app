<script setup lang="ts">
/**
 * A reminder in the list of *Reminders*, as the boards *Promemoria* and
 * *Promemoria-pc* draw it: the icon of the area, what to do (a link), below
 * the thing it refers to (or *Written by hand* for a reminder about nothing)
 * and «From the deadline of 20/10» when it was postponed, who takes care of it
 * (*Everyone* without a person), the day with its weekday («Wed 04/11», the
 * whole date in red once late) and how far it is, the next notice, and the
 * actions: *Postpone* on a late reminder, the ⋯ menu on the others (postpone,
 * *Edit*, *Open the card*, *Delete*), and the switch *Done*. A reminder that
 * is done has *Reopen* instead. On a wide list the parts are columns; on a
 * narrow one (the phone, or the list beside the form of a reminder) the day
 * goes on the right of the title and the notice and the actions below.
 *
 * @group rows
 */
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    BellOff,
    CalendarDays,
    Clock,
    FileText,
    PenLine,
    Pencil,
    Trash2,
    TriangleAlert,
    UsersRound,
} from '@lucide/vue';
import { createReusableTemplate } from '@vueuse/core';
import { DropdownMenuLabel, DropdownMenuSeparator } from 'reka-ui';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { shortDate, weekdayShort } from '@/lib/budget';
import { daysFromToday, relativeDays, todayInRome } from '@/lib/format';
import type { PostponeChoice, ReminderItem } from '@/lib/reminders';
import { postponeChoices } from '@/lib/reminders';
import { cn } from '@/lib/utils';
import FmAvatar from './FmAvatar.vue';
import FmIcon from './FmIcon.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

const { reminder, pending = false } = defineProps<{
    /** The reminder, as the list shows it. */
    reminder: ReminderItem;
    /** The server is marking it done: the switch stays on meanwhile. */
    pending?: boolean;
}>();

const emit = defineEmits<{
    /** The switch *Done* was turned on. */
    done: [];
    /** *Reopen* was tapped, on a reminder that is done. */
    reopen: [];
    /** A quick choice of *Postpone*: the new day, `yyyy-mm-dd`. */
    postpone: [to: string];
    /** *Choose a date…* of *Postpone*: the page opens the panel of the day. */
    'choose-date': [];
    /** *Delete* of the ⋯ menu: the page asks before deleting. */
    delete: [];
}>();

const { date } = useFormat();

const [DefinePostpone, ReusePostpone] = createReusableTemplate();

/** It is still open and its day is passed. */
const late = computed(
    () => !reminder.done && daysFromToday(reminder.due_date) < 0,
);

/** The quick choices of *Postpone*, from today. */
const choices = computed((): PostponeChoice[] =>
    postponeChoices(todayInRome()),
);

/** The classes of the icon tile, the colour of the area. */
const tile = computed(() =>
    cn(
        'flex size-8 shrink-0 items-center justify-center rounded-md @4xl/reminder:size-9',
        areaTile[areaOf(reminder.area) ?? 'general'],
    ),
);

/** The classes of a grey label, as *Written by hand*. */
const label = cn(
    'inline-flex h-6 shrink-0 items-center gap-[5px] rounded-lg bg-divider px-2 text-caption whitespace-nowrap text-ink-soft',
);

/**
 * Sends *done*, and puts the switch back as `pending` says: it stays on only
 * while the server works, and goes off again if it refuses.
 *
 * @param event - the change of the switch
 */
function toggleDone(event: Event): void {
    emit('done');
    (event.target as HTMLInputElement).checked = pending;
}

/** The classes of the day: red once late. */
const dayClass = computed(() =>
    cn(
        'text-label whitespace-nowrap tabular-nums',
        late.value ? 'text-overdue' : 'text-ink',
    ),
);
</script>

<template>
    <DefinePostpone>
        <FmMenuItem
            v-for="choice in choices"
            :key="choice.to"
            @select="emit('postpone', choice.to)"
            >{{ choice.label }}</FmMenuItem
        >
        <FmMenuItem :icon="CalendarDays" @select="emit('choose-date')">{{
            $t('Choose a date…')
        }}</FmMenuItem>
    </DefinePostpone>

    <!-- The row measures its own list, not the screen: next to the form of a
         reminder the list is narrow, and the columns of person, day and notice
         would leave the title a few letters. -->
    <div class="@container/reminder">
        <div
            class="flex flex-wrap items-start gap-x-2.5 gap-y-2.5 px-3.5 py-3 @4xl/reminder:flex-nowrap @4xl/reminder:items-center @4xl/reminder:gap-x-4 @4xl/reminder:px-4 @4xl/reminder:py-2.5"
        >
            <span :class="tile"
                ><FmIcon :icon="areaIcon(reminder.area)" :size="20"
            /></span>

            <span
                class="flex min-w-0 flex-1 flex-col gap-[3px] @4xl/reminder:ml-1 @4xl/reminder:gap-1"
            >
                <Link
                    :href="reminder.href"
                    class="text-row text-inherit no-underline @4xl/reminder:text-body-strong"
                    :class="reminder.done && 'text-ink-soft line-through'"
                    >{{ reminder.title }}</Link
                >
                <span
                    class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1.5 text-meta text-ink-soft @4xl/reminder:text-body-sm"
                >
                    <Link
                        v-if="reminder.subject?.href"
                        :href="reminder.subject.href"
                        class="min-w-0 truncate text-inherit"
                        >{{ reminder.subject.label }}</Link
                    >
                    <span
                        v-else-if="reminder.subject"
                        class="min-w-0 truncate"
                        >{{ reminder.subject.label }}</span
                    >
                    <span v-else-if="reminder.manual" :class="label"
                        ><FmIcon :icon="PenLine" :size="13" />{{
                            $t('Written by hand')
                        }}</span
                    >
                    <!-- On a narrow list the person is a small circle here -->
                    <FmAvatar
                        v-if="reminder.person"
                        class="@4xl/reminder:hidden"
                        :initials="reminder.person.initials"
                        :photo="reminder.person.photo"
                        :size="18"
                        aria-hidden="true"
                    />
                    <span
                        v-else
                        class="flex size-[18px] items-center justify-center rounded-full bg-divider text-ink-soft @4xl/reminder:hidden"
                        aria-hidden="true"
                        ><FmIcon :icon="UsersRound" :size="11"
                    /></span>
                    <span
                        v-if="reminder.postponed_from"
                        class="flex basis-full @4xl/reminder:basis-auto"
                    >
                        <span :class="label"
                            ><FmIcon :icon="CalendarDays" :size="13" />{{
                                $t('From the deadline of :date', {
                                    date: shortDate(reminder.postponed_from),
                                })
                            }}</span
                        >
                    </span>
                    <span
                        v-if="reminder.missing_planned_expense"
                        class="inline-flex items-center gap-1 font-semibold text-warning"
                    >
                        <FmIcon :icon="TriangleAlert" :size="14" />
                        {{ $t('The planned expense is missing') }}
                    </span>
                </span>
            </span>

            <!-- Said to screen readers always, seen only on a wide list -->
            <span
                class="sr-only text-body-sm text-ink-soft @4xl/reminder:not-sr-only @4xl/reminder:flex @4xl/reminder:w-[104px] @4xl/reminder:shrink-0 @4xl/reminder:items-center @4xl/reminder:gap-2"
            >
                <template v-if="reminder.person">
                    <FmAvatar
                        :initials="reminder.person.initials"
                        :photo="reminder.person.photo"
                        :size="26"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ reminder.person.name }}</span>
                </template>
                <template v-else>
                    <span
                        class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-divider"
                        :title="$t('Whole family')"
                        ><FmIcon :icon="UsersRound" :size="15"
                    /></span>
                    {{ $t('Everyone') }}
                </template>
            </span>

            <span
                class="flex shrink-0 flex-col items-end gap-0.5 @4xl/reminder:w-[120px] @4xl/reminder:items-start"
            >
                <template v-if="late">
                    <span :class="cn(dayClass, '@4xl/reminder:hidden')">{{
                        shortDate(reminder.due_date)
                    }}</span>
                    <span :class="cn(dayClass, 'hidden @4xl/reminder:block')">{{
                        date(reminder.due_date)
                    }}</span>
                </template>
                <span v-else-if="reminder.done" :class="dayClass">{{
                    date(reminder.due_date)
                }}</span>
                <span v-else :class="dayClass">{{
                    `${weekdayShort(reminder.due_date)} ${shortDate(reminder.due_date)}`
                }}</span>
                <span
                    v-if="!reminder.done"
                    class="text-meta whitespace-nowrap"
                    :class="
                        late ? 'text-meta-strong text-overdue' : 'text-ink-soft'
                    "
                    >{{ relativeDays(reminder.due_date) }}</span
                >
            </span>

            <span
                class="flex basis-full items-center justify-between gap-2 pl-[42px] @4xl/reminder:contents"
            >
                <span
                    class="flex @4xl/reminder:w-[150px] @4xl/reminder:shrink-0"
                >
                    <template v-if="!reminder.done">
                        <span
                            v-if="reminder.next_notice_on"
                            class="inline-flex h-6 items-center gap-[5px] rounded-lg bg-action-soft px-2 text-caption whitespace-nowrap text-action-dark"
                            ><FmIcon :icon="Bell" :size="13" />{{
                                $t('Notice on :date', {
                                    date: shortDate(reminder.next_notice_on),
                                })
                            }}</span
                        >
                        <span
                            v-else
                            class="flex items-center gap-[5px] text-caption text-ink-soft @4xl/reminder:gap-1.5 @4xl/reminder:text-meta"
                            ><FmIcon :icon="BellOff" :size="14" />{{
                                $t('No other notice')
                            }}</span
                        >
                    </template>
                </span>

                <span
                    class="flex items-center justify-end gap-2 @4xl/reminder:w-[196px] @4xl/reminder:shrink-0"
                >
                    <button
                        v-if="reminder.done"
                        type="button"
                        class="min-h-11 cursor-pointer border-0 bg-transparent p-0 text-label text-action-dark"
                        @click="emit('reopen')"
                    >
                        {{ $t('Reopen') }}
                    </button>
                    <template v-else>
                        <FmMenu
                            v-if="late"
                            outline
                            :icon="Clock"
                            :label="$t('Postpone')"
                        >
                            <ReusePostpone />
                        </FmMenu>
                        <FmMenu
                            v-else
                            plain
                            small
                            :label="
                                $t('More actions on :title', {
                                    title: reminder.title,
                                })
                            "
                        >
                            <DropdownMenuLabel
                                class="px-3 pt-1.5 pb-1 text-meta-strong text-ink-soft"
                                >{{ $t('Postpone') }}</DropdownMenuLabel
                            >
                            <ReusePostpone />
                            <template
                                v-if="
                                    reminder.edit_href ||
                                    reminder.card_href ||
                                    reminder.can_delete
                                "
                            >
                                <DropdownMenuSeparator
                                    class="mx-1.5 my-1 h-px bg-divider"
                                />
                                <FmMenuItem
                                    v-if="reminder.edit_href"
                                    :icon="Pencil"
                                    :href="reminder.edit_href"
                                    >{{ $t('Edit') }}</FmMenuItem
                                >
                                <FmMenuItem
                                    v-if="reminder.card_href"
                                    :icon="FileText"
                                    :href="reminder.card_href"
                                    >{{ $t('Open the card') }}</FmMenuItem
                                >
                                <FmMenuItem
                                    v-if="reminder.can_delete"
                                    :icon="Trash2"
                                    danger
                                    @select="emit('delete')"
                                    >{{ $t('Delete') }}</FmMenuItem
                                >
                            </template>
                        </FmMenu>
                        <label
                            class="relative flex min-h-11 shrink-0 cursor-pointer items-center gap-2 text-body-sm text-ink-soft"
                        >
                            <input
                                type="checkbox"
                                role="switch"
                                class="peer absolute size-px opacity-0"
                                :aria-label="
                                    $t('Done: :title', {
                                        title: reminder.title,
                                    })
                                "
                                :checked="pending"
                                :disabled="pending"
                                @change="toggleDone"
                            />
                            <span
                                aria-hidden="true"
                                class="flex h-6 w-10 shrink-0 items-center rounded-lg bg-edge p-0.5 transition-colors peer-checked:justify-end peer-checked:bg-paid peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-action"
                            >
                                <span
                                    class="size-5 rounded-full bg-card shadow-raised"
                                />
                            </span>
                            {{ $t('Done') }}
                        </label>
                    </template>
                </span>
            </span>
        </div>
    </div>
</template>
