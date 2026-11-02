<script setup lang="ts">
/**
 * One notice of a group, as a row of the form of *Settings → Notices*: how
 * many days, when (before, on the day, after), where it arrives, to whom, and
 * *Remove this notice*. «Everyone» and the people exclude each other. The row
 * edits its rule through `v-model`, and `remove` asks the form to drop it.
 *
 * @group forms
 */
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import type {
    NoticeChannels,
    NoticeRule,
    NoticeTiming,
    RulePerson,
} from '@/lib/reminders';
import FmIcon from './FmIcon.vue';

const { people } = defineProps<{
    /** The people with an account, for «To whom». */
    people: RulePerson[];
}>();

const emit = defineEmits<{
    /** The row asks to be removed from the form. */
    remove: [];
}>();

/** The notice being edited. */
const rule = defineModel<NoticeRule>({ required: true });

/** When, as the person chooses it: 0 days is always «on the day». */
const when = computed((): NoticeTiming | 'on' =>
    rule.value.days === 0 ? 'on' : rule.value.timing,
);

/** The choices of *When*. */
const timings = computed(() => [
    { value: 'before', label: trans('before') },
    { value: 'on', label: trans('on the day') },
    { value: 'after', label: trans('after') },
]);

/** The choices of *Where it goes*. */
const channels = computed(() => [
    { value: 'both', label: trans('App and email') },
    { value: 'app', label: trans('App only') },
    { value: 'email', label: trans('Email only') },
]);

/** The choices of *To whom*: everyone, then each person. */
const recipients = computed(() => [
    { value: 'all', label: trans('Everyone') },
    ...people.map((person) => ({ value: person.id, label: person.name })),
]);

/**
 * Changes the rule only when something really changed: FormKit also sends the
 * value it starts from.
 *
 * @param changes - the fields that change
 */
function patch(changes: Partial<NoticeRule>): void {
    const next = { ...rule.value, ...changes };

    if (JSON.stringify(next) !== JSON.stringify(rule.value)) {
        rule.value = next;
    }
}

/**
 * Writes the days; 0 is the day itself, whatever it was before or after.
 *
 * @param value - what the person typed
 */
function setDays(value: unknown): void {
    const days = Math.max(0, Math.trunc(Number(value) || 0));

    patch({ days });
}

/**
 * Writes when: «on the day» is 0 days, and from the day to before or after a
 * 0 becomes 1 day.
 *
 * @param value - before, on or after
 */
function setWhen(value: unknown): void {
    if (value === 'on') {
        patch({ days: 0 });
    } else if (value === 'before' || value === 'after') {
        patch({
            timing: value,
            days: rule.value.days === 0 ? 1 : rule.value.days,
        });
    }
}

/**
 * Writes the people: the one chosen last wins, so «Everyone» removes the
 * people and a person removes «Everyone».
 *
 * @param value - the values of the field
 */
function setRecipients(value: unknown): void {
    const chosen = Array.isArray(value) ? (value as ('all' | number)[]) : [];
    const before = rule.value.recipients;
    const addedAll = chosen.includes('all') && !before.includes('all');
    const people = chosen.filter((one) => one !== 'all');

    // «Everyone» alone stays as it is: FormKit also echoes back the value we
    // just wrote, and that must not empty the list
    patch({
        recipients: addedAll ? ['all'] : people.length === 0 ? chosen : people,
    });
}
</script>

<template>
    <!-- The row measures the card, not the screen: in the card of a group the
         five columns fit only from about 670px. The fields lose their bottom
         margin here, so that the button lines up with them. -->
    <div class="@container/rule">
        <div
            class="grid grid-cols-2 items-start gap-3 @2xl/rule:grid-cols-[72px_auto_minmax(150px,1fr)_minmax(0,1.2fr)_44px] @2xl/rule:gap-x-2.5 [&_.formkit-tags>input]:min-w-10 [&_.formkit-tags>input]:basis-10 [&>.formkit-outer]:mb-0"
        >
            <FormKit
                type="number"
                name="days"
                min="0"
                :label="$t('Days')"
                :model-value="rule.days"
                @input="setDays"
            />
            <FormKit
                type="togglebuttons"
                name="when"
                enforced
                outer-class="condensed"
                :label="$t('When')"
                :options="timings"
                :model-value="when"
                @input="setWhen"
            />
            <FormKit
                type="dropdown"
                name="channels"
                :label="$t('Where it goes')"
                :options="channels"
                :model-value="rule.channels"
                @input="
                    (value: unknown) =>
                        patch({ channels: value as NoticeChannels })
                "
            />
            <FormKit
                type="taglist"
                name="recipients"
                :label="$t('To whom')"
                :options="recipients"
                :model-value="rule.recipients"
                @input="setRecipients"
            />
            <button
                type="button"
                :aria-label="$t('Remove this notice')"
                class="flex size-11 cursor-pointer items-center justify-center border-0 bg-transparent text-ink-soft hover:text-overdue @2xl/rule:mt-[29.5px]"
                @click="emit('remove')"
            >
                <FmIcon :icon="X" :size="20" />
            </button>
        </div>
    </div>
</template>
