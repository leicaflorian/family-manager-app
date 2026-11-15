<script setup lang="ts">
/**
 * The month of the Agenda as a grid of weeks, from Monday, with the weekdays
 * above. On the phone each day is a number with up to three dots (the colour
 * of the area, red if late); the chosen day is a full blue circle and today
 * has a blue outline. On the computer (`wide`) each day is a tall cell with up
 * to three labels in the colour of the area (red if late, grey, struck through
 * and ticked if done) and «+3 more» for the rest, with a blue border on the
 * chosen day and the month written on the first day of it. A day is always a
 * button that emits `select`; the days of the other months are faint. The
 * month sits in a white card; on the computer a line divides the weekdays from
 * the days, and the month is written as the language writes it («1 dic»,
 * «1 Dec»).
 *
 * @group navigation
 */
import { format } from '@formkit/tempo';
import { Check } from '@lucide/vue';
import { getActiveLanguage, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { areaFill, areaOf, areaTile } from '@/lib/areas';
import { monthName, weekdayShort } from '@/lib/budget';
import { addDays } from '@/lib/days';
import { toDate } from '@/lib/format';
import type { MonthDay, MonthItem } from '@/lib/monthGrid';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const {
    month,
    today,
    selected,
    days,
    wide = false,
} = defineProps<{
    /** The month shown, `yyyy-mm`. */
    month: string;
    /** Today, `yyyy-mm-dd`. */
    today: string;
    /** The chosen day, `yyyy-mm-dd`. */
    selected: string;
    /** What each day holds; a day not listed is empty. */
    days: MonthDay[];
    /** The computer: tall cells with titles instead of dots. */
    wide?: boolean;
}>();

const emit = defineEmits<{
    /** The person tapped a day: `yyyy-mm-dd`. */
    select: [date: string];
}>();

/** The most things a cell writes (computer) or dots a day draws (phone). */
const VISIBLE = 3;

/** The things of each day, by date. */
const itemsByDate = computed(
    (): Map<string, MonthItem[]> =>
        new Map(days.map((day) => [day.date, day.items])),
);

/** The six weeks of the grid: from the Monday of the week of the 1st, 42 days. */
const dates = computed((): string[] => {
    const first = `${month}-01`;
    const weekday = (new Date(`${first}T00:00:00Z`).getUTCDay() + 6) % 7;
    const start = addDays(first, -weekday);

    return Array.from({ length: 42 }, (_, index) => addDays(start, index));
});

/** The short weekday names, Monday to Sunday, in the language of the page. */
const weekdays = computed((): string[] =>
    dates.value.slice(0, 7).map((date) => weekdayShort(date)),
);

/** The name of the month, for the grid. */
const label = computed((): string => monthName(month));

/**
 * The things of a day.
 *
 * @param date - `yyyy-mm-dd`
 * @returns its things, none if the day is not listed
 */
function itemsOf(date: string): MonthItem[] {
    return itemsByDate.value.get(date) ?? [];
}

/**
 * The day of the month as a number.
 *
 * @param date - `yyyy-mm-dd`
 * @returns the number, without a leading zero
 */
function dayNumber(date: string): number {
    return Number(date.slice(8, 10));
}

/**
 * The day as it is read aloud and as the first of a month is written, with the
 * short month as the language writes it: «4 nov» in Italian, «4 Nov» in English.
 *
 * @param date - `yyyy-mm-dd`
 * @returns the day and the short month
 */
function dayAndMonth(date: string): string {
    return format(toDate(date), 'D MMM', getActiveLanguage());
}

/**
 * The name of a day button: the day and how many things it has.
 *
 * @param date - `yyyy-mm-dd`
 * @returns for example «4 Nov, 3 things»
 */
function ariaLabel(date: string): string {
    const count = itemsOf(date).length;

    return transChoice(
        '{0} :date|{1} :date, 1 thing|[2,*] :date, :count things',
        count,
        { date: dayAndMonth(date) },
    );
}

/**
 * Whether a day belongs to the month shown.
 *
 * @param date - `yyyy-mm-dd`
 * @returns true inside the month
 */
function inMonth(date: string): boolean {
    return date.startsWith(month);
}

/**
 * The colour of the dot of a thing on the phone.
 *
 * @param thing - the thing
 * @returns red if late, grey if done, else the colour of its area
 */
function dotClass(thing: MonthItem): string {
    return thing.late
        ? cn('bg-overdue')
        : thing.done
          ? cn('bg-ink-faint')
          : areaFill[areaOf(thing.area) ?? 'general'];
}

/**
 * The colours of the label of a thing on the computer.
 *
 * @param thing - the thing
 * @returns red if late, grey if done, else the soft colour of its area
 */
function labelClass(thing: MonthItem): string {
    return thing.late
        ? cn('bg-overdue-soft text-overdue')
        : thing.done
          ? cn('bg-card-sunken text-ink-soft')
          : areaTile[areaOf(thing.area) ?? 'general'];
}

/**
 * The classes of the number of a day on the phone.
 *
 * @param date - `yyyy-mm-dd`
 * @returns the circle: full blue if chosen, outlined if today
 */
function numberClass(date: string): string {
    return date === selected
        ? cn('bg-action text-label text-on-action')
        : date === today
          ? cn('text-label text-action ring-[1.5px] ring-action ring-inset')
          : cn('text-body-sm', inMonth(date) ? 'text-ink' : 'text-ink-faint');
}

/**
 * The classes of a day cell on the computer.
 *
 * @param date - `yyyy-mm-dd`
 * @returns the cell: sunken outside the month, blue border if chosen
 */
function cellClass(date: string): string {
    return cn(
        'flex min-h-32 min-w-0 cursor-pointer flex-col items-stretch gap-1 border-0 border-r border-b border-solid border-divider p-1.5 text-left text-inherit',
        inMonth(date) ? 'bg-card' : 'bg-card-sunken',
        date === selected && 'ring-2 ring-action ring-inset',
    );
}

/**
 * The classes of the number of a day on the computer.
 *
 * @param date - `yyyy-mm-dd`
 * @returns today is a blue pill, the other months are faint
 */
function cellNumberClass(date: string): string {
    return cn(
        'inline-flex h-[26px] items-center self-start px-1 text-meta-strong',
        date === today
            ? 'min-w-[26px] justify-center rounded-full bg-action px-1.5 text-on-action'
            : inMonth(date)
              ? 'text-ink'
              : 'text-ink-faint',
    );
}
</script>

<template>
    <div
        :class="
            wide
                ? 'flex flex-col overflow-hidden rounded-card bg-card shadow-card'
                : 'flex flex-col gap-1 rounded-card bg-card px-2 pt-2.5 pb-1.5 shadow-card'
        "
    >
        <div
            class="grid grid-cols-7 text-meta text-ink-soft"
            :class="wide ? 'border-b border-divider py-2.5' : 'pb-0.5'"
            aria-hidden="true"
        >
            <span
                v-for="name in weekdays"
                :key="name"
                data-testid="weekday"
                :class="wide ? 'pl-2.5 text-left' : 'text-center'"
                >{{ name }}</span
            >
        </div>
        <div role="grid" :aria-label="label" class="grid grid-cols-7">
            <button
                v-for="date in dates"
                :key="date"
                type="button"
                :aria-label="ariaLabel(date)"
                :aria-pressed="date === selected"
                :aria-current="date === today ? 'date' : undefined"
                :class="
                    wide
                        ? cellClass(date)
                        : 'flex h-[50px] cursor-pointer flex-col items-center gap-[3px] border-0 bg-transparent pt-0.5 text-inherit'
                "
                @click="emit('select', date)"
            >
                <template v-if="wide">
                    <span :class="cellNumberClass(date)">{{
                        dayNumber(date) === 1
                            ? dayAndMonth(date)
                            : dayNumber(date)
                    }}</span>
                    <span
                        v-for="thing in itemsOf(date).slice(0, VISIBLE)"
                        :key="thing.key"
                        :class="
                            cn(
                                'flex h-6 min-w-0 items-center gap-1 rounded-md px-1.5 text-meta',
                                labelClass(thing),
                            )
                        "
                    >
                        <FmIcon
                            v-if="thing.done"
                            :icon="Check"
                            :size="12"
                            class="shrink-0"
                        />
                        <span
                            :class="
                                cn(
                                    'min-w-0 grow truncate',
                                    thing.done && 'line-through',
                                )
                            "
                            >{{ thing.title }}</span
                        >
                    </span>
                    <span
                        v-if="itemsOf(date).length > VISIBLE"
                        class="px-1.5 text-meta text-ink-soft"
                        >{{
                            $tChoice(
                                '{1} +:count more|[2,*] +:count more',
                                itemsOf(date).length - VISIBLE,
                            )
                        }}</span
                    >
                </template>
                <template v-else>
                    <span
                        :class="
                            cn(
                                'flex size-[34px] items-center justify-center rounded-full',
                                numberClass(date),
                            )
                        "
                        >{{ dayNumber(date) }}</span
                    >
                    <span class="flex h-1.5 gap-[3px]">
                        <span
                            v-for="thing in itemsOf(date).slice(0, VISIBLE)"
                            :key="thing.key"
                            data-dot
                            :class="
                                cn('size-1.5 rounded-full', dotClass(thing))
                            "
                        ></span>
                    </span>
                </template>
            </button>
        </div>
    </div>
</template>
