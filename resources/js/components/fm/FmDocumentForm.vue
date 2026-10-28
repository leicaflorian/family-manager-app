<script setup lang="ts">
/**
 * The form of a document, as the boards *Proposta* and *Modifica documento*:
 * what it is, what kind and whose, who it is from, the dates, the status, the
 * tags, the notes and the records it links to. The same in
 * *Upload* and in *Edit a document*: the page gives the route and the values.
 */
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { categoryOptions } from '@/lib/categories';
import type {
    DocumentFormOptions,
    DocumentFormValues,
    DocumentLink,
} from '@/lib/documents';
import { linkKey, parseLinkKey, toPayload } from '@/lib/documents';
import { errors, listErrors } from '@/lib/errors';
import type { RouteDefinition } from '@/wayfinder';
import FmActiveFilter from './FmActiveFilter.vue';
import FmButton from './FmButton.vue';
import FmConfirm from './FmConfirm.vue';

const {
    action,
    initial,
    extra = {},
    options,
    submitLabel,
    cancelLabel,
} = defineProps<{
    /** Where the form is sent: a new document or a change. */
    action: RouteDefinition<'post' | 'put'>;
    /** The values the form starts from. */
    initial: DocumentFormValues;
    /** Data sent with the fields, for example the `file_id` of a new document. */
    extra?: Record<string, number | string>;
    /** The choices of the menus. */
    options: DocumentFormOptions;
    /** The text of the button that sends. */
    submitLabel: string;
    /** The text of the button that gives up, if there is one. */
    cancelLabel?: string;
}>();

defineEmits<{
    /** The person gave up. */
    cancel: [];
}>();

defineSlots<{
    /** On the left of the buttons, for example *Delete the document*. */
    start?: () => unknown;
}>();

const form = useForm<DocumentFormValues>({
    ...initial,
    links: [...initial.links],
});

/** The empty choice at the top of a menu. */
const none = computed(() => ({ value: '', label: trans('None') }));

const categories = computed(() => [
    none.value,
    ...categoryOptions(options.categories),
]);
const people = computed(() => [none.value, ...options.people]);
const organizations = computed(() => [none.value, ...options.organizations]);

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

    return [
        { value: '', label: trans('Choose a record') },
        ...options.links
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
            .filter((group) => group.options.length > 0),
    ];
});

/** The value of the menu of the links: a choice adds the record, and the menu goes back to empty. */
const linkChoice = ref('');

watch(linkChoice, (key) => {
    const link = parseLinkKey(key);

    if (link) {
        form.links.push(link);
        linkChoice.value = '';
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
                type="dropdown"
                :label="$t('What kind it is')"
                name="category_id"
                :options="categories"
                :errors="errors(form.errors.category_id)"
            />
            <FormKit
                v-model="form.person_id"
                type="dropdown"
                :label="$t('Whose it is')"
                name="person_id"
                :options="people"
                :errors="errors(form.errors.person_id)"
            />
            <FormKit
                v-model="form.organization_id"
                type="dropdown"
                :label="$t('Who it is from')"
                name="organization_id"
                :options="organizations"
                :errors="errors(form.errors.organization_id)"
            />
            <FormKit
                v-model="form.date"
                type="datepicker"
                :label="$t('Date')"
                name="date"
                :errors="errors(form.errors.date)"
            />
            <FormKit
                v-model="form.expiry_date"
                type="datepicker"
                :label="$t('Expires on')"
                name="expiry_date"
                :errors="errors(form.errors.expiry_date)"
            />
            <FormKit
                v-model="form.status"
                type="togglebuttons"
                enforced
                :label="$t('Status')"
                name="status"
                :options="options.statuses"
                :errors="errors(form.errors.status)"
            />
        </div>
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
        <FormKit
            v-model="form.notes"
            type="textarea"
            :label="$t('Notes')"
            name="notes"
            :errors="errors(form.errors.notes)"
        />
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
            v-model="linkChoice"
            type="dropdown"
            :label="$t('Link it to')"
            name="link"
            :options="linkChoices"
            :help="
                options.links.length === 0
                    ? $t(
                          'The records of the areas (homes, cars, jobs…) will appear here.',
                      )
                    : undefined
            "
            :disabled="options.links.length === 0"
            :errors="linkErrors"
        />
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2.5">
            <slot name="start" />
            <div class="ml-auto flex flex-wrap gap-2.5">
                <FmButton
                    v-if="cancelLabel"
                    variant="text"
                    @click="$emit('cancel')"
                >
                    {{ cancelLabel }}
                </FmButton>
                <FmButton type="submit" :disabled="form.processing">
                    {{ submitLabel }}
                </FmButton>
            </div>
        </div>
    </form>
</template>
