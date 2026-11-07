<script setup lang="ts">
/**
 * The *Agenda*: what expires and what is to pay or to arrive, from today to the
 * end of the period chosen, as the boards *Agenda* and *Agenda-pc* draw it.
 * Under the title the tiles sum up what is to sort out (the passed deadlines,
 * only on the computer), what goes out this week and in the whole period. The
 * filters (period, person and areas) are a bar above the list on the computer
 * and three buttons that open a panel from below on the phone. Then the list:
 * first the deadlines already passed (*To sort out*, in red, with *Update the
 * date* and *Renewed*, which open the panel of the new date), then this week, next week and the rest of the period
 * (month by month when it is longer than one), each with what goes out. The
 * server sends the entries of the period; the person and the areas filter in
 * the page.
 */
import { Head } from '@inertiajs/vue3';
import { CalendarRange, Plus } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmAgendaFilters from '@/components/fm/FmAgendaFilters.vue';
import FmAgendaRow from '@/components/fm/FmAgendaRow.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDeadlineDateSheet from '@/components/fm/FmDeadlineDateSheet.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useAgendaFilters } from '@/composables/useAgendaFilters';
import { useFormat } from '@/composables/useFormat';
import {
    agendaTotals,
    areaCounts,
    groupByWeek,
    visibleEntries,
} from '@/lib/agenda';
import { shortDate } from '@/lib/budget';
import { agendaTabs } from '@/lib/reminders';
import { cn } from '@/lib/utils';
import { create } from '@/routes/reminders';

/** A tile above the list: what it sums up, the figure and the line below. */
type Tile = {
    /** What it sums up, for example «This week». */
    label: string;
    /** The figure: a count or an amount. */
    value: string;
    /** The line below. */
    hint: string;
    /** The red tile of what is to sort out. */
    overdue: boolean;
};

const { agenda } = defineProps<{
    /** The entries of the period with the filters saved, the people and the areas. */
    agenda: App.Data.AgendaData;
}>();

const { euro } = useFormat();

/** The passed deadline whose date is being changed, and whether it was renewed; it stays after closing, so the panel can slide away. */
const changing = ref<{
    entry: App.Data.AgendaEntryData;
    renewed: boolean;
} | null>(null);

/** The panel of the new date is open. */
const changingOpen = ref(false);

/**
 * Opens the panel of the new date of a passed deadline.
 *
 * @param entry - the deadline
 * @param renewed - it was renewed, not only corrected
 */
function changeDate(entry: App.Data.AgendaEntryData, renewed: boolean): void {
    changing.value = { entry, renewed };
    changingOpen.value = true;
}
const { filters, toggleArea, choosePerson, chooseMonths, reset } =
    useAgendaFilters(agenda.filters);

const desktop = useMediaQuery('(min-width: 1024px)');
const sheetOpen = ref(false);

/** The entries for the person and the areas chosen. */
const shown = computed(() => visibleEntries(agenda.entries, filters.value));

/** The blocks of the list; `from` is today. */
const groups = computed(() =>
    groupByWeek(shown.value, agenda.from, agenda.to, agenda.months),
);

/** How many entries each area has for the person chosen, the areas turned off too. */
const counts = computed(() =>
    areaCounts(agenda.entries, filters.value.person_id, agenda.areas),
);

/** The people of the family by id, for the circle of the row. */
const avatars = computed(
    (): Map<number, Pick<App.Data.PersonData, 'initials' | 'photo'>> =>
        new Map(agenda.people.map((person) => [person.id, person])),
);

/** The tiles above the list: on the phone without *To sort out*, and with shorter lines. */
const tiles = computed((): Tile[] => {
    const totals = agendaTotals(shown.value, agenda.from);
    const week = totals.week;
    const period = totals.period;

    const overdue: Tile = {
        label: trans('To sort out'),
        value: String(totals.overdue),
        hint: transChoice(
            '{1} passed deadline|[0,*] passed deadlines',
            totals.overdue,
        ),
        overdue: true,
    };
    const thisWeek: Tile = {
        label: trans('This week'),
        value: euro(week.amount),
        hint: desktop.value
            ? transChoice(
                  '{1} :count entry, from :from to :to|[0,*] :count entries, from :from to :to',
                  week.count,
                  {
                      count: String(week.count),
                      from: shortDate(week.from),
                      to: shortDate(week.to),
                  },
              )
            : transChoice('{1} :count entry|[0,*] :count entries', week.count, {
                  count: String(week.count),
              }),
        overdue: false,
    };
    const untilEnd: Tile = {
        label: trans('Until :date', { date: shortDate(agenda.to) }),
        value: euro(period.amount),
        hint: desktop.value
            ? transChoice(
                  '{1} :count outgoing entry :period|[0,*] :count outgoing entries :period',
                  period.count,
                  {
                      count: String(period.count),
                      period: transChoice(
                          '{1} in the next month|[2,*] in the next :count months',
                          agenda.months,
                          { count: String(agenda.months) },
                      ),
                  },
              )
            : transChoice(
                  '{1} :count outgoing entry|[0,*] :count outgoing entries',
                  period.count,
                  { count: String(period.count) },
              ),
        overdue: false,
    };

    // A red tile with nothing to sort out would only alarm
    return desktop.value && totals.overdue > 0
        ? [overdue, thisWeek, untilEnd]
        : [thisWeek, untilEnd];
});

/**
 * The classes of a tile: separate white boxes on the computer (the one to sort
 * out in red), two halves of one box on the phone.
 *
 * @param tile - the tile
 * @returns the classes of its box
 */
function tileClass(tile: Tile): string {
    return desktop.value
        ? cn(
              'flex flex-[1_1_220px] flex-col gap-1 rounded-card px-[18px] py-4',
              tile.overdue ? 'bg-overdue/8' : 'bg-card',
          )
        : cn(
              'flex flex-1 flex-col gap-0.5 px-3.5 py-3 not-last:border-r not-last:border-divider',
          );
}

/** The server sent nothing for the period, whatever the filters. */
const nothingInPeriod = computed(() => agenda.entries.length === 0);
</script>

<template>
    <Head :title="$t('Agenda')" />

    <FmTitle :title="$t('Agenda')">
        <template #beside>
            <FmTabs
                variant="pill"
                class="w-full sm:w-[340px]"
                :label="$t('Agenda views')"
                :tabs="agendaTabs('deadlines', agenda.open_reminders)"
            />
        </template>
        <template #actions>
            <FmButton variant="outline" :icon="Plus" :href="create().url">{{
                $t('New reminder')
            }}</FmButton>
        </template>
    </FmTitle>

    <div class="flex min-w-0 flex-col gap-5">
        <FmAgendaFilters
            v-if="!desktop"
            v-model:open="sheetOpen"
            variant="sheet"
            :filters="filters"
            :people="agenda.people"
            :areas="agenda.areas"
            :periods="agenda.periods"
            :counts="counts"
            :shown="shown.length"
            @months="chooseMonths"
            @person="choosePerson"
            @area="toggleArea"
            @reset="reset"
        />

        <ul
            v-if="!nothingInPeriod"
            :aria-label="$t('In short')"
            class="m-0 flex list-none p-0"
            :class="desktop ? 'flex-wrap gap-3' : 'rounded-card bg-card'"
        >
            <li
                v-for="tile in tiles"
                :key="tile.label"
                :class="tileClass(tile)"
            >
                <span
                    class="font-semibold text-ink-soft"
                    :class="desktop ? 'text-sm' : 'text-[13px]'"
                    >{{ tile.label }}</span
                >
                <span
                    class="font-extrabold whitespace-nowrap tabular-nums"
                    :class="[
                        desktop ? 'text-[26px]' : 'text-xl',
                        tile.overdue ? 'text-overdue' : 'text-ink',
                    ]"
                    >{{ tile.value }}</span
                >
                <span
                    class="text-ink-soft"
                    :class="desktop ? 'text-[13px]' : 'text-xs'"
                    >{{ tile.hint }}</span
                >
            </li>
        </ul>

        <FmAgendaFilters
            v-if="desktop"
            variant="toolbar"
            :filters="filters"
            :people="agenda.people"
            :areas="agenda.areas"
            :periods="agenda.periods"
            :counts="counts"
            :shown="shown.length"
            @months="chooseMonths"
            @person="choosePerson"
            @area="toggleArea"
            @reset="reset"
        />

        <FmEmpty
            v-if="nothingInPeriod"
            :icon="CalendarRange"
            :title="
                $tChoice(
                    '{1} Nothing expiring in the next month|[2,*] Nothing expiring in the next :count months',
                    filters.months,
                    { count: String(filters.months) },
                )
            "
        >
            <FmButton v-if="filters.months < 3" @click="chooseMonths(3)">{{
                $t('See the next 3 months')
            }}</FmButton>
        </FmEmpty>

        <template v-else>
            <div
                v-if="shown.length === 0"
                class="flex flex-wrap items-center gap-3 rounded-card bg-card p-5"
            >
                <span class="text-[15px]">{{
                    $t('No entries with these filters')
                }}</span>
                <FmButton variant="soft" small @click="reset">{{
                    $t('Clear filters')
                }}</FmButton>
            </div>

            <FmCard
                v-for="group in groups"
                :key="group.key"
                :title="group.title"
                :tone="group.overdue ? 'overdue' : undefined"
                list
            >
                <template v-if="group.outgoing !== null" #actions>
                    <span
                        class="text-[13px] whitespace-nowrap text-ink-soft lg:text-sm"
                        >{{
                            $t(':amount outgoing', {
                                amount: euro(group.outgoing),
                            })
                        }}</span
                    >
                </template>
                <FmAgendaRow
                    v-for="entry in group.entries"
                    :key="entry.key"
                    :entry="entry"
                    :actions="group.overdue"
                    @update-date="changeDate(entry, false)"
                    @renewed="changeDate(entry, true)"
                    :person-avatar="
                        entry.person_id === null
                            ? undefined
                            : avatars.get(entry.person_id)
                    "
                />
                <template v-if="group.overdue" #note>
                    {{
                        $t(
                            'A passed deadline stays here for three months, until you renew it or fix the date.',
                        )
                    }}
                </template>
            </FmCard>
        </template>
        <FmDeadlineDateSheet
            v-if="changing"
            v-model:open="changingOpen"
            :entry="changing.entry"
            :renewed="changing.renewed"
        />
    </div>
</template>
