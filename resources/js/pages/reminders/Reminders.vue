<script setup lang="ts">
/**
 * *Reminders*, the second tab of the Agenda: what is still to do, late ones
 * first, and what is done. The circle marks a reminder as done and a bar with
 * *Undo* stays until the person goes on; a renewal the server cannot confirm
 * shows its reason. *Who* filters by person. A reminder made by the app opens
 * the card or the document it comes from, one written by hand its form.
 */
import { Head, router } from '@inertiajs/vue3';
import { BellRing, Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmReminderRow from '@/components/fm/FmReminderRow.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { daysFromToday } from '@/lib/format';
import { withNone } from '@/lib/forms';
import type { ReminderItem } from '@/lib/reminders';
import { agendaTabs, toReminderItem } from '@/lib/reminders';
import { cn } from '@/lib/utils';
import { create, done, index, reopen } from '@/routes/reminders';

const { reminders, embedded = false } = defineProps<{
    /** The open and the done reminders, the people for *Who* and the person chosen. */
    reminders: App.Data.RemindersPageData;
    /** The list is shown beside the reminder form, which has the head of the page. */
    embedded?: boolean;
}>();

/** Which list is shown. */
const view = ref<'open' | 'done'>('open');

/** The reminders just marked as done, hidden until the server sends the list again. */
const justDone = ref<ReminderItem[]>([]);

/** Why the server did not mark a reminder as done. */
const problem = ref<string | null>(null);

/** The person chosen in *Who*, as the menu holds it: the id as text, empty for the whole family. */
const person = ref(
    reminders.person_id === null ? '' : String(reminders.person_id),
);

/** The reminders still to do, as rows, without the ones just marked. */
const open = computed((): ReminderItem[] =>
    reminders.open
        .map(toReminderItem)
        .filter(
            (item) => !justDone.value.some((hidden) => hidden.id === item.id),
        ),
);

/** The late ones. */
const late = computed((): ReminderItem[] =>
    open.value.filter((item) => daysFromToday(item.due_date) < 0),
);

/** The ones still to come. */
const coming = computed((): ReminderItem[] =>
    open.value.filter((item) => daysFromToday(item.due_date) >= 0),
);

/** The done ones, as rows. */
const finished = computed((): ReminderItem[] =>
    reminders.done.map(toReminderItem),
);

/** The choices of *Who*. */
const people = computed(() =>
    withNone(reminders.people).map((option) =>
        option.value === ''
            ? { ...option, label: trans('Whole family') }
            : option,
    ),
);

/** The last reminder marked as done, for the *Undo* bar. */
const last = computed((): ReminderItem | undefined => justDone.value.at(-1));

/** The classes of a chip of the filter. */
const chip = (active: boolean): string =>
    cn(
        'h-9 cursor-pointer rounded-full px-3.5 text-sm',
        active
            ? 'border-0 bg-[#EAF4FF] font-bold text-action-dark'
            : 'border border-divider bg-white font-medium text-[#4A5565]',
    );

watch(person, (chosen) => {
    router.get(
        index().url,
        { person: chosen === '' ? undefined : chosen },
        { preserveScroll: true, preserveState: true, replace: true },
    );
});

/**
 * Marks a reminder as done.
 *
 * @param item - the reminder
 */
function markDone(item: ReminderItem): void {
    problem.value = null;
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
</script>

<template>
    <Head v-if="!embedded" :title="$t('Reminders')" />

    <div class="flex flex-col gap-5">
        <FmTitle :title="$t('Agenda')">
            <template #beside>
                <FmTabs
                    variant="pill"
                    class="w-full sm:w-[340px]"
                    :label="$t('Agenda views')"
                    :tabs="
                        agendaTabs(
                            'reminders',
                            reminders.open_total - justDone.length,
                        )
                    "
                />
            </template>
            <template #actions>
                <FmButton :icon="Plus" :href="create().url">{{
                    $t('New reminder')
                }}</FmButton>
            </template>
        </FmTitle>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :aria-pressed="view === 'open'"
                    :class="chip(view === 'open')"
                    @click="view = 'open'"
                >
                    {{ $t('To do · :count', { count: String(open.length) }) }}
                </button>
                <button
                    type="button"
                    :aria-pressed="view === 'done'"
                    :class="chip(view === 'done')"
                    @click="view = 'done'"
                >
                    {{ $t('Done') }}
                </button>
            </div>
            <FormKit
                v-model="person"
                type="dropdown"
                name="person"
                :label="$t('Who')"
                :options="people"
                outer-class="mb-0"
            />
        </div>

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

        <template v-if="view === 'open'">
            <FmEmpty
                v-if="open.length === 0"
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
            <template v-else>
                <FmCard
                    v-if="late.length > 0"
                    :title="$t('Overdue')"
                    tone="overdue"
                    list
                >
                    <FmReminderRow
                        v-for="item in late"
                        :key="item.id"
                        :reminder="item"
                        @done="markDone(item)"
                    />
                </FmCard>
                <FmCard v-if="coming.length > 0" :title="$t('Coming up')" list>
                    <FmReminderRow
                        v-for="item in coming"
                        :key="item.id"
                        :reminder="item"
                        @done="markDone(item)"
                    />
                </FmCard>
            </template>
        </template>

        <template v-else>
            <FmEmpty
                v-if="finished.length === 0"
                :icon="BellRing"
                :title="$t('Nothing done yet')"
            />
            <FmCard v-else :title="$t('Done')" list>
                <FmReminderRow
                    v-for="item in finished"
                    :key="item.id"
                    :reminder="item"
                    @reopen="undo(item)"
                />
            </FmCard>
        </template>

        <p class="m-0 text-sm text-ink-soft">
            {{
                $t(
                    'Reminders appear on their own from the dates of cards; the ones with the icon are written by hand. Notices arrive to everyone in the family who has an account.',
                )
            }}
        </p>
    </div>
</template>
