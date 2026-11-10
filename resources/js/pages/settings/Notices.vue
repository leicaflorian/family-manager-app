<script setup lang="ts">
/**
 * *Settings → Notices*: the groups of notices of the family. Each card is a
 * group with the deadlines it covers and its notices («45 days before · App and
 * email · Everyone»). An administrator changes the groups and saves them all
 * together with *Save*; everyone else only looks. *Edit* opens a group in place
 * on the computer; on the phone it takes the whole screen, with *Cancel* and
 * *Save* at the bottom.
 */
import { Head, router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { useMediaQuery } from '@vueuse/core';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmNotificationRule from '@/components/fm/FmNotificationRule.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import type { NoticeRule, RulePerson } from '@/lib/reminders';
import { ruleText } from '@/lib/reminders';
import { settings } from '@/routes';
import { update } from '@/routes/settings/notices';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { usePageTrail } from '@/composables/usePageTrail';

const { settings: page } = defineProps<{
    /** The groups, the kinds of deadline, the people with an account and whether who looks can change them. */
    settings: App.Data.NoticeSettingsData;
}>();

/** A group as the page edits it. */
type Draft = {
    /** Tells the groups apart before the new ones have an id. */
    key: string;
    id: number | null;
    name: string;
    is_default: boolean;
    deadline_types: string[];
    rules: NoticeRule[];
    example: string | null;
};

/** The notice a new row starts with: a week before, in the app and by email, for everyone. */
const NEW_RULE: NoticeRule = {
    days: 7,
    timing: 'before',
    channels: 'both',
    recipients: ['all'],
};

let newGroups = 0;

/**
 * The groups of the server as drafts to edit.
 *
 * @param groups - the groups of the page
 */
function toDrafts(groups: App.Data.ReminderGroupData[]): Draft[] {
    return groups.map((group) => ({
        key: `group-${group.id}`,
        id: group.id,
        name: group.name,
        is_default: group.is_default,
        deadline_types: [...group.deadline_types],
        rules: group.rules.map((rule) => ({
            name: rule.name,
            days: rule.days,
            timing: rule.timing as NoticeRule['timing'],
            channels: rule.channels as NoticeRule['channels'],
            recipients: [...rule.recipients] as NoticeRule['recipients'],
        })),
        example: group.example,
    }));
}

const drafts = ref<Draft[]>(toDrafts(page.groups));
/** The keys of the groups open for editing. */
const editing = ref<string[]>([]);
/** What the server refused with the last save. */
const refused = ref<string[]>([]);
const saving = ref(false);
/** On the computer a group is edited in place; on the phone it takes the screen. */
const desktop = useMediaQuery('(min-width: 1024px)');

// A save redirects here with the new groups: start from them
watch(
    () => page.groups,
    (groups) => {
        drafts.value = toDrafts(groups);
        editing.value = [];
        changes.reset();
    },
);

/** The people who can be chosen, as the rule row wants them. */
const people = computed((): RulePerson[] => page.people);

/** The names of the kinds of deadline, by key. */
const kindNames = computed(
    () => new Map(page.kinds.map((kind) => [kind.value, kind.label])),
);

/**
 * The kinds of a group in words: «Road tax, inspection».
 *
 * @param draft - the group
 */
function kindsText(draft: Draft): string {
    const names = draft.deadline_types.map(
        (type) => kindNames.value.get(type) ?? type,
    );

    if (names.length === 0) {
        return draft.is_default
            ? trans('Reminders written by hand and all the rest')
            : trans('No deadlines yet');
    }

    return names
        .map((name, index) =>
            index === 0
                ? name
                : name.charAt(0).toLocaleLowerCase() + name.slice(1),
        )
        .join(', ');
}

/**
 * The choices of *For which deadlines* in a group: every kind, and where it is now if another group has it.
 *
 * @param draft - the group being edited
 */
function kindOptions(draft: Draft): { value: string; label: string }[] {
    return page.kinds.map((kind) => {
        const holder = drafts.value.find(
            (other) =>
                other.key !== draft.key &&
                other.deadline_types.includes(kind.value),
        );

        return {
            value: kind.value,
            label: holder
                ? trans(':kind · now in «:group»', {
                      kind: kind.label,
                      group: holder.name,
                  })
                : kind.label,
        };
    });
}

/**
 * Writes the kinds of a group. A kind chosen here leaves the group that had it: it stays in one group only.
 *
 * @param draft - the group
 * @param value - the values of the field
 */
function setTypes(draft: Draft, value: unknown): void {
    const chosen = Array.isArray(value) ? (value as string[]) : [];

    if (JSON.stringify(chosen) === JSON.stringify(draft.deadline_types)) {
        return;
    }

    for (const other of drafts.value) {
        if (other.key !== draft.key) {
            other.deadline_types = other.deadline_types.filter(
                (type) => !chosen.includes(type),
            );
        }
    }

    draft.deadline_types = chosen;
}

/**
 * Whether a group is open on the screen of the phone.
 *
 * @param draft - the group
 */
function onScreen(draft: Draft): boolean {
    return !desktop.value && editing.value.includes(draft.key);
}

/**
 * Undoes the group of the open screen and closes it: a saved group goes back to the server's version, a new one is
 * removed. The other groups, what is open in them and the errors stay as they are.
 *
 * @param draft - the group of the screen
 */
function cancel(draft: Draft): void {
    const saved = page.groups.find((group) => group.id === draft.id);

    drafts.value =
        draft.id === null || saved === undefined
            ? drafts.value.filter((other) => other.key !== draft.key)
            : drafts.value.map((other) =>
                  other.key === draft.key ? toDrafts([saved])[0]! : other,
              );
    editing.value = editing.value.filter((key) => key !== draft.key);
}

/**
 * Opens or closes a group for editing.
 *
 * @param draft - the group
 */
function toggle(draft: Draft): void {
    editing.value = editing.value.includes(draft.key)
        ? editing.value.filter((key) => key !== draft.key)
        : [...editing.value, draft.key];
}

/** The boxes of the groups, each with its key in `data-group`. */
const groupBoxes = useTemplateRef<HTMLElement[]>('groupBoxes');

/**
 * Adds a group, open for editing, and scrolls the page to it: it goes at the
 * bottom, below the others. On the phone it opens on its own screen.
 */
async function addGroup(): Promise<void> {
    const key = `new-${(newGroups += 1)}`;

    drafts.value.push({
        key,
        id: null,
        name: '',
        is_default: false,
        deadline_types: [],
        rules: [],
        example: null,
    });
    editing.value.push(key);

    if (desktop.value) {
        await nextTick();
        groupBoxes.value
            ?.find((box) => box.dataset.group === key)
            ?.scrollIntoView({
                behavior: matchMedia('(prefers-reduced-motion: reduce)').matches
                    ? 'auto'
                    : 'smooth',
                block: 'start',
            });
    }
}

/**
 * Makes a group the default one: the old one stops being it.
 *
 * @param draft - the group
 */
function makeDefault(draft: Draft): void {
    for (const other of drafts.value) {
        other.is_default = other.key === draft.key;
    }
}

/**
 * Removes a group from the list; it is deleted when the list is saved.
 *
 * @param draft - the group
 */
function removeGroup(draft: Draft): void {
    drafts.value = drafts.value.filter((other) => other.key !== draft.key);
    editing.value = editing.value.filter((key) => key !== draft.key);
}

/**
 * The groups as the server saves them: what the page has and is not saved
 * (which group is open, the example) stays out.
 *
 * @returns the groups to send
 */
function payload() {
    return {
        groups: drafts.value.map((draft) => ({
            id: draft.id,
            name: draft.name,
            is_default: draft.is_default,
            deadline_types: draft.deadline_types,
            notifications: draft.rules.map((rule) => ({
                name: rule.name ?? '',
                days: rule.days,
                timing: rule.timing,
                channels: rule.channels,
                recipients: rule.recipients,
            })),
        })),
    };
}

/** While the groups have changes not saved, leaving the page asks first. */
const changes = useUnsavedChanges(payload);

/**
 * Saves every group at once.
 */
function save(): void {
    refused.value = [];
    saving.value = true;

    router.put(update.url(), payload(), {
        preserveScroll: true,
        onError: (errors) => {
            refused.value = Object.values(errors);
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

useSaveShortcut(save, () => page.can_change && !saving.value);

usePageTrail(() => [
    { label: trans('Settings'), href: settings().url },
    { label: trans('Notices') },
]);
</script>

<template>
    <Head :title="$t('Notices')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Notices')"
            :subtitle="$t('How many days before, where and to whom')"
            :back="settings().url"
            :back-label="$t('Settings')"
        >
            <template v-if="page.can_change" #actions>
                <FmActions
                    :actions="[
                        {
                            label: $t('New group'),
                            icon: Plus,
                            onSelect: addGroup,
                        },
                    ]"
                />
            </template>
        </FmTitle>

        <div
            v-for="draft in drafts"
            ref="groupBoxes"
            :key="draft.key"
            :data-group="draft.key"
            :data-testid="onScreen(draft) ? 'group-screen' : undefined"
            :class="
                onScreen(draft) &&
                'fixed inset-0 z-40 overflow-y-auto bg-canvas px-5 pt-4'
            "
        >
            <FmCard :title="draft.name || $t('New group')">
                <template v-if="page.can_change && !onScreen(draft)" #actions>
                    <FmSectionLink @click="toggle(draft)">{{
                        editing.includes(draft.key)
                            ? $t('Done editing')
                            : $t('Edit')
                    }}</FmSectionLink>
                </template>

                <template v-if="editing.includes(draft.key)">
                    <FormKit
                        v-model="draft.name"
                        type="text"
                        name="name"
                        :label="$t('Name of the group')"
                    />
                    <FormKit
                        type="taglist"
                        name="types"
                        :label="$t('For which deadlines')"
                        :help="
                            $t(
                                'Choose from the list, or start typing to search.',
                            )
                        "
                        :options="kindOptions(draft)"
                        :model-value="draft.deadline_types"
                        @input="(value: unknown) => setTypes(draft, value)"
                    />
                    <div class="mb-5 flex flex-col gap-4">
                        <FmNotificationRule
                            v-for="(rule, index) in draft.rules"
                            :key="index"
                            v-model="draft.rules[index]!"
                            :people="people"
                            @remove="draft.rules.splice(index, 1)"
                        />
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2.5"
                    >
                        <FmButton
                            variant="soft"
                            :icon="Plus"
                            @click="
                                draft.rules.push({
                                    ...NEW_RULE,
                                    recipients: ['all'],
                                })
                            "
                            >{{ $t('Add a notice') }}</FmButton
                        >
                        <div class="flex gap-2.5">
                            <FmButton
                                v-if="!draft.is_default"
                                variant="outline"
                                @click="makeDefault(draft)"
                                >{{ $t('Make default') }}</FmButton
                            >
                            <FmButton
                                v-if="!draft.is_default"
                                variant="danger"
                                :icon="Trash2"
                                @click="removeGroup(draft)"
                                >{{ $t('Delete group') }}</FmButton
                            >
                        </div>
                    </div>
                </template>
                <template v-else>
                    <p class="m-0 text-meta text-ink-soft">
                        <span
                            v-if="draft.is_default"
                            class="mr-2 rounded-full bg-action-soft px-2 py-0.5 text-caption text-action-dark"
                            >{{ $t('Default') }}</span
                        >{{ kindsText(draft) }}
                    </p>
                    <ul class="m-0 flex list-none flex-col gap-1 p-0">
                        <li v-for="(rule, index) in draft.rules" :key="index">
                            {{ ruleText(rule, people) }}
                        </li>
                    </ul>
                    <p v-if="draft.example" class="m-0 text-meta text-ink-soft">
                        {{ draft.example }}
                    </p>
                </template>
            </FmCard>
            <div
                v-if="onScreen(draft)"
                class="sticky bottom-0 -mx-5 mt-3 flex flex-col gap-2 bg-canvas px-5 pt-2 pb-4"
            >
                <FmNotice v-if="refused.length > 0" tone="danger">
                    <span
                        v-for="message in refused"
                        :key="message"
                        class="block"
                        >{{ message }}</span
                    >
                </FmNotice>
                <div class="flex gap-2.5">
                    <FmButton
                        variant="soft"
                        class="flex-1"
                        @click="cancel(draft)"
                        >{{ $t('Cancel') }}</FmButton
                    >
                    <FmButton class="flex-2" :disabled="saving" @click="save">{{
                        $t('Save')
                    }}</FmButton>
                </div>
            </div>
        </div>

        <FmNotice
            v-if="refused.length > 0 && (desktop || editing.length === 0)"
            tone="danger"
        >
            <span v-for="message in refused" :key="message" class="block">{{
                message
            }}</span>
        </FmNotice>

        <div
            v-if="page.can_change && (desktop || editing.length === 0)"
            class="flex justify-end"
        >
            <FmButton :disabled="saving" @click="save">{{
                $t('Save')
            }}</FmButton>
        </div>

        <p class="m-0 text-meta text-ink-soft">
            {{
                page.without_account.length === 0
                    ? $t(
                          'Notices go out every morning at :time. They arrive only to those with an account.',
                          { time: page.reminders_time },
                      )
                    : $tChoice(
                          '{1} Notices go out every morning at :time. They arrive only to those with an account: :names gets none.|[2,*] Notices go out every morning at :time. They arrive only to those with an account: :names get none.',
                          page.without_account.length,
                          {
                              names: page.without_account.join(', '),
                              time: page.reminders_time,
                          },
                      )
            }}
        </p>
    </div>
</template>
