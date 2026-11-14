<script setup lang="ts">
/**
 * The content of the panel of a row of the Agenda (boards *Pannelli*,
 * *Agenda-scadenza*, *Agenda-pagamento*, *Agenda-impegno*): the name and what
 * happens, the date with how far it is (red once passed), the person and the
 * area, the lines of the values (the notes of a task, the notices of its
 * reminder, the card) and the buttons. A deadline has *Done* and *Postpone*
 * (when it has an automatic reminder), *Update the date* and *Renewed* (when
 * the person can edit the card) and *Open the card*; a payment has *I paid it*
 * and *Open the due date*, never *Done*; a task has *Done*, *Postpone* and the
 * ⋯ menu with *Edit* and *Delete*. In the Done list (`done`) it says who closed
 * it and when, and only *Reopen* is left. *Done*, *Reopen* and *Delete* are
 * done here (*Done* says so for a moment with *Undo*); the others are emitted,
 * for the page. A payment that also pays a deadline carries its reminder, but
 * still has no *Done* nor *Postpone*. The container (a panel from
 * below on the phone, a side panel on the computer) is chosen by the page.
 *
 * @group panels
 */
import type { LucideIcon } from '@lucide/vue';
import {
    ArrowRight,
    Bell,
    CalendarDays,
    CalendarSync,
    Check,
    Clock,
    Pencil,
    RotateCcw,
    Trash2,
    Wallet,
    X,
} from '@lucide/vue';
import { trans, getActiveLanguage } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { useFormat } from '@/composables/useFormat';
import type { Area } from '@/lib/areas';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { shortDate } from '@/lib/budget';
import {
    markReminderDone,
    reopenReminder,
    withoutPayQuery,
} from '@/lib/agenda';
import { daysFromToday, relativeDays } from '@/lib/format';
import { destroy } from '@/routes/reminders';
import FmAmount from './FmAmount.vue';
import FmAvatar from './FmAvatar.vue';
import FmButton from './FmButton.vue';
import FmConfirmDelete from './FmConfirmDelete.vue';
import FmIcon from './FmIcon.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';
import FmSectionLink from './FmSectionLink.vue';
import FmValueList from './FmValueList.vue';
import type { ValueLine } from './FmValueList.vue';

const {
    entry,
    done = false,
    areaName,
    personAvatar,
} = defineProps<{
    /** The entry, as the server sends it. */
    entry: App.Data.AgendaEntryData;
    /** The panel of the Done list: when and by whom it was closed, and only *Reopen*. */
    done?: boolean;
    /** The name of the area, from the areas of the page; without it only the icon of the area shows. */
    areaName?: string;
    /** The initials and photo of the person, from the people of the page; without it the first letter of the name shows. */
    personAvatar?: Pick<App.Data.PersonData, 'initials' | 'photo'>;
}>();

const emit = defineEmits<{
    /** *Postpone* was tapped: the page asks for the new day. */
    postpone: [];
    /** *Update the date* was tapped: the page asks for the new date of the card. */
    updateDate: [];
    /** *Renewed* was tapped: the page asks for the new date, as a renewal. */
    renewed: [];
    /** *I paid it* was tapped: the page opens the payment. */
    pay: [];
    /** The panel is to be closed: the close button, or *Done* once saved. */
    close: [];
}>();

const { date } = useFormat();

/** Whether the panel of the confirmation of *Delete* is open. */
const confirming = ref(false);

/** The reminder, when there is one. */
const reminder = computed(() => entry.reminder);

/** The area, `general` if the server sends one this app does not know. */
const area = computed((): Area => areaOf(entry.area) ?? 'general');

/** Whether the day is already passed and the entry still open. */
const late = computed((): boolean => !done && daysFromToday(entry.date) < 0);

/**
 * The line of the date: the day and how far it is, or that it is done, with
 * the words of its kind (in Italian a task is «fatto», a deadline «fatta»).
 */
const distance = computed((): string => {
    if (!done) {
        return relativeDays(entry.date);
    }

    return entry.kind === 'task' ? trans('task done') : trans('deadline done');
});

/** The first letter of the first name, for the circle of the person. */
const initial = computed((): string =>
    Array.from(entry.person ?? '')
        .slice(0, 1)
        .join('')
        .toUpperCase(),
);

/** The line of the notices: the days and who they reach; empty when there is none. */
const notices = computed((): string => {
    const days = reminder.value?.notice_days ?? [];

    if (days.length === 0) {
        return '';
    }

    const list = (items: string[]): string =>
        new Intl.ListFormat(getActiveLanguage(), {
            type: 'conjunction',
        }).format(items);
    const dates = list(
        days.map((day) => trans('on :date', { date: shortDate(day) })),
    );
    const names = reminder.value?.recipients ?? [];

    return names.length === 0
        ? trans('Notice :dates', { dates })
        : trans('Notice :dates · to :names', { dates, names: list(names) });
});

/** The line of the Done list: when it was closed and by whom. */
const doneLine = computed((): string =>
    trans('Done on :date · :name', {
        date: shortDate(reminder.value?.done_on ?? entry.date),
        name: reminder.value?.done_by ?? '',
    }),
);

/**
 * The lines of the values, in the order of the boards: the account and the
 * planned expense of a payment, what a task is linked to, the notes, the
 * notices of the reminder and the card of a deadline.
 */
const lines = computed((): ValueLine[] => {
    const list: ValueLine[] = [];
    const add = (label: string, text: string, icon?: LucideIcon): void => {
        list.push({ label, text, href: null, icon });
    };

    if (entry.account) {
        add(trans('Bank account'), entry.account);
    }

    if (entry.planned) {
        add(trans('Planned expense'), entry.planned);
    }

    if (entry.kind === 'task' && entry.subtitle !== '') {
        add(trans('Referred to'), entry.subtitle);
    }

    if (entry.notes) {
        add(trans('Notes'), entry.notes);
    }

    if (!done && notices.value !== '') {
        add(trans('Reminder'), notices.value, Bell);
    }

    if (entry.kind === 'deadline' && entry.subtitle !== '') {
        add(trans('Card'), entry.subtitle);
    }

    return list;
});

/** The entries of the ⋯ menu of a task: *Edit* and *Delete*, for who can. */
const canEdit = computed(
    (): boolean => entry.kind === 'task' && reminder.value?.can_edit === true,
);
const canDelete = computed(
    (): boolean => entry.kind === 'task' && reminder.value?.can_delete === true,
);

/** Marks the reminder as done, closes the panel and offers *Undo* for a moment. */
function markAsDone(): void {
    if (reminder.value !== null) {
        markReminderDone(reminder.value.id, entry.name, () => emit('close'));
    }
}

/** Puts the reminder back among the things to do and closes the panel. */
function reopenTask(): void {
    if (reminder.value !== null) {
        reopenReminder(reminder.value.id, () => emit('close'));
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <h2 class="m-0 text-sheet-title break-words">
                    {{ entry.name }}
                </h2>
                <span class="text-body-sm text-ink-soft">{{
                    entry.detail
                }}</span>
            </div>
            <FmButton
                variant="neutral"
                small
                :icon="X"
                :label="$t('Close')"
                @click="emit('close')"
            />
        </div>

        <div class="flex flex-col gap-2 text-body-sm">
            <span class="flex items-center gap-2">
                <FmIcon class="text-ink-soft" :icon="CalendarDays" :size="18" />
                <span
                    ><span>{{ date(entry.date) }}</span> ·
                    <span
                        :class="
                            late ? 'text-label text-overdue' : 'text-ink-soft'
                        "
                        >{{ distance }}</span
                    ></span
                >
            </span>
            <span class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span
                    v-if="entry.person"
                    class="inline-flex items-center gap-2"
                >
                    <FmAvatar
                        :initials="personAvatar?.initials ?? initial"
                        :photo="personAvatar?.photo ?? null"
                        :size="24"
                        aria-hidden="true"
                    />{{ entry.person }}
                </span>
                <span class="inline-flex items-center gap-2">
                    <span
                        class="flex size-6 items-center justify-center rounded-tag"
                        :class="areaTile[area]"
                        :title="areaName"
                    >
                        <FmIcon :icon="areaIcon(area)" :size="14" />
                    </span>
                    <template v-if="areaName">{{ areaName }}</template>
                </span>
            </span>
        </div>

        <span v-if="entry.amount !== null" class="text-stat-lg tabular-nums">
            <FmAmount
                :amount="entry.amount"
                :estimated="entry.estimated"
                :income="entry.kind === 'income'"
            />
        </span>

        <span
            v-if="done"
            class="flex items-center gap-1.5 text-body-sm text-ink-soft"
        >
            <FmIcon class="text-paid" :icon="Check" :size="16" />
            {{ doneLine }}
        </span>

        <FmValueList
            v-if="lines.length > 0"
            class="border-t border-divider pt-3.5"
            :values="lines"
        />

        <FmSectionLink
            v-if="entry.kind === 'deadline'"
            :href="entry.href"
            :icon="ArrowRight"
            >{{ $t('Open the card') }}</FmSectionLink
        >

        <div class="mt-1 flex flex-col gap-2.5">
            <template v-if="done">
                <FmButton
                    v-if="reminder"
                    :icon="RotateCcw"
                    wide
                    @click="reopenTask"
                    >{{ $t('Reopen') }}</FmButton
                >
            </template>
            <template v-else>
                <div
                    v-if="entry.kind === 'payment' || entry.kind === 'income'"
                    class="flex flex-col gap-2.5"
                >
                    <FmButton :icon="Wallet" wide @click="emit('pay')">{{
                        $t('I paid it')
                    }}</FmButton>
                    <FmSectionLink
                        :href="withoutPayQuery(entry.href)"
                        :icon="ArrowRight"
                        >{{ $t('Open the due date') }}</FmSectionLink
                    >
                </div>
                <div
                    v-if="reminder && entry.kind !== 'payment'"
                    class="flex gap-2.5"
                >
                    <FmButton
                        class="flex-2"
                        :icon="Check"
                        @click="markAsDone"
                        >{{ $t('Done') }}</FmButton
                    >
                    <FmButton
                        class="flex-1"
                        variant="neutral"
                        :icon="Clock"
                        @click="emit('postpone')"
                        >{{ $t('Postpone') }}</FmButton
                    >
                    <FmMenu v-if="canEdit || canDelete" :label="$t('More')">
                        <FmMenuItem
                            v-if="canEdit"
                            :icon="Pencil"
                            :href="entry.href"
                            >{{ $t('Edit') }}</FmMenuItem
                        >
                        <FmMenuItem
                            v-if="canDelete"
                            :icon="Trash2"
                            danger
                            @select="confirming = true"
                            >{{ $t('Delete') }}</FmMenuItem
                        >
                    </FmMenu>
                </div>
                <div
                    v-if="
                        entry.kind === 'deadline' && entry.update_url !== null
                    "
                    class="flex gap-2.5"
                >
                    <FmButton
                        class="flex-1"
                        variant="neutral"
                        :icon="CalendarDays"
                        @click="emit('updateDate')"
                        >{{ $t('Update the date') }}</FmButton
                    >
                    <FmButton
                        class="flex-1"
                        variant="neutral"
                        :icon="CalendarSync"
                        @click="emit('renewed')"
                        >{{ $t('Renewed') }}</FmButton
                    >
                </div>
            </template>
        </div>

        <FmConfirmDelete
            v-if="reminder && canDelete"
            v-model:open="confirming"
            :url="destroy.url(reminder.id)"
            :question="$t('Delete this task?')"
            :text="$t('The task and its notices are deleted for good.')"
            :confirm-label="$t('Delete the task')"
        />
    </div>
</template>
