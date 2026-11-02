<script setup lang="ts">
/**
 * The *Agenda*: what expires and what is to pay or to arrive, from today to the
 * end of the period chosen. First the deadlines already passed (*To sort out*,
 * in red), then one block for each month with the payments of the month. The
 * filters (period, person and areas) are a column beside the list on the
 * computer and a panel from below on the phone, opened from the line that tells
 * what is chosen. The server sends the entries of the period; the person and the
 * areas filter in the page.
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
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useAgendaFilters } from '@/composables/useAgendaFilters';
import { useFormat } from '@/composables/useFormat';
import {
    areaCounts,
    groupByMonth,
    summary,
    visibleEntries,
} from '@/lib/agenda';
import { agendaTabs } from '@/lib/reminders';
import { create } from '@/routes/reminders';

const { agenda } = defineProps<{
    /** The entries of the period with the filters saved, the people and the areas. */
    agenda: App.Data.AgendaData;
}>();

const { euro } = useFormat();
const { filters, toggleArea, choosePerson, chooseMonths, reset } =
    useAgendaFilters(agenda.filters);

const desktop = useMediaQuery('(min-width: 1024px)');
const sheetOpen = ref(false);

/** The entries for the person and the areas chosen. */
const shown = computed(() => visibleEntries(agenda.entries, filters.value));

/** The blocks of the list; `from` is today. */
const groups = computed(() => groupByMonth(shown.value, agenda.from));

/** How many entries each area has for the person chosen, the areas turned off too. */
const counts = computed(() =>
    areaCounts(agenda.entries, filters.value.person_id, agenda.areas),
);

/** The name of each area, as the server gives it. */
const labels = computed((): Record<string, string> =>
    Object.fromEntries(agenda.areas.map((a) => [a.value, a.label])),
);

/** The people of the family by id, for the circle of the row. */
const avatars = computed(
    (): Map<number, Pick<App.Data.PersonData, 'initials' | 'photo'>> =>
        new Map(agenda.people.map((person) => [person.id, person])),
);

/** What the areas turned off hide, area by area; an area without entries hides nothing. */
const hidden = computed((): Record<string, number> =>
    Object.fromEntries(
        filters.value.hidden_areas
            .map((area): [string, number] => [area, counts.value[area] ?? 0])
            .filter(([, count]) => count > 0),
    ),
);

/** The sentence above the list. */
const summaryText = computed(() =>
    summary(
        shown.value.length,
        hidden.value,
        filters.value.months,
        agenda.from,
        agenda.to,
        labels.value,
    ),
);

/** The line of the phone: the period, the person and the areas chosen. */
const chosenText = computed((): string => {
    const person = agenda.people.find(
        (candidate) => candidate.id === filters.value.person_id,
    );
    const off = filters.value.hidden_areas.length;

    return [
        transChoice(
            '{1} Next month|[2,*] Next :count months',
            filters.value.months,
            {
                count: String(filters.value.months),
            },
        ),
        person?.first_name ?? trans('Whole family'),
        off === 0
            ? trans('All areas')
            : transChoice('{1} :count area off|[2,*] :count areas off', off, {
                  count: String(off),
              }),
    ].join(' · ');
});

/** The server sent nothing for the period, whatever the filters. */
const nothingInPeriod = computed(() => agenda.entries.length === 0);
</script>

<template>
    <Head :title="$t('Agenda')" />

    <FmTitle :title="$t('Agenda')">
        <template #actions>
            <FmButton variant="outline" :icon="Plus" :href="create().url">{{
                $t('New reminder')
            }}</FmButton>
        </template>
    </FmTitle>
    <FmTabs
        variant="pill"
        class="lg:w-[340px]"
        :label="$t('Agenda views')"
        :tabs="agendaTabs('deadlines', agenda.open_reminders)"
    />

    <div class="grid gap-5 lg:grid-cols-[300px_1fr] lg:items-start">
        <FmAgendaFilters
            v-if="desktop"
            variant="column"
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
        <FmAgendaFilters
            v-else
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

        <div class="flex min-w-0 flex-col gap-5">
            <div
                v-if="!desktop"
                class="flex items-center justify-between gap-3 text-[15px]"
            >
                <span class="text-ink-soft">{{ chosenText }}</span>
                <button
                    type="button"
                    class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-action"
                    @click="sheetOpen = true"
                >
                    {{ $t('Change') }}
                </button>
            </div>

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
                <p class="m-0 text-[15px] text-ink-soft">{{ summaryText }}</p>

                <div
                    v-if="shown.length === 0"
                    class="flex flex-wrap items-center gap-3 rounded-2xl bg-white p-5"
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
                        <span class="text-[15px] text-ink-soft">{{
                            $t(':amount outgoing', {
                                amount: euro(group.outgoing),
                            })
                        }}</span>
                    </template>
                    <FmAgendaRow
                        v-for="entry in group.entries"
                        :key="entry.key"
                        :entry="entry"
                        :person-avatar="
                            entry.person_id === null
                                ? undefined
                                : avatars.get(entry.person_id)
                        "
                    />
                    <p
                        v-if="group.overdue"
                        class="m-0 px-4 py-3 text-sm text-ink-soft"
                    >
                        {{
                            $t(
                                'Deadlines passed in the last three months: renew them, or update the date on the card.',
                            )
                        }}
                    </p>
                </FmCard>

                <p class="m-0 text-sm text-ink-soft">
                    {{
                        $t(
                            'Payments show from today on: the late ones are on the Home.',
                        )
                    }}
                </p>
            </template>
        </div>
    </div>
</template>
