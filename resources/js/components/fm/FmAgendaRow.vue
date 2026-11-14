<script setup lang="ts">
/**
 * An entry of the Agenda, in two layouts. In the Agenda (`agenda`, the
 * boards *Agenda* and *Agenda-pc*): the day with its weekday (the month, in
 * red, once passed), the icon of the area, the thing in bold with a small bell
 * beside it when its reminder has notices (*Notice set*), below what happens
 * to it, then the person, how far it is («tomorrow» highlighted) and the amount
 * (only a payment has one). The whole row is a button that emits `open`, to
 * show the panel of the entry. With `done` (the Done list) the day on the left
 * is the one it was closed on, the line below says «Done on 05/11 · Laura»
 * with a green tick, and there is no amount nor how far it is; with `reopen`
 * (the computer) a *Reopen* button sits beside the row. In the Home
 * (`home`): the dot of the area, the title, below the card with how far it is
 * («Gas - Enel · in 4 days»), or «Late by 3 days · 23/10/2026» in red, and the
 * amount, with «~» if it is an estimate. There the whole row is a link to the
 * entry.
 *
 * @group rows
 */
import { Bell, Check, ChevronRight, RotateCcw } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import type { Area } from '@/lib/areas';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { shortDate, shortMonth, weekdayShort } from '@/lib/budget';
import { daysFromToday, relativeDays, toDate } from '@/lib/format';
import { reopenReminder } from '@/lib/agenda';
import { rowLink } from '@/lib/rows';
import { cn } from '@/lib/utils';
import FmAmount from './FmAmount.vue';
import FmAvatar from './FmAvatar.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

/** Where the row is shown. */
export type AgendaRowLayout = 'agenda' | 'home';

const {
    entry,
    layout = 'agenda',
    personAvatar,
    done = false,
    reopen = false,
} = defineProps<{
    /** The entry, as the server sends it. */
    entry: App.Data.AgendaEntryData;
    /** The Agenda (the day on the left, the person) or the Home (the dot and the line below). */
    layout?: AgendaRowLayout;
    /** The initials and photo of the person of the entry, from the people of the page; without it the first letter of the name shows. */
    personAvatar?: Pick<App.Data.PersonData, 'initials' | 'photo'>;
    /** Only in the Agenda: the row of the Done list, with who closed it and when instead of how far it is. */
    done?: boolean;
    /** With `done`, on the computer: a *Reopen* button beside the row, for an entry with a reminder. */
    reopen?: boolean;
}>();

const emit = defineEmits<{
    /** The row was tapped, in the Agenda: the page shows the panel of the entry. */
    open: [];
}>();

const { date } = useFormat();

/** The reminder of a done row that can be reopened from the row itself. */
const reopenable = computed((): App.Data.AgendaReminderData | null =>
    done && reopen ? entry.reminder : null,
);

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

/** The day shown on the left: when it was closed in the Done list, else the day it falls on. */
const shownDate = computed((): string =>
    done ? (entry.reminder?.done_on ?? entry.date) : entry.date,
);

/** The day of the month, as the number on the left. */
const day = computed(() => toDate(shownDate.value).getDate());

/** Whether the notices of its reminder are set, so the bell shows: not for a done entry. */
const hasNotice = computed(
    (): boolean => !done && (entry.reminder?.notice_days.length ?? 0) > 0,
);

/** The line of the Done list: when it was closed and by whom. */
const doneLine = computed((): string =>
    trans('Done on :date · :name', {
        date: shortDate(shownDate.value),
        name: entry.reminder?.done_by ?? '',
    }),
);

/** Under the day: its weekday, or the month for what is already passed. */
const dayLabel = computed((): string =>
    done || late.value
        ? shortMonth(shownDate.value.slice(0, 7)).toLowerCase()
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
    <div v-else class="flex min-w-0 items-center">
        <button
            type="button"
            :class="
                cn(
                    'flex w-full min-w-0 flex-1 items-center gap-2.5 border-0 bg-transparent py-3 pr-3.5 pl-2 text-left text-inherit lg:gap-x-4 lg:px-4',
                    rowLink,
                )
            "
            @click="emit('open')"
        >
            <span class="flex w-9 shrink-0 flex-col items-center lg:w-10">
                <span
                    class="text-sheet-title lg:text-stat"
                    :class="late && !done ? 'text-overdue' : 'text-ink'"
                    >{{ day }}</span
                >
                <span
                    class="text-caption"
                    :class="late && !done ? 'text-overdue' : 'text-ink-soft'"
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
                class="flex min-w-0 grow flex-wrap items-center gap-x-1.5 gap-y-[3px] lg:gap-y-1"
            >
                <span class="min-w-0 truncate text-row lg:text-body-strong">{{
                    entry.name
                }}</span>
                <span
                    v-if="hasNotice"
                    role="img"
                    :aria-label="$t('Notice set')"
                    :title="$t('Notice set')"
                    class="inline-flex shrink-0 text-ink-soft"
                >
                    <FmIcon :icon="Bell" :size="14" />
                </span>
                <span
                    v-if="done"
                    class="flex min-w-0 basis-full items-center gap-1.5 text-meta text-ink-soft lg:text-body-sm"
                >
                    <FmIcon class="text-paid" :icon="Check" :size="14" />
                    <span class="truncate">{{ doneLine }}</span>
                </span>
                <span
                    v-else
                    class="flex min-w-0 basis-full items-center gap-1.5 text-meta text-ink-soft lg:text-body-sm"
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
                <span
                    v-if="!done"
                    class="order-2 flex lg:order-none lg:w-[104px]"
                >
                    <span class="whitespace-nowrap" :class="distanceClass">{{
                        relativeDays(entry.date)
                    }}</span>
                </span>
                <span
                    v-if="!done"
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
                </span>
                <FmIcon
                    v-if="!reopenable"
                    class="hidden text-ink-faint lg:block"
                    :icon="ChevronRight"
                    :size="18"
                />
            </span>
        </button>
        <FmButton
            v-if="reopenable"
            class="mr-4 shrink-0"
            variant="neutral"
            compact
            :icon="RotateCcw"
            :aria-label="$t('Reopen: :name', { name: entry.name })"
            @click="reopenReminder(reopenable.id)"
            >{{ $t('Reopen') }}</FmButton
        >
    </div>
</template>
