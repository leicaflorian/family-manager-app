<script setup lang="ts">
/**
 * The *Agenda*: one list of the things to do (deadlines of the cards,
 * payments of the planned expenses and tasks written by hand), as the boards
 * *Agenda*, *Agenda-pc*, *Agenda-fatti* and *Agenda-fatti-pc* draw it. Under
 * the title a switch between *To do* (with the number of the late things in
 * red) and *Done*, and *New task*. In *To do* a red line says how many things
 * are late, the filters (period, person and areas: a bar on the computer,
 * three buttons that open a panel from below on the phone), then the late
 * things, this week, next week and the rest of the period. In *Done* the
 * things closed, month by month, *Show earlier months* at the end.
 * A row opens its panel (`FmAgendaPanel`): from below on the phone, beside the
 * list on the computer (the boards *Agenda-scadenza*, *Agenda-pagamento*,
 * *Agenda-impegno* and *Agenda-pannello-pc*); from there *Postpone*, *Update
 * the date*, *Renewed* and *I paid it* open their own panels. The server sends
 * the entries of the period and the done ones of the person; the person and
 * the areas filter in the page.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarRange,
    ChevronDown,
    CircleAlert,
    CircleCheck,
    Plus,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmAgendaFilters from '@/components/fm/FmAgendaFilters.vue';
import FmAgendaPanel from '@/components/fm/FmAgendaPanel.vue';
import FmAgendaRow from '@/components/fm/FmAgendaRow.vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDeadlineDateSheet from '@/components/fm/FmDeadlineDateSheet.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmPaySheet from '@/components/fm/FmPaySheet.vue';
import FmPostponeSheet from '@/components/fm/FmPostponeSheet.vue';
import FmSidePanel from '@/components/fm/FmSidePanel.vue';
import type { Tab } from '@/components/fm/FmTabs.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useAgendaFilters } from '@/composables/useAgendaFilters';
import { useComputerLayout } from '@/composables/useLayoutMode';
import {
    areaCounts,
    doneByMonth,
    groupByWeek,
    visibleEntries,
} from '@/lib/agenda';
import type { ReminderItem } from '@/lib/reminders';
import { agenda as agendaRoute } from '@/routes';
import { payment as paymentRoute } from '@/routes/budget/deadlines';
import { index as movements } from '@/routes/budget/movements';
import { create } from '@/routes/reminders';

const {
    agenda,
    done = [],
    done_next_page = null,
    payment = null,
    accounts = [],
} = defineProps<{
    /** The entries of the period with the filters saved, the people and the areas. */
    agenda: App.Data.AgendaData;
    /** The done things of the person chosen, page after page (only in the Done view). */
    done?: App.Data.AgendaEntryData[];
    /** The next page of the done things, or null when there is none. */
    done_next_page?: number | null;
    /** The due date of *I paid it*, sent only when the panel asks for it. */
    payment?: App.Data.DeadlineData | null;
    /** The accounts to pay with, sent with `payment`. */
    accounts?: App.Data.OptionData[];
}>();

const desktop = useComputerLayout();

/** The Done view is open. */
const doneView = computed(() => agenda.view === 'done');

const { filters, toggleArea, choosePerson, chooseMonths, reset } =
    useAgendaFilters(agenda.filters);

/** The phone panel of the filters is open. */
const sheetOpen = ref(false);

/** The two views, with the late things beside *To do*. */
const tabs = computed((): Tab[] => [
    {
        label: trans('To do'),
        href: agendaRoute().url,
        current: !doneView.value,
        ...(agenda.overdue > 0
            ? {
                  count: agenda.overdue,
                  countLabel: trans('To do, :count late', {
                      count: String(agenda.overdue),
                  }),
              }
            : {}),
    },
    {
        label: trans('Completed'),
        href: agendaRoute({ query: { view: 'done' } }).url,
        current: doneView.value,
    },
]);

/** The entries of the view open, before the filters of the page. */
const listed = computed(() => (doneView.value ? done : agenda.entries));

/** The entries for the person and the areas chosen. */
const shown = computed(() => visibleEntries(listed.value, filters.value));

/** The blocks of the list: by week in *To do* (`from` is today), by month in *Done*. */
const groups = computed(() =>
    doneView.value
        ? doneByMonth(shown.value)
        : groupByWeek(shown.value, agenda.from, agenda.to, agenda.months),
);

/** How many entries each area has for the person chosen, the areas turned off too. */
const counts = computed(() =>
    areaCounts(listed.value, filters.value.person_id, agenda.areas),
);

/** The people of the family by id, for the circle of the row and of the panel. */
const avatars = computed(
    (): Map<number, Pick<App.Data.PersonData, 'initials' | 'photo'>> =>
        new Map(agenda.people.map((person) => [person.id, person])),
);

/** The names of the areas, for the panel. */
const areaNames = computed(
    (): Map<string, string> =>
        new Map(agenda.areas.map((area) => [area.value, area.label])),
);

/** The server sent nothing for the view, whatever the filters. */
const nothingListed = computed(() => listed.value.length === 0);

/**
 * The circle of the person of an entry.
 *
 * @param entry - the entry
 * @returns the initials and photo, or undefined without a person
 */
function avatarOf(
    entry: App.Data.AgendaEntryData,
): Pick<App.Data.PersonData, 'initials' | 'photo'> | undefined {
    return entry.person_id === null
        ? undefined
        : avatars.value.get(entry.person_id);
}

/** The earlier months of the Done list are being loaded. */
const loadingEarlier = ref(false);

/**
 * *Show earlier months*: adds the next page of the done things to the list,
 * without changing the address.
 *
 * @param page - the page to load
 */
function showEarlier(page: number): void {
    loadingEarlier.value = true;
    router.reload({
        data: { done_page: page },
        only: ['done', 'done_next_page'],
        preserveUrl: true,
        onFinish: () => {
            loadingEarlier.value = false;
        },
    });
}

/** The entry whose panel is shown; it stays after closing, so the panel can slide away. */
const opened = ref<App.Data.AgendaEntryData | null>(null);

/** The panel of the entry is open. */
const panelOpen = ref(false);

/** The entry of the panel as the server sends it now, so a reload refreshes it. */
const panelEntry = computed(
    (): App.Data.AgendaEntryData | null =>
        listed.value.find((entry) => entry.key === opened.value?.key) ??
        opened.value,
);

/**
 * Shows the panel of an entry.
 *
 * @param entry - the entry tapped
 */
function openPanel(entry: App.Data.AgendaEntryData): void {
    opened.value = entry;
    panelOpen.value = true;
}

/** Closes the panel of the entry. */
function closePanel(): void {
    panelOpen.value = false;
}

/** The passed deadline whose date is being changed, and whether it was renewed. */
const changing = ref<{
    entry: App.Data.AgendaEntryData;
    renewed: boolean;
} | null>(null);

/** The panel of the new date is open. */
const changingOpen = ref(false);

/**
 * Opens the panel of the new date of a deadline, in place of its panel.
 *
 * @param entry - the deadline
 * @param renewed - it was renewed, not only corrected
 */
function changeDate(entry: App.Data.AgendaEntryData, renewed: boolean): void {
    closePanel();
    changing.value = { entry, renewed };
    changingOpen.value = true;
}

/** The reminder being postponed, as *Postpone* reads it. */
const postponing = ref<Pick<
    ReminderItem,
    'id' | 'title' | 'due_date' | 'subject'
> | null>(null);

/** The panel of *Postpone* is open. */
const postponeOpen = ref(false);

/**
 * Opens *Postpone* for the reminder of an entry, in place of its panel.
 *
 * @param entry - the entry, with its reminder
 */
function postpone(entry: App.Data.AgendaEntryData): void {
    if (entry.reminder === null) {
        return;
    }

    closePanel();
    postponing.value = {
        id: entry.reminder.id,
        title: entry.name,
        due_date: entry.date,
        subject:
            entry.subtitle === ''
                ? null
                : { label: entry.subtitle, href: null },
    };
    postponeOpen.value = true;
}

/** The key of the payment asked to the server for *I paid it*, until its panel closes. */
const paying = ref<string | null>(null);

/**
 * *I paid it*: asks the server for the due date and the accounts, without
 * changing the address; the panel of the payment opens when they arrive.
 *
 * @param entry - the payment
 */
function pay(entry: App.Data.AgendaEntryData): void {
    closePanel();
    paying.value = entry.key;
    router.reload({
        only: ['payment', 'accounts'],
        data: { payment: entry.key },
        preserveUrl: true,
    });
}

/** The panel of the payment: open once the due date asked has arrived. */
const payOpen = computed({
    get: (): boolean =>
        payment !== null &&
        paying.value ===
            `payment:${payment.planned_id}:${payment.original_date}`,
    set: (open: boolean): void => {
        if (!open) {
            paying.value = null;
        }
    },
});

/** Where the payment is sent: back to the Agenda once recorded. */
const payAction = computed((): string =>
    payment === null
        ? ''
        : paymentRoute.url(
              {
                  kind: payment.kind,
                  id: payment.planned_id,
                  date: payment.original_date,
              },
              { query: { from_agenda: 1 } },
          ),
);
</script>

<template>
    <Head :title="$t('Agenda')" />

    <FmTitle :title="$t('Agenda')">
        <template #beside>
            <FmTabs
                variant="pill"
                class="w-full sm:w-[260px]"
                :label="$t('Agenda views')"
                :tabs="tabs"
            />
        </template>
        <template #actions>
            <FmButton
                v-if="desktop"
                variant="neutral"
                :icon="Plus"
                :href="create().url"
                >{{ $t('New task') }}</FmButton
            >
            <FmButton
                v-else
                variant="neutral"
                :icon="Plus"
                :label="$t('New task')"
                :href="create().url"
            />
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
            :family-hidden="agenda.family_hidden"
            :periods="doneView ? [] : agenda.periods"
            :counts="counts"
            :shown="shown.length"
            @months="chooseMonths"
            @person="choosePerson"
            @area="toggleArea"
            @reset="reset"
        />

        <div
            v-if="!doneView && agenda.overdue > 0"
            role="status"
            class="flex items-center gap-2.5 rounded-lg bg-overdue-soft px-3.5 py-2.5 text-label text-overdue lg:px-4 lg:py-3"
        >
            <FmIcon :icon="CircleAlert" :size="18" />
            {{
                $tChoice(
                    '{1} :count thing late|[2,*] :count things late',
                    agenda.overdue,
                    { count: String(agenda.overdue) },
                )
            }}
        </div>

        <FmAgendaFilters
            v-if="desktop"
            variant="toolbar"
            :filters="filters"
            :people="agenda.people"
            :areas="agenda.areas"
            :family-hidden="agenda.family_hidden"
            :periods="doneView ? [] : agenda.periods"
            :counts="counts"
            :shown="shown.length"
            @months="chooseMonths"
            @person="choosePerson"
            @area="toggleArea"
            @reset="reset"
        />

        <template v-if="nothingListed">
            <FmEmpty
                v-if="doneView"
                :icon="CircleCheck"
                :title="$t('Nothing done yet')"
            />
            <FmEmpty
                v-else
                :icon="CalendarRange"
                :title="
                    $tChoice(
                        '{1} Nothing to do in the next month|[2,*] Nothing to do in the next :count months',
                        filters.months,
                        { count: String(filters.months) },
                    )
                "
            >
                <FmButton v-if="filters.months < 3" @click="chooseMonths(3)">{{
                    $t('See the next 3 months')
                }}</FmButton>
            </FmEmpty>
        </template>

        <template v-else>
            <div
                v-if="shown.length === 0"
                class="flex flex-wrap items-center gap-3 rounded-card bg-card p-5"
            >
                <span class="text-body-sm">{{
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
                <template v-if="doneView" #actions>
                    <span
                        class="text-meta whitespace-nowrap text-ink-soft lg:text-body-sm"
                        >{{ group.entries.length }}</span
                    >
                </template>
                <FmAgendaRow
                    v-for="entry in group.entries"
                    :key="entry.key"
                    :entry="entry"
                    :done="doneView"
                    :reopen="desktop"
                    :person-avatar="avatarOf(entry)"
                    @open="openPanel(entry)"
                />
            </FmCard>

            <FmButton
                v-if="doneView && done_next_page !== null"
                variant="neutral"
                wide
                :icon="ChevronDown"
                :disabled="loadingEarlier"
                @click="showEarlier(done_next_page)"
                >{{ $t('Show earlier months') }}</FmButton
            >
        </template>

        <p
            v-if="doneView"
            class="m-0 px-1 pb-4 text-center text-meta text-ink-soft"
        >
            <Link
                v-if="desktop"
                :href="movements().url"
                class="text-ink-soft hover:text-action"
                >{{
                    $t('Paid expenses are in the movements of the Budget')
                }}</Link
            >
            <template v-else>{{
                $t('Paid expenses are in the movements of the Budget')
            }}</template>
        </p>

        <template v-if="panelEntry">
            <FmSidePanel
                v-if="desktop && panelOpen"
                :label="panelEntry.name"
                @close="closePanel"
            >
                <div class="bg-card p-6">
                    <FmAgendaPanel
                        :entry="panelEntry"
                        :done="doneView"
                        :area-name="areaNames.get(panelEntry.area)"
                        :person-avatar="avatarOf(panelEntry)"
                        @postpone="postpone(panelEntry)"
                        @update-date="changeDate(panelEntry, false)"
                        @renewed="changeDate(panelEntry, true)"
                        @pay="pay(panelEntry)"
                        @close="closePanel"
                    />
                </div>
            </FmSidePanel>
            <FmBottomSheet
                v-else-if="!desktop"
                v-model:open="panelOpen"
                :title="panelEntry.name"
                hide-title
            >
                <FmAgendaPanel
                    :entry="panelEntry"
                    :done="doneView"
                    :area-name="areaNames.get(panelEntry.area)"
                    :person-avatar="avatarOf(panelEntry)"
                    @postpone="postpone(panelEntry)"
                    @update-date="changeDate(panelEntry, false)"
                    @renewed="changeDate(panelEntry, true)"
                    @pay="pay(panelEntry)"
                    @close="closePanel"
                />
            </FmBottomSheet>
        </template>

        <FmDeadlineDateSheet
            v-if="changing"
            v-model:open="changingOpen"
            :entry="changing.entry"
            :renewed="changing.renewed"
        />
        <FmPostponeSheet
            v-if="postponing"
            v-model:open="postponeOpen"
            :reminder="postponing"
        />
        <FmPaySheet
            v-if="payment"
            v-model:open="payOpen"
            :deadline="payment"
            :accounts="accounts"
            :action="payAction"
        />
    </div>
</template>
