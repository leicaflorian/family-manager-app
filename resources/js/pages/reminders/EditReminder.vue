<script setup lang="ts">
/**
 * *New reminder* and the change of one written by hand: what to do, the day,
 * who takes care of it, the thing it refers to (a card, a document or a person)
 * and the group of notices, with the people who get them. It is a window of
 * its own on the computer and the whole screen on the phone, with *Cancel* and
 * *Save* at the bottom.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { errors } from '@/lib/errors';
import { isEmpty, withMethod, withNone } from '@/lib/forms';
import { recipientsSentence } from '@/lib/reminders';
import { index, store, update } from '@/routes/reminders';

const { reminder, remindable, groups, default_recipients, links } =
    defineProps<{
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
        /** The things a reminder can refer to, grouped by type. */
        links: App.Data.LinkGroupData[];
    }>();

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

    <div class="mx-auto flex w-full max-w-[560px] flex-col gap-4">
        <form
            class="flex flex-col gap-3.5 rounded-3xl bg-white p-5 lg:p-7"
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
                    type="dropdown"
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
                :errors="errors(form.errors.reminder_group_id)"
            />
            <FmNotice>{{ recipientsSentence(recipients) }}</FmNotice>

            <div class="flex gap-2.5 pt-2">
                <FmButton variant="soft" :href="index().url" class="flex-1">{{
                    $t('Cancel')
                }}</FmButton>
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
