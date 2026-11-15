<script setup lang="ts">
/**
 * The calendar view of the *Agenda*, as the boards *Agenda-calendario* and
 * *Agenda-calendario-pc* draw it: the row of the month (its name, *Connect a
 * calendar*, *Today* and the arrows to the month before and after), the grid
 * of the month and the list of the day chosen, with its title («Wednesday 4
 * November»), how many things are to do and done and the sum of the amounts
 * to pay. On the phone the list is under the grid, on the wide layout beside
 * it. The day chosen starts on today when the month holds it, else on the
 * first of the month; it is not in the address. The person and the areas of
 * the filters choose what is shown, as in the list. Another month is a visit
 * that loads only the month.
 *
 * Used only by the page *Agenda*.
 */
import { router } from '@inertiajs/vue3';
import { CalendarSync, ChevronLeft, ChevronRight } from '@lucide/vue';
import { getActiveLanguage, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmAgendaRow from '@/components/fm/FmAgendaRow.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmMonthGrid from '@/components/fm/FmMonthGrid.vue';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import { visibleEntries } from '@/lib/agenda';
import { monthName } from '@/lib/budget';
import { addMonths } from '@/lib/days';
import { formatDayTitle } from '@/lib/format';
import { monthDays } from '@/lib/monthGrid';
import { agenda as agendaRoute } from '@/routes';

const { calendar, filters, personAvatar } = defineProps<{
    /** The month shown, with its open and done entries, as the server sends it. */
    calendar: App.Data.AgendaCalendarData;
    /** The filters in use: the person and the areas choose what is shown. */
    filters: App.Data.AgendaFiltersData;
    /** The circle of the person of an entry, from the people of the page. */
    personAvatar: (
        entry: App.Data.AgendaEntryData,
    ) => Pick<App.Data.PersonData, 'initials' | 'photo'> | undefined;
}>();

const emit = defineEmits<{
    /** A row of the day was tapped: the page shows the panel of the entry. */
    open: [entry: App.Data.AgendaEntryData];
    /** *Connect a calendar* was tapped: the page shows its panel. */
    link: [];
}>();

const wide = useWideLayout();
const { euro } = useFormat();

/**
 * The day chosen when a month arrives: today if the month holds it, else its first day.
 *
 * @returns `yyyy-mm-dd`
 */
function startDay(): string {
    return calendar.today.startsWith(calendar.month)
        ? calendar.today
        : `${calendar.month}-01`;
}

/** The day whose things are listed, `yyyy-mm-dd`. */
const selected = ref(startDay());

watch(
    () => calendar.month,
    () => {
        selected.value = startDay();
    },
);

/** The open entries the person and the areas chosen show. */
const open = computed(() => visibleEntries(calendar.entries, filters));

/** The done entries the person and the areas chosen show. */
const done = computed(() => visibleEntries(calendar.done, filters));

/** What each day of the grid holds. */
const days = computed(() =>
    monthDays(
        calendar.from,
        calendar.to,
        open.value,
        done.value,
        calendar.today,
    ),
);

/** The open entries of the day chosen. */
const dayOpen = computed(() =>
    open.value.filter((entry) => entry.date === selected.value),
);

/** The done entries of the day chosen. */
const dayDone = computed(() =>
    done.value.filter((entry) => entry.date === selected.value),
);

/** The title of the day chosen, «Wednesday 4 November». */
const dayTitle = computed(() =>
    formatDayTitle(selected.value, getActiveLanguage()),
);

/** «3 to do · 1 done · 13,99 €»: what is not there is left out. */
const summary = computed((): string => {
    const toPay = dayOpen.value.reduce(
        (sum, entry) => sum + (entry.amount ?? 0),
        0,
    );

    return [
        dayOpen.value.length > 0
            ? transChoice(
                  '{1} :count to do|[2,*] :count to do',
                  dayOpen.value.length,
              )
            : '',
        dayDone.value.length > 0
            ? transChoice(
                  '{1} :count done|[2,*] :count done',
                  dayDone.value.length,
              )
            : '',
        toPay > 0 ? euro(toPay) : '',
    ]
        .filter((part) => part !== '')
        .join(' · ');
});

/**
 * Loads another month, without the rest of the page; the server remembers the calendar view.
 *
 * @param month - `yyyy-mm`
 */
function goTo(month: string): void {
    router.get(
        agendaRoute({ query: { view: 'calendar', month } }).url,
        {},
        { preserveState: true, preserveScroll: true, only: ['calendar'] },
    );
}

/**
 * Goes to the month before or after the one shown.
 *
 * @param step - -1 for the month before, 1 for the one after
 */
function move(step: number): void {
    goTo(addMonths(`${calendar.month}-01`, step).slice(0, 7));
}

/** *Today*: chooses today, loading its month when another one is shown. */
function goToday(): void {
    if (calendar.today.startsWith(calendar.month)) {
        selected.value = calendar.today;
    } else {
        goTo(calendar.today.slice(0, 7));
    }
}
</script>

<template>
    <div
        :class="
            wide ? 'flex flex-wrap items-start gap-5' : 'flex flex-col gap-3.5'
        "
    >
        <div
            class="flex min-w-0 flex-col gap-3"
            :class="wide && 'flex-[999_1_560px]'"
        >
            <div class="flex items-center gap-2 px-1">
                <h2
                    class="m-0 grow whitespace-nowrap"
                    :class="wide ? 'text-stat-lg' : 'text-section'"
                >
                    {{ monthName(calendar.month) }}
                </h2>
                <FmButton
                    v-if="wide"
                    variant="neutral"
                    :icon="CalendarSync"
                    aria-haspopup="dialog"
                    @click="emit('link')"
                    >{{ $t('Connect a calendar') }}</FmButton
                >
                <FmButton
                    v-else
                    variant="neutral"
                    small
                    :icon="CalendarSync"
                    :label="$t('Connect a calendar')"
                    aria-haspopup="dialog"
                    @click="emit('link')"
                />
                <span
                    v-if="wide"
                    aria-hidden="true"
                    class="mx-1 h-6 w-px bg-edge-soft"
                />
                <FmButton variant="neutral" @click="goToday">{{
                    $t('Today')
                }}</FmButton>
                <FmButton
                    variant="neutral"
                    small
                    :icon="ChevronLeft"
                    :label="$t('Month before')"
                    @click="move(-1)"
                />
                <FmButton
                    variant="neutral"
                    small
                    :icon="ChevronRight"
                    :label="$t('Month after')"
                    @click="move(1)"
                />
            </div>
            <FmMonthGrid
                :month="calendar.month"
                :today="calendar.today"
                :selected="selected"
                :days="days"
                :wide="wide"
                @select="selected = $event"
            />
        </div>

        <section
            class="flex min-w-0 flex-col gap-2"
            :class="wide && 'flex-[1_1_320px] pt-[52px]'"
            :aria-label="dayTitle"
        >
            <div class="flex flex-col gap-0.5 px-1">
                <h2 class="m-0 text-section">{{ dayTitle }}</h2>
                <span v-if="summary" class="text-meta text-ink-soft">{{
                    summary
                }}</span>
            </div>
            <FmCard v-if="dayOpen.length + dayDone.length > 0" list>
                <FmAgendaRow
                    v-for="entry in dayOpen"
                    :key="entry.key"
                    :entry="entry"
                    :person-avatar="personAvatar(entry)"
                    @open="emit('open', entry)"
                />
                <FmAgendaRow
                    v-for="entry in dayDone"
                    :key="entry.key"
                    :entry="entry"
                    checked
                    :person-avatar="personAvatar(entry)"
                    @open="emit('open', entry)"
                />
            </FmCard>
            <FmCard v-else>
                <p class="m-0 text-body-sm text-ink-soft">
                    {{ $t('Nothing on this day.') }}
                </p>
            </FmCard>
        </section>
    </div>
</template>
