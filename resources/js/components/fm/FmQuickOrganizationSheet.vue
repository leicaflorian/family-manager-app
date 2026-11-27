<script setup lang="ts">
/**
 * *New organization* from the card form, the «+» beside an organization menu:
 * the panel that adds one with just the name and its types, as the board
 * *Modifica-nuovo-ente*. The page stays as it is (the form of the card keeps
 * what was written); the server answers with the new organization as the
 * `organization` flash, which the panel gives with `created` before closing.
 * Phone, email, website and logo are added later from the organization page.
 * The errors come in the `organization` bag, so they stay in the panel.
 *
 * @group forms
 */
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { errors, listErrors } from '@/lib/errors';
import { organizationTypeChips } from '@/lib/family';
import { quick } from '@/routes/organizations';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

/** The organization the server added. */
export interface QuickOrganization {
    /** Its id. */
    id: number;
    /** Its name. */
    name: string;
}

const { types } = defineProps<{
    /** Every type of organization, as the organization form offers them. */
    types: App.Data.OptionData[];
}>();

const emit = defineEmits<{
    /** The organization was added: its id and name. */
    created: [organization: QuickOrganization];
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

/** The name written. */
const name = ref('');

/** The types chosen. */
const chosen = ref<string[]>([]);

/** The messages of the server, by field (`name`, `types.0`, …). */
const problems = ref<Record<string, string>>({});

/** The organization is being sent. */
const processing = ref(false);

/** Asks before closing the panel with something written and not saved. */
const changes = useUnsavedChanges(
    () => ({ name: name.value, types: chosen.value }),
    { active: () => open.value },
);

// Opened again, the panel starts empty
watch(open, (isOpen) => {
    if (isOpen) {
        name.value = '';
        chosen.value = [];
        problems.value = {};
        changes.reset();
    }
});

/**
 * Whether the flash of the server holds the organization added.
 *
 * @param value - the `organization` of the flash
 * @returns true for an id and a name
 */
function isOrganization(value: unknown): value is QuickOrganization {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as Record<string, unknown>).id === 'number' &&
        typeof (value as Record<string, unknown>).name === 'string'
    );
}

/** Sends the organization; the page stays where it is, with its state. */
function save(): void {
    processing.value = true;
    problems.value = {};

    router.post(
        quick.url(),
        { name: name.value, types: chosen.value },
        {
            errorBag: 'organization',
            preserveState: true,
            preserveScroll: true,
            preserveUrl: true,
            onError: (messages) => {
                problems.value = messages;
            },
            onFlash: (flash) => {
                const created: unknown = (flash as Record<string, unknown>)
                    .organization;

                if (isOrganization(created)) {
                    emit('created', created);
                    open.value = false;
                }
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :before-close="changes.confirmClose"
        :title="$t('New organization')"
    >
        <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
            <FormKit
                v-model="name"
                type="text"
                :label="$t('Name')"
                name="name"
                autocomplete="off"
                outer-class="mb-0"
                :errors="errors(problems.name)"
            />
            <FormKit
                v-model="chosen"
                type="togglebuttons"
                multiple
                :label="$t('What kind it is')"
                name="types"
                :options="types"
                outer-class="mb-0"
                v-bind="organizationTypeChips"
                :errors="listErrors(problems, 'types')"
            />
            <p class="m-0 text-body-sm text-ink-soft">
                {{
                    $t(
                        'Phone, email, website and logo you add later, from the organization page.',
                    )
                }}
            </p>
            <div class="flex items-center justify-between gap-2.5">
                <FmButton variant="text" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton type="submit" :disabled="processing">{{
                    $t('Save')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
