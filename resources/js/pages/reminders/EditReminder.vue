<script setup lang="ts">
/**
 * *New reminder* and the change of one written by hand: what to do, the day,
 * who takes care of it, the thing it refers to (a card, a document or a person)
 * and the group of notices, with when it notifies and the people who get them.
 * On the computer the form is a panel beside the list of the reminders; on the
 * phone it is the whole screen, with *Cancel* and *Save* at the bottom. An
 * `admin` can also delete a reminder written by hand.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, defineAsyncComponent } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { errors } from '@/lib/errors';
import { isEmpty, withMethod, withNone } from '@/lib/forms';
import { offsetsHint, recipientsSentence } from '@/lib/reminders';
import { destroy, index, store, update } from '@/routes/reminders';

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

/** On the computer the form is a panel beside the list. */
const desktop = useMediaQuery('(min-width: 1024px)');

const form = useForm({
    title: reminder?.title ?? '',
    due_date: reminder?.due_date ?? '',
    person_id: reminder?.person ? String(reminder.person.id) : '',
    remindable: remindable ?? '',
    reminder_group_id:
        reminder?.group_id === null || reminder === null
            ? ''
            : String(reminder.group_id),
});

/** While the form has changes not saved, leaving the page asks first. */
useUnsavedChanges(() => form.data());

/** The title of the page. */
const heading = computed(() =>
    reminder ? trans('Edit reminder') : trans('New reminder'),
);

/** The things a reminder can refer to as one list: «Home · Casa di via Roma», by `type:id`. */
const things = computed(() =>
    links.flatMap((group) =>
        group.options.map((option) => ({
            value: `${group.type}:${option.value}`,
            label: `${group.label} · ${option.label}`,
        })),
    ),
);

/** The choices of the group: the default ones first, then the groups of the family. */
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

    <div
        class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_460px] lg:items-start"
        :class="!desktop && 'mx-auto w-full max-w-[560px]'"
    >
        <Reminders v-if="desktop" embedded :reminders="reminders" />
        <form
            class="flex flex-col gap-3.5 rounded-3xl bg-white p-5 lg:sticky lg:top-6 lg:p-7"
            novalidate
            @submit.prevent="save"
        >
            <div class="flex items-center justify-between gap-3">
                <h1 class="m-0 text-xl font-extrabold lg:text-[22px]">
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
                class="sticky bottom-0 -mx-5 flex gap-2.5 bg-white px-5 pt-2 pb-1 lg:static lg:m-0 lg:p-0 lg:pt-2"
            >
                <FmDeleteButton
                    v-if="can_delete && reminder"
                    :url="destroy.url(reminder.id)"
                    :question="$t('Delete this reminder?')"
                    :text="$t('It will not come back.')"
                    :confirm-label="$t('Delete the reminder')"
                />
                <FmButton
                    variant="soft"
                    :href="index().url"
                    class="flex-1"
                    data-discard-changes
                    >{{ $t('Cancel') }}</FmButton
                >
                <FmButton
                    type="submit"
                    class="flex-2"
                    :disabled="form.processing"
                    >{{ $t('Save') }}</FmButton
                >
            </div>
        </form>
    </div>
</template>
