<script setup lang="ts">
/**
 * The form of a document, as the board *Proposta*: what it is, what kind,
 * who it is from and whose, the dates and the records it links to. The same in
 * *Upload* and in *Edit a document*: the page gives the route and the values.
 */
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
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

/** The errors of the tags (`tags.0`, …), all under the field. */
const tagErrors = computed(() => listErrors(form.errors, 'tags'));

/**
 * Removes a record from the links.
 *
 * @param link - the link to remove
 */
function unlink(link: DocumentLink): void {
    form.links = form.links.filter((other) => linkKey(other) !== linkKey(link));
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
                type="select"
                :label="$t('What kind it is')"
                name="category_id"
                :options="categories"
                :errors="errors(form.errors.category_id)"
            />
            <FormKit
                v-model="form.organization_id"
                type="select"
                :label="$t('Who it is from')"
                name="organization_id"
                :options="organizations"
                :errors="errors(form.errors.organization_id)"
            />
        </div>
        <FormKit
            v-model="form.person_id"
            type="select"
            :label="$t('Whose it is')"
            name="person_id"
            :options="people"
            :errors="errors(form.errors.person_id)"
        />
        <div class="grid grid-cols-2 gap-3">
            <FormKit
                v-model="form.date"
                type="date"
                :label="$t('Date')"
                name="date"
                :errors="errors(form.errors.date)"
            />
            <FormKit
                v-model="form.expiry_date"
                type="date"
                :label="$t('Expires on')"
                name="expiry_date"
                :errors="errors(form.errors.expiry_date)"
            />
        </div>
        <FormKit
            v-model="linkChoice"
            type="select"
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
        <div v-if="form.links.length > 0" class="flex flex-wrap gap-2">
            <FmActiveFilter
                v-for="link in form.links"
                :key="linkKey(link)"
                :label="linkLabels.get(linkKey(link)) ?? linkKey(link)"
                :remove-label="
                    $t('Remove the link :label', {
                        label: linkLabels.get(linkKey(link)) ?? linkKey(link),
                    })
                "
                @remove="unlink(link)"
            />
        </div>
        <FormKit
            v-model="form.status"
            type="select"
            :label="$t('Status')"
            name="status"
            :options="options.statuses"
            :errors="errors(form.errors.status)"
        />
        <FormKit
            v-model="form.tags"
            type="text"
            :label="$t('Tags')"
            name="tags"
            :help="$t('Separated by commas, for example: salary, 2026')"
            :errors="tagErrors"
        />
        <FormKit
            v-model="form.notes"
            type="textarea"
            :label="$t('Notes')"
            name="notes"
            :errors="errors(form.errors.notes)"
        />
        <div class="mt-1.5 flex flex-wrap justify-end gap-2.5">
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
    </form>
</template>
