<script setup lang="ts">
/**
 * *Reminders*, the second tab of the Agenda, as the boards *Promemoria* and
 * *Promemoria-pc* draw it. Above the list the filters: *To do* (with how many)
 * or *Done*, the person (the server filters by person), the search on the
 * computer and the areas (these two in the page); on the phone they are
 * buttons that open the panel from below, as in *Deadlines*. The reminders to
 * do are in three blocks, *Late* (red), *This week* (today to Sunday) and
 * *Later*, each with how many and the first ten, the others on request. The
 * switch *Done* marks a reminder done and a bar with *Undo* stays until the
 * person goes on; a renewal the server cannot confirm shows its reason. A
 * late reminder has *Postpone*, the others the ⋯ menu with it, *Edit*, *Open
 * the card* and *Delete*.
 */
import { Head, router } from '@inertiajs/vue3';
import { BellRing, Plus } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmAgendaFilters from '@/components/fm/FmAgendaFilters.vue';
import type { AgendaFilterViews } from '@/components/fm/FmAgendaFilters.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmConfirmDelete from '@/components/fm/FmConfirmDelete.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmPostponeSheet from '@/components/fm/FmPostponeSheet.vue';
import FmReminderRow from '@/components/fm/FmReminderRow.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { todayInRome } from '@/lib/format';
import type { ReminderGroup, ReminderItem } from '@/lib/reminders';
import {
    agendaTabs,
    groupReminders,
    matchesReminder,
    postponeReminder,
    reminderAreaCounts,
    toReminderItem,
} from '@/lib/reminders';
import { create, destroy, done, index, reopen } from '@/routes/reminders';

/** How many rows a block shows before *Show the other N*. */
const FIRST_ROWS = 10;

const { reminders, embedded = false } = defineProps<{
    /** The open and the done reminders, the people, the person chosen and the areas. */
    reminders: App.Data.RemindersPageData;
    /** The list is shown beside the reminder form, which has the head of the page. */
    embedded?: boolean;
}>();

const desktop = useMediaQuery('(min-width: 1024px)');

/** Which list is shown. */
const view = ref<'open' | 'done'>('open');

/** The text typed in the search. */
const search = ref('');

/** The areas turned off. */
const hiddenAreas = ref<string[]>([]);

/** The panel of the filters of the phone is open. */
const sheetOpen = ref(false);

/** The blocks opened with *Show the other N*. */
const expanded = ref<ReminderGroup['key'][]>([]);

/** The reminders just marked as done, hidden until the server sends the list again. */
const justDone = ref<ReminderItem[]>([]);

/** The reminder the server is marking as done. */
const marking = ref<number | null>(null);

/** Why the server did not do what was asked. */
const problem = ref<string | null>(null);

/** The reminder of *Choose a date…*; it stays after closing, so the panel can slide away. */
const postponing = ref<ReminderItem | null>(null);

/** The panel of *Choose a date…* is open. */
const postponeOpen = ref(false);

/** The reminder of *Delete*; it stays after closing, so the panel can slide away. */
const deleting = ref<ReminderItem | null>(null);

/** The question before deleting is open. */
const deleteOpen = ref(false);

/** The reminders still to do, as rows, without the ones just marked. */
const open = computed((): ReminderItem[] =>
    reminders.open
        .map(toReminderItem)
        .filter(
            (item) => !justDone.value.some((hidden) => hidden.id === item.id),
        ),
);

/** The done ones, as rows. */
const finished = computed((): ReminderItem[] =>
    reminders.done.map(toReminderItem),
);

/** The list chosen, with what is searched: the areas count from here. */
const searched = computed((): ReminderItem[] =>
    (view.value === 'open' ? open.value : finished.value).filter((item) =>
        matchesReminder(item, search.value),
    ),
);

/** The rows shown: what is searched, in the areas that are not turned off. */
const shown = computed((): ReminderItem[] =>
    searched.value.filter((item) => !hiddenAreas.value.includes(item.area)),
);

/** How many reminders each area has in the list chosen, the areas turned off too. */
const counts = computed(() =>
    reminderAreaCounts(searched.value, reminders.areas),
);

/** The blocks of the reminders to do. */
const groups = computed(() => groupReminders(shown.value, todayInRome()));

/** The two lists, with how many are still to do. */
const views = computed((): AgendaFilterViews => ({
    label: trans('Status'),
    options: [
        {
            value: 'open',
            label: trans('To do · :count', {
                count: String(open.value.length),
            }),
        },
        { value: 'done', label: trans('Completed') },
    ],
}));

/** The filters as `FmAgendaFilters` reads them. */
const filters = computed(() => ({
    person_id: reminders.person_id,
    hidden_areas: hiddenAreas.value,
}));

/** Some filter of the page or of the server is on. */
const filtered = computed(
    () =>
        search.value.trim() !== '' ||
        hiddenAreas.value.length > 0 ||
        reminders.person_id !== null,
);

/** The last reminder marked as done, for the *Undo* bar. */
const last = computed((): ReminderItem | undefined => justDone.value.at(-1));

/**
 * The title of a block.
 *
 * @param key - the block
 * @returns «Late», «This week» or «Later»
 */
function groupTitle(key: ReminderGroup['key']): string {
    switch (key) {
        case 'late':
            return trans('Late');
        case 'week':
            return trans('This week');
        default:
            return trans('Later');
    }
}

/**
 * The rows a block shows: the first ten, all once opened.
 *
 * @param group - the block
 * @returns the rows to show
 */
function visible(group: ReminderGroup): ReminderItem[] {
    return expanded.value.includes(group.key)
        ? group.items
        : group.items.slice(0, FIRST_ROWS);
}

/**
 * Chooses a person, or the whole family: the server sends their reminders.
 *
 * @param id - the person, or null for everyone
 */
function choosePerson(id: number | null): void {
    router.get(
        index().url,
        { person: id === null ? undefined : String(id) },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

/**
 * Turns an area off, or on again.
 *
 * @param area - the area
 */
function toggleArea(area: string): void {
    hiddenAreas.value = hiddenAreas.value.includes(area)
        ? hiddenAreas.value.filter((hidden) => hidden !== area)
        : [...hiddenAreas.value, area];
}

/** Takes every filter off: the search, the areas and the person. */
function reset(): void {
    search.value = '';
    hiddenAreas.value = [];

    if (reminders.person_id !== null) {
        choosePerson(null);
    }
}

/**
 * Marks a reminder as done.
 *
 * @param item - the reminder
 */
function markDone(item: ReminderItem): void {
    problem.value = null;
    marking.value = item.id;
    router.post(
        done.url(item.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                justDone.value = [...justDone.value, item];
            },
            onError: (errors) => {
                problem.value = errors.status ?? null;
            },
            onFinish: () => {
                marking.value = null;
            },
        },
    );
}

/**
 * Takes the done mark away: from the *Undo* bar, or from *Reopen* in the done list.
 *
 * @param item - the reminder
 */
function undo(item: ReminderItem): void {
    problem.value = null;
    router.delete(reopen.url(item.id), {
        preserveScroll: true,
        onSuccess: () => {
            justDone.value = justDone.value.filter(
                (hidden) => hidden.id !== item.id,
            );
        },
        onError: (errors) => {
            problem.value = errors.status ?? null;
        },
    });
}

/**
 * Moves a reminder to a day of the quick choices of *Postpone*.
 *
 * @param item - the reminder
 * @param to - the new day, `yyyy-mm-dd`
 */
function postpone(item: ReminderItem, to: string): void {
    problem.value = null;
    postponeReminder(item.id, to, {
        onError: (errors) => {
            problem.value = errors.to ?? null;
        },
    });
}

/**
 * Opens the panel of *Choose a date…*.
 *
 * @param item - the reminder
 */
function chooseDate(item: ReminderItem): void {
    postponing.value = item;
    postponeOpen.value = true;
}

/**
 * Asks before deleting a reminder.
 *
 * @param item - the reminder
 */
function askDelete(item: ReminderItem): void {
    deleting.value = item;
    deleteOpen.value = true;
}
</script>

<template>
    <Head v-if="!embedded" :title="$t('Reminders')" />

    <div class="flex min-w-0 flex-col gap-5">
        <FmTitle :title="$t('Agenda')">
            <template #beside>
                <FmTabs
                    variant="pill"
                    class="w-full sm:w-[340px]"
                    :label="$t('Agenda views')"
                    :tabs="agendaTabs('reminders', reminders.open_total)"
                />
            </template>
            <template #actions>
                <FmButton variant="outline" :icon="Plus" :href="create().url">{{
                    $t('New reminder')
                }}</FmButton>
            </template>
        </FmTitle>

        <FmAgendaFilters
            v-model:view="view"
            v-model:search="search"
            v-model:open="sheetOpen"
            :variant="desktop ? 'toolbar' : 'sheet'"
            :filters="filters"
            :people="reminders.people"
            :areas="reminders.areas"
            :family-hidden="reminders.family_hidden"
            :views="views"
            :search-label="desktop ? $t('Search a reminder') : undefined"
            :counts="counts"
            :shown="shown.length"
            @person="choosePerson"
            @area="toggleArea"
            @reset="reset"
        />

        <FmNotice v-if="problem" tone="danger">{{ problem }}</FmNotice>
        <FmNotice v-if="last && view === 'open'" tone="ok">
            {{ $t('Done: :title', { title: last.title }) }}
            <template #action>
                <button
                    type="button"
                    class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-inherit underline"
                    @click="undo(last)"
                >
                    {{ $t('Undo') }}
                </button>
            </template>
        </FmNotice>

        <div
            v-if="shown.length === 0 && filtered"
            class="flex flex-wrap items-center gap-3 rounded-card bg-card p-5"
        >
            <span class="text-body-sm">{{
                $t('No reminders with these filters')
            }}</span>
            <FmButton variant="soft" small @click="reset">{{
                $t('Clear filters')
            }}</FmButton>
        </div>

        <template v-else-if="view === 'open'">
            <FmEmpty
                v-if="shown.length === 0"
                :icon="BellRing"
                :title="$t('Nothing to do')"
                :text="
                    $t(
                        'Reminders appear on their own from the dates of cards and documents, and you can write yours.',
                    )
                "
            >
                <FmButton :icon="Plus" :href="create().url">{{
                    $t('New reminder')
                }}</FmButton>
            </FmEmpty>
            <FmCard
                v-for="group in groups"
                :key="group.key"
                :title="groupTitle(group.key)"
                :tone="group.key === 'late' ? 'overdue' : undefined"
                list
            >
                <template #actions>
                    <span class="text-meta text-ink-soft lg:text-body-sm">{{
                        group.items.length
                    }}</span>
                </template>
                <FmReminderRow
                    v-for="item in visible(group)"
                    :key="item.id"
                    :reminder="item"
                    :pending="marking === item.id"
                    @done="markDone(item)"
                    @postpone="postpone(item, $event)"
                    @choose-date="chooseDate(item)"
                    @delete="askDelete(item)"
                />
                <button
                    v-if="visible(group).length < group.items.length"
                    type="button"
                    class="flex h-control-lg cursor-pointer items-center justify-center gap-1.5 border-0 bg-card text-row text-action"
                    @click="expanded = [...expanded, group.key]"
                >
                    {{
                        $tChoice(
                            '{1} Show the other one|[2,*] Show the other :count',
                            group.items.length - visible(group).length,
                            {
                                count: String(
                                    group.items.length - visible(group).length,
                                ),
                            },
                        )
                    }}
                </button>
            </FmCard>
        </template>

        <template v-else>
            <FmEmpty
                v-if="shown.length === 0"
                :icon="BellRing"
                :title="$t('Nothing done yet')"
            />
            <FmCard v-else :title="$t('Completed')" list>
                <FmReminderRow
                    v-for="item in shown"
                    :key="item.id"
                    :reminder="item"
                    @reopen="undo(item)"
                />
            </FmCard>
        </template>

        <FmPostponeSheet
            v-if="postponing"
            v-model:open="postponeOpen"
            :reminder="postponing"
        />
        <FmConfirmDelete
            v-if="deleting"
            v-model:open="deleteOpen"
            :url="destroy.url(deleting.id)"
            :question="$t('Delete this reminder?')"
            :text="$t('It will not come back.')"
            :confirm-label="$t('Delete the reminder')"
            preserve-state
        />
    </div>
</template>
