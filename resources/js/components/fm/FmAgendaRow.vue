<script setup lang="ts">
/**
 * An entry of the Agenda, in two layouts. In the Agenda (`agenda`): the day
 * with its weekday, the title, below the card and the person, on the right the
 * amount, how far it is and *Reminder done*. In the Home (`home`): the dot of
 * the area, the title, below the card with how far it is («Gas - Enel · in 4
 * days»), or «Late by 3 days · 23/10/2026» in red, and the amount, with «~» if
 * it is an estimate. The whole row leads to the entry.
 *
 * @group rows
 */
import { Link } from '@inertiajs/vue3';
import { transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import type { Area } from '@/lib/areas';
import { areaFill } from '@/lib/areas';
import { shortMonth, weekdayShort } from '@/lib/budget';
import { daysFromToday, relativeDays, toDate } from '@/lib/format';
import { rowLink } from '@/lib/rows';
import { cn } from '@/lib/utils';
import FmAmount from './FmAmount.vue';
import FmAvatar from './FmAvatar.vue';
import FmRow from './FmRow.vue';

/** Where the row is shown. */
export type AgendaRowLayout = 'agenda' | 'home';

const { entry, layout = 'agenda' } = defineProps<{
    /** The entry, as the server sends it. */
    entry: App.Data.AgendaEntryData;
    /** The Agenda (the day on the left, the person) or the Home (the dot and the line below). */
    layout?: AgendaRowLayout;
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

/** The day of the month, as the number on the left. */
const day = computed(() => toDate(entry.date).getDate());

/** Under the day: its weekday, or the month for what is already passed. */
const dayLabel = computed((): string =>
    late.value
        ? shortMonth(entry.date.slice(0, 7)).toLowerCase()
        : weekdayShort(entry.date),
);

/** The colour of the day and of how far it is. */
const tone = computed(() =>
    late.value ? cn('text-overdue') : cn('text-ink-soft'),
);

/** The first letter of the first name, for the circle of the person. */
const initial = computed(() =>
    Array.from(entry.person ?? '')
        .slice(0, 1)
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <FmRow
        v-if="layout === 'home'"
        :title="entry.title"
        :subtitle="homeSubtitle"
        :href="entry.href"
        :area="entry.area as Area"
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
    <Link
        v-else
        :href="entry.href"
        :class="
            cn(
                'relative flex w-full items-center gap-2.5 py-3 pr-3.5 pl-2 text-inherit no-underline',
                rowLink,
            )
        "
    >
        <span
            class="flex w-10 shrink-0 flex-col items-center leading-[1.1]"
            :class="tone"
        >
            <span
                class="text-xl font-extrabold"
                :class="late ? undefined : 'text-ink'"
                >{{ day }}</span
            >
            <span class="text-xs font-semibold">{{ dayLabel }}</span>
        </span>
        <span class="flex min-w-0 grow flex-col gap-[3px]">
            <span class="truncate text-[15px] font-semibold">{{
                entry.title
            }}</span>
            <span
                class="flex min-w-0 items-center gap-1.5 text-[13px] text-ink-soft"
            >
                <span
                    data-dot
                    class="size-2.5 shrink-0 rounded-full"
                    :class="areaFill[entry.area as Area]"
                />
                <span v-if="entry.subtitle" class="truncate">{{
                    entry.subtitle
                }}</span>
                <FmAvatar
                    v-if="entry.person"
                    :initials="initial"
                    :name="entry.person"
                    :size="18"
                    :title="entry.person"
                />
                <span v-if="entry.person" class="hidden shrink-0 lg:inline">{{
                    entry.person
                }}</span>
            </span>
        </span>
        <span class="flex shrink-0 flex-col items-end gap-[3px]">
            <span
                v-if="entry.amount !== null"
                class="text-[15px] font-bold whitespace-nowrap"
            >
                <FmAmount
                    :amount="entry.amount"
                    :estimated="entry.estimated"
                    :income="entry.kind === 'income'"
                />
            </span>
            <span
                class="text-xs whitespace-nowrap"
                :class="late ? 'font-semibold text-overdue' : 'text-ink-soft'"
                >{{ relativeDays(entry.date) }}</span
            >
            <span
                v-if="entry.reminder_done"
                class="text-xs font-semibold text-paid"
                >{{ $t('Reminder done') }}</span
            >
        </span>
    </Link>
</template>
