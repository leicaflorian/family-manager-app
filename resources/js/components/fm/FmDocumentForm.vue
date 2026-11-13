<script setup lang="ts">
/**
 * The form of a document, as the boards *Proposta* and *Modifica documento*:
 * what it is, what kind and whose, who it is from, the dates, the status, the
 * tags, the notes and the records it links to. The same in
 * *Upload* and in *Edit a document*: the page gives the route and the values.
 * The category, the person and the organization are found by typing.
 *
 * @group forms
 */
import type { FormKitNode } from '@formkit/core';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Info } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { FillMode } from '@/composables/useReadingFill';
import { useReadingFill } from '@/composables/useReadingFill';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { useWideLayout } from '@/composables/useWideLayout';
import { categoryOptions } from '@/lib/categories';
import type {
    DocumentFormOptions,
    DocumentFormValues,
    DocumentLink,
} from '@/lib/documents';
import { linkKey, parseLinkKey, toPayload } from '@/lib/documents';
import { errors, listErrors } from '@/lib/errors';
import { withNone } from '@/lib/forms';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';
import FmActiveFilter from './FmActiveFilter.vue';
import FmButton from './FmButton.vue';
import FmCategoryOption from './FmCategoryOption.vue';
import FmConfirm from './FmConfirm.vue';
import FmIcon from './FmIcon.vue';
import FmOrganizationMark from './FmOrganizationMark.vue';
import FmTooltip from './FmTooltip.vue';

const {
    action,
    initial,
    extra = {},
    options,
    submitLabel,
    cancelLabel,
    proposal,
    fill = 'untouched',
    proposalIsStart = false,
    errors: outsideErrors,
    needed = [],
} = defineProps<{
    /**
     * Where the form is sent: a new document or a change. Without it the form has no buttons and sends
     * nothing: it only tells its values (`change`), for a page that sends many documents together.
     */
    action?: RouteDefinition<'post' | 'put'>;
    /** The values the form starts from. */
    initial: DocumentFormValues;
    /** Data sent with the fields, for example the `file_id` of a new document. */
    extra?: Record<string, number | string>;
    /** The choices of the menus. */
    options: DocumentFormOptions;
    /** The text of the button that sends; with an `action`. */
    submitLabel?: string;
    /** The text of the button that gives up, if there is one. */
    cancelLabel?: string;
    /** What the reading found, as form values: written into the fields the person has not touched. */
    proposal?: Partial<DocumentFormValues>;
    /** Which fields the proposal may write: the ones not touched, or only the empty ones. */
    fill?: FillMode;
    /** The form opens with the proposal: what it fills in is the start, not a change not saved (a page that shows the reading as already chosen). */
    proposalIsStart?: boolean;
    /** Errors found outside the form (a page that sent many documents), by field: `title`, `tags.0`… */
    errors?: Record<string, string>;
    /** The empty fields a box of *What I do too* needs (`date`, `person_id`, `organization_id`): each says so under it. */
    needed?: string[];
}>();

const emit = defineEmits<{
    /** The person gave up. */
    cancel: [];
    /** The values of the form, now and every time they change (the person writes, the reading fills). */
    change: [values: DocumentFormValues];
}>();

defineSlots<{
    /** On the left of the buttons, for example *Delete the document*. */
    start?: () => unknown;
}>();

const form = useForm<DocumentFormValues>({
    ...initial,
    links: [...initial.links],
});

watch(
    () => form.data(),
    (values) => emit('change', values),
    {
        deep: true,
        immediate: true,
    },
);

/** While the form has changes not saved, leaving the page asks first. */
const changes = useUnsavedChanges(() => form.data());

// The errors a page gives replace the ones shown
watch(
    () => outsideErrors,
    (received) => {
        form.clearErrors();

        if (received && Object.keys(received).length > 0) {
            form.setError(received);
        }
    },
    { immediate: true },
);

const reading = useReadingFill(form, initial, fill);

// The proposal may arrive after the form opened (the reading ends later)
watch(
    () => proposal,
    (values) => {
        if (values) {
            // Only a form the person has not touched: what they wrote stays a change to lose
            const untouched = proposalIsStart && !changes.dirty.value;

            reading.apply(values);

            if (untouched) {
                changes.reset();
            }
        }
    },
    { immediate: true },
);

// The categories keep their name and depth, which the list of the menu shows
const categories = computed(() => [
    ...withNone([]),
    ...categoryOptions(options.categories),
]);
const people = computed(() => withNone(options.people));

/** The classes of the line under a field needed below: the warning colour in place of the soft one. */
const neededClass = cn('$reset mt-1 text-meta text-warning');

/**
 * The line under a field when a box of *What I do too* needs it.
 *
 * @param field - the column
 * @returns the line, undefined when the field is not needed
 */
function neededHelp(field: string): string | undefined {
    return needed.includes(field)
        ? trans('Needed by What I do too.')
        : undefined;
}
const organizations = computed(() => withNone(options.organizations));

/** The organization chosen in *Who it is from*, for its mark in the field. */
const organization = computed(() =>
    options.organizations.find(
        (choice) => choice.value === String(form.organization_id ?? ''),
    ),
);

/** The tablet and the computer explain *Link it to* with the ⓘ by its label; the phone, under the field. */
const wide = useWideLayout();

/** What linking a document means, under *Link it to* or in the bubble of its ⓘ. */
const linkHint = computed(() =>
    trans(
        'Link the document to a card, like the electricity, the car or a policy: so you find it there too.',
    ),
);

/** The name of each record that can be linked, by `linkKey()`. */
const linkLabels = computed(
    () =>
        new Map<string, string>(
            options.links.flatMap((group) =>
                group.options.map(
                    (option) =>
                        [
                            linkKey({
                                type: group.type,
                                id: Number(option.value),
                            }),
                            option.label,
                        ] as const,
                ),
            ),
        ),
);

/** The records not linked yet, grouped by type as the menu shows them. */
const linkChoices = computed(() => {
    const chosen = new Set(form.links.map(linkKey));

    return options.links
        .map((group) => ({
            group: group.label,
            options: group.options
                .map((option) => ({
                    value: linkKey({
                        type: group.type,
                        id: Number(option.value),
                    }),
                    label: option.label,
                }))
                .filter((option) => !chosen.has(option.value)),
        }))
        .filter((group) => group.options.length > 0);
});

/** The value of the menu of the links: a choice adds the record, and the menu goes back to empty. */
const linkChoice = ref('');

/** The field of the menu of the links, emptied through FormKit after a choice. */
const linkNode = ref<FormKitNode>();

watch(linkChoice, (key) => {
    const link = parseLinkKey(key);

    if (link) {
        form.links.push(link);
        // Setting `linkChoice` back to '' is not enough: '' → key → '' happens in one Vue
        // flush, so the `modelValue` prop of the field never changes and FormKit keeps the
        // record as its value (its name comes back in the box and it stays ticked in the list).
        // The field is emptied through its node, which also empties the model.
        linkNode.value?.input('', false);
    }
});

/** The errors of the links, all under the menu. */
const linkErrors = computed(() => listErrors(form.errors, 'links'));

/** The tags already used in the family that the document does not have yet. */
const tagChoices = computed(() =>
    options.tags.filter((tag) => !form.tags.includes(tag)),
);

/** The errors of the tags (`tags.0`, …), all under the field. */
const tagErrors = computed(() => listErrors(form.errors, 'tags'));

/** The links the document had when the form opened. */
const savedLinks = new Set(initial.links.map(linkKey));

/** The saved link waiting for the person to confirm its removal. */
const unlinking = ref<DocumentLink | null>(null);

/** The panel that asks before removing a saved link, open while there is one. */
const confirmingUnlink = computed({
    get: (): boolean => unlinking.value !== null,
    set: (open: boolean): void => {
        if (!open) {
            unlinking.value = null;
        }
    },
});

/**
 * The name of a record that can be linked, or its key if it is not among the
 * choices.
 *
 * @param link - the link
 * @returns the name to show
 */
function linkLabel(link: DocumentLink): string {
    return linkLabels.value.get(linkKey(link)) ?? linkKey(link);
}

/**
 * Removes a record from the links.
 *
 * @param link - the link to remove
 */
function unlink(link: DocumentLink): void {
    form.links = form.links.filter((other) => linkKey(other) !== linkKey(link));
}

/**
 * The × of a link: a link just added goes at once, a saved one only after
 * asking, because taking a document away from a record by mistake is easy.
 *
 * @param link - the link whose × was pressed
 */
function askToUnlink(link: DocumentLink): void {
    if (savedLinks.has(linkKey(link))) {
        unlinking.value = link;
    } else {
        unlink(link);
    }
}

/** Removes the saved link, once the person confirmed. */
function confirmUnlink(): void {
    if (unlinking.value) {
        unlink(unlinking.value);
    }
    unlinking.value = null;
}

/** Sends the document, if it has its title. */
function save(): void {
    if (!action) {
        return;
    }

    form.clearErrors();

    if (form.title.trim() === '') {
        form.setError('title', trans('Write what the document is.'));

        return;
    }

    form.transform((values) => ({ ...extra, ...toPayload(values) })).submit(
        action,
    );
}

useSaveShortcut(save, () => !form.processing);
</script>

<template>
    <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
        <FormKit
            v-model="form.title"
            type="text"
            :label="$t('What it is')"
            name="title"
            :errors="errors(form.errors.title)"
        />
        <div class="grid grid-cols-2 gap-3">
            <FormKit
                v-model="form.category_id"
                type="autocomplete"
                :label="$t('What kind it is')"
                name="category_id"
                :options="categories"
                open-on-click
                :errors="errors(form.errors.category_id)"
            >
                <template #option="{ option }">
                    <FmCategoryOption :option="option" />
                </template>
            </FormKit>
            <FormKit
                v-model="form.person_id"
                type="autocomplete"
                :label="$t('Whose it is')"
                name="person_id"
                :help="neededHelp('person_id')"
                :help-class="neededClass"
                :options="people"
                open-on-click
                :errors="errors(form.errors.person_id)"
            />
        </div>
        <FormKit
            v-model="form.organization_id"
            type="autocomplete"
            open-on-click
            :label="$t('Who it is from')"
            name="organization_id"
            :help="neededHelp('organization_id')"
            :help-class="neededClass"
            :options="organizations"
            :errors="errors(form.errors.organization_id)"
        >
            <template v-if="organization" #prefix>
                <FmOrganizationMark
                    :types="organization.types"
                    :logo="organization.logo"
                    :name="organization.label"
                    :icon-size="14"
                    class="mr-2 size-6 rounded-tag bg-canvas"
                />
            </template>
        </FormKit>
        <div class="grid grid-cols-2 gap-3">
            <FormKit
                v-model="form.date"
                type="datepicker"
                :label="$t('Date')"
                name="date"
                :help="neededHelp('date')"
                :help-class="neededClass"
                :errors="errors(form.errors.date)"
            />
            <FormKit
                v-model="form.expiry_date"
                type="datepicker"
                :label="$t('Expires on')"
                name="expiry_date"
                :errors="errors(form.errors.expiry_date)"
            />
        </div>
        <FormKit
            v-model="form.status"
            type="togglebuttons"
            enforced
            :label="$t('Status')"
            name="status"
            :options="options.statuses"
            :errors="errors(form.errors.status)"
        />
        <!-- The chips of the links sit just under the field, as the boards Proposta -->
        <div class="mb-3.5 flex flex-col gap-2">
            <FormKit
                v-model="linkChoice"
                type="autocomplete"
                open-on-click
                :label="$t('Link it to')"
                :placeholder="$t('Choose a record')"
                name="link"
                outer-class="$reset formkit-disabled:opacity-50"
                :options="linkChoices"
                @node="linkNode = $event"
                :help="
                    options.links.length === 0
                        ? $t(
                              'The records of the areas (homes, cars, jobs…) will appear here.',
                          )
                        : wide
                          ? undefined
                          : linkHint
                "
                :disabled="options.links.length === 0"
                :errors="linkErrors"
            >
                <template v-if="wide" #label="{ id }">
                    <div class="mb-1.5 flex items-center gap-1.5">
                        <label :for="id" class="text-row text-ink">{{
                            $t('Link it to')
                        }}</label>
                        <FmTooltip :text="linkHint" side="right">
                            <button
                                type="button"
                                :aria-label="$t('What linking it means')"
                                class="flex size-7 cursor-pointer items-center justify-center rounded-full text-ink-soft"
                            >
                                <FmIcon :icon="Info" :size="18" />
                            </button>
                        </FmTooltip>
                    </div>
                </template>
            </FormKit>
            <div v-if="form.links.length > 0" class="flex flex-wrap gap-2">
                <FmActiveFilter
                    v-for="link in form.links"
                    :key="linkKey(link)"
                    :label="linkLabel(link)"
                    :remove-label="
                        $t('Remove the link :label', { label: linkLabel(link) })
                    "
                    @remove="askToUnlink(link)"
                />
            </div>
        </div>
        <FmConfirm
            v-model:open="confirmingUnlink"
            :question="
                $t('Remove the link to :label?', {
                    label: unlinking ? linkLabel(unlinking) : '',
                })
            "
            :text="
                $t(
                    'The document will no longer be linked to it, once you save.',
                )
            "
            :confirm-label="$t('Remove the link')"
            @confirm="confirmUnlink"
        />
        <FormKit
            v-model="form.notes"
            type="textarea"
            :label="$t('Notes')"
            name="notes"
            :errors="errors(form.errors.notes)"
        />
        <FormKit
            v-model="form.tags"
            type="taglist"
            :label="$t('Tags')"
            name="tags"
            :options="tagChoices"
            show-empty-message
            :empty-message="$t('Press Enter to add the tag')"
            allow-new-values
            :placeholder="$t('Add…')"
            :help="$t('Write a tag and press Enter, for example: salary')"
            :errors="tagErrors"
        />
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2.5">
            <slot name="start" />
            <div class="ml-auto flex flex-wrap gap-2.5">
                <FmButton
                    v-if="reading.canRestore.value"
                    variant="text"
                    type="button"
                    @click="reading.restore()"
                >
                    {{ $t('Restore the reading') }}
                </FmButton>
                <FmButton
                    v-if="cancelLabel && action"
                    variant="text"
                    data-discard-changes
                    @click="$emit('cancel')"
                >
                    {{ cancelLabel }}
                </FmButton>
                <FmButton
                    v-if="action"
                    type="submit"
                    :disabled="form.processing"
                >
                    {{ submitLabel }}
                </FmButton>
            </div>
        </div>
    </form>
</template>
