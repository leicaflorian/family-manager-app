<script setup lang="ts">
/**
 * An entry of the Agenda, in two layouts. In the Agenda (`agenda`, the
 * boards *Agenda* and *Agenda-pc*): the day with its weekday (the month, in
 * red, once passed), the icon of the area, the thing in bold with the state
 * of its reminder beside it (*Notice on 03/11*, *Reminder done*, *Reminder
 * late*), below what happens to it, then the person, how far it is
 * («tomorrow» highlighted) and the amount («—» for a deadline). With
 * `actions` a passed deadline has *Update the date* and *Renewed*, outside
 * the link, when the person may change its date (`update_url`). In the Home (`home`): the dot of the area, the title, below the
 * card with how far it is («Gas - Enel · in 4 days»), or «Late by 3 days ·
 * 23/10/2026» in red, and the amount, with «~» if it is an estimate. The
 * whole row leads to the entry.
 *
 * @group rows
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { Bell, CalendarDays, Check, ChevronRight } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import type { Area } from '@/lib/areas';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { shortDate, shortMonth, weekdayShort } from '@/lib/budget';
import { daysFromToday, relativeDays, toDate } from '@/lib/format';
import { rowLink } from '@/lib/rows';
import { cn } from '@/lib/utils';
import FmAmount from './FmAmount.vue';
import FmAvatar from './FmAvatar.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

/** Where the row is shown. */
export type AgendaRowLayout = 'agenda' | 'home';

/** The label of the reminder of an entry: its text, icon and colours. */
type ReminderBadge = { text: string; icon: LucideIcon; tone: string };

const {
    entry,
    layout = 'agenda',
    personAvatar,
    actions = false,
} = defineProps<{
    /** The entry, as the server sends it. */
    entry: App.Data.AgendaEntryData;
    /** The Agenda (the day on the left, the person) or the Home (the dot and the line below). */
    layout?: AgendaRowLayout;
    /** The initials and photo of the person of the entry, from the people of the page; without it the first letter of the name shows. */
    personAvatar?: Pick<App.Data.PersonData, 'initials' | 'photo'>;
    /** Only in the Agenda: *Update the date* and *Renewed* under the row, for a passed deadline the person may change. */
    actions?: boolean;
}>();

const emit = defineEmits<{
    /** *Update the date* was tapped. */
    'update-date': [];
    /** *Renewed* was tapped. */
    renewed: [];
}>();

const { date } = useFormat();

/** Whether it is already passed: the deadlines of the cards, and the payments of the Home. */
const late = computed(() => daysFromToday(entry.date) < 0);

/** The line of the Home: the card and how far it is, or how late and since when. */
const homeSubtitle = computed((): string =>
    late.value
        ? [
              transChoice(
                  '{1} Late by :count day|[2,*] Late by :count days',
                  -daysFromToday(entry.date),
                  { count: String(-daysFromToday(entry.date)) },
              ),
              date(entry.date),
          ].join(' · ')
        : [entry.subtitle, relativeDays(entry.date)]
              .filter((part) => part !== '')
              .join(' · '),
);

/** The line under the thing: what happens, with the whole date once passed. */
const detail = computed((): string =>
    [entry.detail, late.value ? date(entry.date) : '']
        .filter((part) => part !== '')
        .join(' · '),
);

/** The day of the month, as the number on the left. */
const day = computed(() => toDate(entry.date).getDate());

/** Under the day: its weekday, or the month for what is already passed. */
const dayLabel = computed((): string =>
    late.value
        ? shortMonth(entry.date.slice(0, 7)).toLowerCase()
        : weekdayShort(entry.date),
);

/** Today and tomorrow are highlighted, as a blue label. */
const soon = computed((): boolean => {
    const days = daysFromToday(entry.date);

    return days === 0 || days === 1;
});

/** The colour of how far it is: a blue label when soon, red once passed. */
const distanceClass = computed((): string =>
    soon.value
        ? cn(
              'inline-flex h-6 items-center rounded-lg bg-action-soft px-2 text-caption text-action-dark',
          )
        : late.value
          ? cn('text-caption text-overdue lg:text-label')
          : cn('text-caption text-ink-soft lg:text-body-sm'),
);

/** The label of the reminder of a deadline, if it says something. */
const badge = computed((): ReminderBadge | null => {
    const reminder = entry.reminder;

    if (reminder === null) {
        return null;
    }

    return reminder.state === 'done'
        ? {
              text: trans('Reminder done'),
              icon: Check,
              tone: cn('bg-paid/12 text-paid'),
          }
        : reminder.state === 'late'
          ? {
                text: trans('Reminder late'),
                icon: Bell,
                tone: cn('bg-overdue/10 text-overdue'),
            }
          : reminder.next_notice !== null
            ? {
                  text: trans('Notice on :date', {
                      date: shortDate(reminder.next_notice),
                  }),
                  icon: Bell,
                  tone: cn('bg-action-soft text-action-dark'),
              }
            : null;
});

/** The first letter of the first name, for the circle of the person. */
const initial = computed(() =>
    Array.from(entry.person ?? '')
        .slice(0, 1)
        .join('')
        .toUpperCase(),
);

/** The area, `general` if the server sends one this app does not know. */
const area = computed((): Area => areaOf(entry.area) ?? 'general');
</script>

<template>
    <FmRow
        v-if="layout === 'home'"
        :title="entry.title"
        :subtitle="homeSubtitle"
        :href="entry.href"
        :area="area"
        :tone="late ? 'overdue' : 'normal'"
        :arrow="false"
    >
        <template v-if="entry.amount !== null" #after>
            <FmAmount
                :amount="entry.amount"
                :estimated="entry.estimated"
                :income="entry.kind === 'income'"
            />
        </template>
    </FmRow>
    <div
        v-else
        class="flex flex-col gap-2.5 lg:flex-row lg:items-center lg:gap-4"
        :class="actions && 'py-3 pr-3.5 pl-2 lg:px-4'"
    >
        <Link
            :href="entry.href"
            :class="
                cn(
                    'flex min-w-0 grow items-center gap-2.5 text-inherit no-underline lg:gap-x-4',
                    !actions && 'py-3 pr-3.5 pl-2 lg:px-4',
                    rowLink,
                )
            "
        >
            <span
                class="flex w-9 shrink-0 flex-col items-center lg:w-10"
            >
                <span
                    class="text-sheet-title lg:text-stat"
                    :class="late ? 'text-overdue' : 'text-ink'"
                    >{{ day }}</span
                >
                <span
                    class="text-caption"
                    :class="late ? 'text-overdue' : 'text-ink-soft'"
                    >{{ dayLabel }}</span
                >
            </span>
            <span
                class="flex size-8 shrink-0 items-center justify-center rounded-md lg:size-9"
                :class="areaTile[area]"
            >
                <FmIcon :icon="areaIcon(area)" :size="20" />
            </span>
            <span
                class="flex min-w-0 grow flex-wrap items-center gap-x-2 gap-y-[3px] lg:gap-y-1"
            >
                <span
                    class="min-w-0 basis-full truncate text-row lg:basis-auto lg:text-body-strong"
                    >{{ entry.name }}</span
                >
                <span
                    class="order-2 flex min-w-0 basis-full items-center gap-1.5 text-meta text-ink-soft lg:order-3 lg:text-body-sm"
                >
                    <span class="truncate">{{ detail }}</span>
                    <FmAvatar
                        v-if="entry.person"
                        class="lg:hidden"
                        :initials="personAvatar?.initials ?? initial"
                        :photo="personAvatar?.photo ?? null"
                        :size="18"
                        aria-hidden="true"
                    />
                </span>
                <span
                    v-if="badge"
                    class="order-3 mt-0.5 inline-flex h-6 shrink-0 items-center gap-[5px] rounded-lg px-2 text-caption whitespace-nowrap lg:order-2 lg:mt-0"
                    :class="badge.tone"
                >
                    <FmIcon :icon="badge.icon" :size="14" />{{ badge.text }}
                </span>
            </span>
            <!-- Said to screen readers always, seen only on the computer -->
            <span
                class="sr-only lg:not-sr-only lg:flex lg:w-[104px] lg:shrink-0 lg:items-center lg:gap-2 lg:text-body-sm lg:text-ink-soft"
            >
                <template v-if="entry.person">
                    <FmAvatar
                        :initials="personAvatar?.initials ?? initial"
                        :photo="personAvatar?.photo ?? null"
                        :size="26"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ entry.person }}</span>
                </template>
            </span>
            <span
                class="flex shrink-0 flex-col items-end gap-1 lg:flex-row lg:items-center lg:gap-4"
            >
                <span class="order-2 flex lg:order-none lg:w-[104px]">
                    <span class="whitespace-nowrap" :class="distanceClass">{{
                        relativeDays(entry.date)
                    }}</span>
                </span>
                <span
                    v-if="!actions"
                    class="order-1 flex justify-end lg:order-none lg:w-[110px]"
                >
                    <span
                        v-if="entry.amount !== null"
                        class="text-row-amount whitespace-nowrap tabular-nums lg:text-amount"
                    >
                        <FmAmount
                            :amount="entry.amount"
                            :estimated="entry.estimated"
                            :income="entry.kind === 'income'"
                        />
                    </span>
                    <span
                        v-else
                        class="hidden text-body-sm text-ink-faint lg:inline"
                        >—</span
                    >
                </span>
                <FmIcon
                    v-if="!actions"
                    class="hidden text-ink-faint lg:block"
                    :icon="ChevronRight"
                    :size="18"
                />
            </span>
        </Link>
        <span
            v-if="actions && entry.update_url !== null"
            class="flex gap-2 pl-[46px] lg:pl-0"
        >
            <FmButton
                variant="outline"
                class="grow lg:grow-0"
                :icon="CalendarDays"
                @click="emit('update-date')"
                >{{ $t('Update the date') }}</FmButton
            >
            <FmButton
                class="grow lg:grow-0"
                :icon="Check"
                @click="emit('renewed')"
                >{{ $t('Renewed') }}</FmButton
            >
        </span>
    </div>
</template>
