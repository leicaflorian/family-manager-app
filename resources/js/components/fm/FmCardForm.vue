<script setup lang="ts">
/**
 * The form of a card, built from the description the server gives
 * (`CardFieldData`): each field becomes the FormKit Pro input of its kind, and
 * a field shows only when its `show_when` holds, for example the vehicle only
 * for a car policy, and its label can change with `label_when`, for example
 * the POD code of electricity that is a PDR code for gas. A hidden field is
 * sent empty. For a loan the page also gives `loanFigures`: under the instalment
 * the form shows the total to pay and the cost, which it asks the server for
 * (a partial reload) 300 ms after the amounts or the start stop changing. The
 * `after` slot, above the buttons, gets the live values for what depends on
 * them. A type whose name is composed from its fields (`titleParts`: a
 * utility, an employment, an enrollment) shows that name at the top, read-only,
 * as it will be saved. Beside every organization menu, to whoever may add one,
 * a «+» opens *New organization* (`FmQuickOrganizationSheet`) and chooses the
 * organization added, keeping what was written. The same form creates and
 * edits every type of card: the page gives the fields and the values, and gets
 * them back with `submit`.
 *
 * @group forms
 */
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { composeTitle, fieldInput, isMenuField } from '@/lib/cards';
import { errors } from '@/lib/errors';
import { isEmpty } from '@/lib/forms';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmLoanFigures from './FmLoanFigures.vue';
import FmQuickOrganizationSheet from './FmQuickOrganizationSheet.vue';
import type { QuickOrganization } from './FmQuickOrganizationSheet.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';

const {
    fields,
    values,
    serverErrors = {},
    processing = false,
    loanFigures = undefined,
    titleParts = [],
    canCreateOrganization = false,
    organizationTypes = [],
} = defineProps<{
    /** The description of the fields, in the order they are shown. */
    fields: App.Data.CardFieldData[];
    /** The values the form starts from, by column. */
    values: Record<string, unknown>;
    /** The errors of the server, by column. */
    serverErrors?: Partial<Record<string, string>>;
    /** The form is being sent: the button waits. */
    processing?: boolean;
    /** The total to pay and the cost of a loan: null until the server answers, left out for any other card. */
    loanFigures?: App.Data.LoanFiguresData | null;
    /** How the type composes its name from the fields (each part a list of columns, the first with a value wins); empty for a type with its own name. */
    titleParts?: string[][];
    /** Whether the person may add an organization: the «+» beside the organization menus. */
    canCreateOrganization?: boolean;
    /** Every type of organization, for *New organization*. */
    organizationTypes?: App.Data.OptionData[];
}>();

const emit = defineEmits<{
    /** The person saved: the value of every field, the hidden ones `null`. */
    submit: [values: Record<string, unknown>];
}>();

defineSlots<{
    /** On the left of the *Save* button, for example *Delete*. */
    start?: () => unknown;
    /** Above the buttons, for what follows the type chosen, for example the old policy to cancel; gets the live values of the form. */
    after?: (props: { values: Record<string, unknown> }) => unknown;
}>();

/** The kinds whose value is the id of a record and whose options have text values. */
const references = new Set(['person', 'organization', 'card']);

/**
 * The value a field starts from: an id is text like the values of its options
 * (FormKit compares them strictly), an untouched switch is off.
 *
 * @param field - the field
 * @returns the starting value
 */
function startValue(field: App.Data.CardFieldData): unknown {
    const value = values[field.column] ?? null;

    if (field.kind === 'toggle') {
        return value ?? false;
    }

    return references.has(field.kind) && value !== null ? String(value) : value;
}

const data = reactive<Record<string, unknown>>(
    Object.fromEntries(
        fields.map((field) => [field.column, startValue(field)]),
    ),
);

/** While the form has changes not saved, leaving the page asks first. */
useUnsavedChanges(() => data);

/** The columns of a loan that its total to pay and cost follow. */
const LOAN_COLUMNS = [
    'total_amount',
    'installments_count',
    'installment_amount',
    'final_installment_amount',
    'start_date',
    'end_date',
    'status',
];

/** The column of the field the figures of a loan are written under. */
const FIGURES_AFTER = 'installment_amount';

/** The wait, in ms, after the last change before the figures are asked for. */
const FIGURES_DELAY = 300;

/** The pending request for the figures, if the person changed something not long ago. */
let pending: ReturnType<typeof setTimeout> | undefined;

/** Asks the server for the figures of the values written now; the address stays that of the page. */
function askFigures(): void {
    router.reload({
        only: ['loanFigures'],
        data: {
            figures: Object.fromEntries(
                LOAN_COLUMNS.filter((column) => !isEmpty(data[column])).map(
                    (column) => [column, data[column]],
                ),
            ),
        } as RequestPayload,
        preserveUrl: true,
    });
}

watch(
    () => LOAN_COLUMNS.map((column) => data[column]),
    () => {
        clearTimeout(pending);

        if (loanFigures !== undefined) {
            pending = setTimeout(askFigures, FIGURES_DELAY);
        }
    },
);

// A request still waiting must not go out once the form is gone
onBeforeUnmount(() => clearTimeout(pending));

/** What the person tried to save with something missing: the errors found. */
const missing = ref<Record<string, string>>({});

/**
 * Whether a field is shown with the values now: every condition of its
 * `show_when` must hold.
 *
 * @param field - the field
 * @returns true if the field is shown
 */
function isShown(field: App.Data.CardFieldData): boolean {
    return Object.entries(field.show_when).every(([column, allowed]) =>
        allowed.includes(String(data[column])),
    );
}

/**
 * The label of a field with the values now: the one of `label_when` they ask
 * for, otherwise the usual one.
 *
 * @param field - the field
 * @returns the label
 */
function labelOf(field: App.Data.CardFieldData): string {
    for (const [column, labels] of Object.entries(field.label_when)) {
        const label = labels[String(data[column])];

        if (label !== undefined) {
            return label;
        }
    }

    return field.label;
}

/** The fields shown now. */
const shown = computed(() => fields.filter(isShown));

/**
 * The options of a field as its input wants them: a menu that is not required
 * starts with the empty choice.
 *
 * @param field - the field
 * @returns the options
 */
function optionsOf(
    field: App.Data.CardFieldData,
): { value: string; label: string }[] {
    const options = field.options.map(({ value, label }) => ({ value, label }));

    return isMenuField(field.kind) && !field.required
        ? [{ value: '', label: trans('None') }, ...options]
        : options;
}

/**
 * The values as they are sent: a hidden field is empty, a switch is on or off.
 *
 * @returns the value of every field, by column
 */
function sent(): Record<string, unknown> {
    return Object.fromEntries(
        fields.map((field) => [
            field.column,
            isShown(field) &&
            (field.kind === 'toggle' || !isEmpty(data[field.column]))
                ? data[field.column]
                : null,
        ]),
    );
}

/** The name the card will have with the values now, empty while nothing composes it. */
const title = computed(() => composeTitle(titleParts, fields, sent()));

/**
 * Whether a field has the «+» that adds an organization beside it.
 *
 * @param field - the field
 * @returns true for an organization menu, when the person may add one
 */
function adds(field: App.Data.CardFieldData): boolean {
    return canCreateOrganization && field.kind === 'organization';
}

/** The column of the organization menu whose «+» opened the panel. */
const adding = ref<string | null>(null);

/** *New organization* is open. */
const addingOpen = ref(false);

/**
 * Opens *New organization* for a menu.
 *
 * @param column - the column of the menu
 */
function addOrganization(column: string): void {
    adding.value = column;
    addingOpen.value = true;
}

/**
 * Chooses the organization just added in the menu that asked for it: the page
 * has already come back with it among the options.
 *
 * @param organization - the organization added
 */
function chooseAdded(organization: QuickOrganization): void {
    if (adding.value !== null) {
        data[adding.value] = String(organization.id);
    }
}

/** Sends the values, if every shown field that is required has one. */
function save(): void {
    missing.value = Object.fromEntries(
        shown.value
            .filter((field) => field.required && isEmpty(data[field.column]))
            .map((field) => [field.column, trans('This field is required.')]),
    );

    if (Object.keys(missing.value).length > 0) {
        return;
    }

    emit('submit', sent());
}

useSaveShortcut(save, () => !processing);
</script>

<template>
    <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
        <FormKit
            v-if="titleParts.length > 0"
            type="text"
            name="composed_title"
            :label="$t('Title')"
            :model-value="title"
            :placeholder="$t('It is made from the fields below')"
            readonly
            inner-class="$reset flex h-control items-center rounded-control border-[1.5px] border-edge-soft bg-segment px-3.5"
        />
        <template v-for="field in shown" :key="field.column">
            <div class="flex items-start gap-2">
                <FormKit
                    v-model="data[field.column]"
                    :type="fieldInput(field.kind)"
                    :name="field.column"
                    :label="$t(labelOf(field))"
                    :options="
                        field.kind === 'choice' || isMenuField(field.kind)
                            ? optionsOf(field)
                            : undefined
                    "
                    :open-on-click="isMenuField(field.kind) ? true : undefined"
                    :enforced="
                        field.kind === 'choice' && field.required
                            ? true
                            : undefined
                    "
                    :errors="
                        errors(
                            missing[field.column] ?? serverErrors[field.column],
                        )
                    "
                    outer-class="min-w-0 flex-1"
                />
                <div v-if="adds(field)" class="flex shrink-0 flex-col">
                    <!-- As tall as the label of the field, so the «+» sits beside the menu -->
                    <span
                        aria-hidden="true"
                        class="invisible mb-1.5 block text-row"
                        >&nbsp;</span
                    >
                    <button
                        type="button"
                        :aria-label="$t('New organization')"
                        :title="$t('New organization')"
                        class="flex size-control-md cursor-pointer items-center justify-center rounded-md border border-edge-soft bg-card text-action transition-colors hover:bg-action-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
                        @click="addOrganization(field.column)"
                    >
                        <FmIcon :icon="Plus" :size="20" />
                    </button>
                </div>
            </div>
            <FmLoanFigures
                v-if="
                    loanFigures !== undefined && field.column === FIGURES_AFTER
                "
                :figures="loanFigures"
            />
        </template>
        <slot name="after" :values="data" />
        <FmQuickOrganizationSheet
            v-if="canCreateOrganization"
            v-model:open="addingOpen"
            :types="organizationTypes"
            @created="chooseAdded"
        />
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2.5">
            <slot name="start" />
            <FmButton type="submit" class="ml-auto" :disabled="processing">
                {{ $t('Save') }}
            </FmButton>
        </div>
    </form>
</template>
