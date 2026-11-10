<script setup lang="ts">
/**
 * *New reminder* and the change of one written by hand: what to do, the day,
 * who takes care of it, the thing it refers to (a card, a document or a person)
 * and the group of notices, with when it notifies and the people who get them.
 * On the computer the form is a window in the middle, over the list of the
 * reminders (board `Nuovo-promemoria-pc`), and closing it goes back to the
 * list; on the phone it is the whole screen, with *Cancel* and *Save* at the
 * bottom. An
 * `admin` can also delete a reminder written by hand.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { createReusableTemplate } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, defineAsyncComponent } from 'vue';
import { useComputerLayout } from '@/composables/useLayoutMode';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { usePageTrail } from '@/composables/usePageTrail';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { errors } from '@/lib/errors';
import { isEmpty, withMethod, withNone } from '@/lib/forms';
import { offsetsHint, recipientsSentence } from '@/lib/reminders';
import { agenda } from '@/routes';
import { destroy, index, store, update } from '@/routes/reminders';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';

/**
 * The list of *Reminders* beside the form. It is a page of its own too, so it
 * loads with import(): imported at the top it would become a shared piece of
 * the build, and Laravel would not find the page *Reminders* any more.
 */
const Reminders = defineAsyncComponent(() => import('./Reminders.vue'));

const {
    reminder,
    remindable,
    groups,
    default_recipients,
    default_offsets,
    links,
    can_delete,
    reminders,
} = defineProps<{
    /** The reminder to change, or null for a new one. */
    reminder: App.Data.ReminderData | null;
    /** The thing it refers to as `type:id`, or null. */
    remindable: string | null;
    /** The people of the family, for *Who takes care of it*. */
    people: App.Data.OptionData[];
    /** The groups of notices, each with the people who get them. */
    groups: App.Data.ReminderGroupChoiceData[];
    /** The people who get the notices of the default group, used when no group is chosen. */
    default_recipients: string[];
    /** When the default group notifies, in days from the due date. */
    default_offsets: number[];
    /** The things a reminder can refer to, grouped by type. */
    links: App.Data.LinkGroupData[];
    /** Whoever is looking can delete this reminder (an `admin`, and not an automatic one). */
    can_delete: boolean;
    /** The list of the reminders, shown beside the form on the computer. */
    reminders: App.Data.RemindersPageData;
}>();

/** On the computer the form is a window over the list. */
const desktop = useComputerLayout();

/** The form, written once: in the window on the computer, the whole page on the phone. */
const [DefineForm, ReuseForm] = createReusableTemplate();

/**
 * Goes back to the list when the window closes (×, Esc or a tap outside).
 *
 * @param open - whether the window is still open
 */
function closeWindow(open: boolean): void {
    if (!open) {
        router.visit(index().url);
    }
}

const form = useForm({
    title: reminder?.title ?? '',
    due_date: reminder?.due_date ?? '',
    person_id: reminder?.person ? String(reminder.person.id) : '',
    remindable: remindable ?? '',
    // A group outside the choices is the default one (the server leaves it out):
    // «Default» does the same
    reminder_group_id: groups.some(
        (group) => group.value === String(reminder?.group_id),
    )
        ? String(reminder?.group_id)
        : '',
});

/**
 * While the form has changes not saved, leaving asks first: also closing the
 * window, which goes back to the list.
 */
useUnsavedChanges(() => form.data());

/** The title of the page. */
const heading = computed(() =>
    reminder ? trans('Edit reminder') : trans('New reminder'),
);

usePageTrail(() => [
    { label: trans('Agenda'), href: agenda().url },
    { label: trans('Reminders'), href: index().url },
    { label: heading.value },
]);

/** The things a reminder can refer to as one list: «Home · Casa di via Roma», by `type:id`. */
const things = computed(() =>
    links.flatMap((group) =>
        group.options.map((option) => ({
            value: `${group.type}:${option.value}`,
            label: `${group.label} · ${option.label}`,
        })),
    ),
);

/** The choices of the group: «Default» (the default group of the family), then the other groups. */
const groupChoices = computed(() => [
    { value: '', label: trans('Default') },
    ...groups.map(({ value, label }) => ({ value, label })),
]);

/** Who gets the notices: the group chosen, or the default one when none is. */
const recipients = computed(
    (): string[] =>
        groups.find((group) => group.value === form.reminder_group_id)
            ?.recipients ?? default_recipients,
);

/** When the group chosen notifies, or the default group when none is. */
const hint = computed((): string =>
    offsetsHint(
        groups.find((group) => group.value === form.reminder_group_id)
            ?.offsets ?? default_offsets,
    ),
);

/** Sends the reminder if the title and the day are there. */
function save(): void {
    form.clearErrors();

    for (const field of ['title', 'due_date'] as const) {
        if (isEmpty(form[field])) {
            form.setError(field, trans('This field is required.'));
        }
    }

    if (form.hasErrors) {
        return;
    }

    const target = reminder ? update(reminder.id) : store();

    form.transform((data) => withMethod(data, target.method)).post(target.url);
}

useSaveShortcut(save, () => !form.processing);
</script>

<template>
    <Head :title="heading" />

    <DefineForm>
        <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
            <FormKit
                v-model="form.title"
                type="text"
                name="title"
                :label="$t('What needs doing') + ' *'"
                autocomplete="off"
                :errors="errors(form.errors.title)"
            />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormKit
                    v-model="form.due_date"
                    id="due_date"
                    type="datepicker"
                    name="due_date"
                    :label="$t('Due on') + ' *'"
                    :errors="errors(form.errors.due_date)"
                />
                <FormKit
                    v-model="form.person_id"
                    type="autocomplete"
                    open-on-click
                    name="person_id"
                    :label="$t('Who takes care of it')"
                    :options="withNone(people)"
                    :errors="errors(form.errors.person_id)"
                />
            </div>
            <FormKit
                v-model="form.remindable"
                type="autocomplete"
                open-on-click
                name="remindable"
                :label="$t('Refers to')"
                :options="withNone(things)"
                :help="$t('Optional: a card, a document or a person.')"
                :errors="errors(form.errors.remindable)"
            />
            <FormKit
                v-model="form.reminder_group_id"
                type="dropdown"
                name="reminder_group_id"
                :label="$t('Notices')"
                :options="groupChoices"
                :help="hint"
                :errors="errors(form.errors.reminder_group_id)"
            />
            <FmNotice>{{ recipientsSentence(recipients) }}</FmNotice>

            <div
                class="sticky bottom-0 -mx-5 flex gap-2.5 bg-card px-5 pt-2 pb-1 lg:static lg:m-0 lg:justify-end lg:p-0 lg:pt-2"
            >
                <FmDeleteButton
                    class="lg:mr-auto"
                    v-if="can_delete && reminder"
                    :url="destroy.url(reminder.id)"
                    :question="$t('Delete this reminder?')"
                    :text="$t('It will not come back.')"
                    :confirm-label="$t('Delete the reminder')"
                />
                <FmButton
                    :variant="desktop ? 'text' : 'soft'"
                    :href="index().url"
                    class="flex-1 lg:flex-none"
                    data-discard-changes
                    >{{ $t('Cancel') }}</FmButton
                >
                <FmButton
                    type="submit"
                    class="flex-2 lg:flex-none lg:px-7"
                    :disabled="form.processing"
                    >{{ $t('Save') }}</FmButton
                >
            </div>
        </form>
    </DefineForm>

    <template v-if="desktop">
        <Reminders embedded :reminders="reminders" />
        <FmBottomSheet :open="true" :title="heading" @update:open="closeWindow">
            <ReuseForm />
        </FmBottomSheet>
    </template>
    <div
        v-else
        class="mx-auto flex w-full max-w-[560px] flex-col gap-3.5 rounded-sheet bg-card p-5"
    >
        <div class="flex items-center justify-between gap-3">
            <h1 class="m-0 text-stat">
                {{ heading }}
            </h1>
            <Link
                :href="index().url"
                :aria-label="$t('Close')"
                class="flex size-11 items-center justify-center text-ink-soft"
            >
                <FmIcon :icon="X" :size="22" />
            </Link>
        </div>
        <ReuseForm />
    </div>
</template>
