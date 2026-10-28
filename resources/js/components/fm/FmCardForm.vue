<script setup lang="ts">
/**
 * The form of a card, built from the description the server gives
 * (`CardFieldData`): each field becomes the FormKit Pro input of its kind, and
 * a field shows only when its `show_when` holds, for example the vehicle only
 * for a car policy, and its label can change with `label_when`, for example
 * the POD code of electricity that is a PDR code for gas. A hidden field is
 * sent empty. The same form creates and
 * edits every type of card: the page gives the fields and the values, and gets
 * them back with `submit`.
 */
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { errors } from '@/lib/errors';
import FmButton from './FmButton.vue';

const {
    fields,
    values,
    serverErrors = {},
    processing = false,
} = defineProps<{
    /** The description of the fields, in the order they are shown. */
    fields: App.Data.CardFieldData[];
    /** The values the form starts from, by column. */
    values: Record<string, unknown>;
    /** The errors of the server, by column. */
    serverErrors?: Partial<Record<string, string>>;
    /** The form is being sent: the button waits. */
    processing?: boolean;
}>();

const emit = defineEmits<{
    /** The person saved: the value of every field, the hidden ones `null`. */
    submit: [values: Record<string, unknown>];
}>();

defineSlots<{
    /** On the left of the *Save* button, for example *Delete*. */
    start?: () => unknown;
}>();

/** The FormKit input of each kind of field. */
const inputs: Record<string, string> = {
    text: 'text',
    textarea: 'textarea',
    number: 'number',
    currency: 'currency',
    date: 'datepicker',
    toggle: 'toggle',
    choice: 'togglebuttons',
    dropdown: 'autocomplete',
    person: 'autocomplete',
    organization: 'autocomplete',
    card: 'autocomplete',
};

/** The kinds whose input is a menu that can be left empty. */
const menus = new Set(['dropdown', 'person', 'organization', 'card']);

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

    return menus.has(field.kind) && !field.required
        ? [{ value: '', label: trans('None') }, ...options]
        : options;
}

/**
 * Whether a value is empty: nothing written or chosen. Zero and "no" are values.
 *
 * @param value - the value of a field
 * @returns true if it is empty
 */
function isEmpty(value: unknown): boolean {
    return value === null || value === undefined || value === '';
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

    emit(
        'submit',
        Object.fromEntries(
            fields.map((field) => [
                field.column,
                isShown(field) &&
                (field.kind === 'toggle' || !isEmpty(data[field.column]))
                    ? data[field.column]
                    : null,
            ]),
        ),
    );
}

useSaveShortcut(save, () => !processing);
</script>

<template>
    <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
        <template v-for="field in shown" :key="field.column">
            <FormKit
                v-model="data[field.column]"
                :type="inputs[field.kind] ?? 'text'"
                :name="field.column"
                :label="$t(labelOf(field))"
                :options="
                    field.kind === 'choice' || menus.has(field.kind)
                        ? optionsOf(field)
                        : undefined
                "
                :open-on-click="menus.has(field.kind) ? true : undefined"
                :enforced="
                    field.kind === 'choice' && field.required ? true : undefined
                "
                :errors="
                    errors(missing[field.column] ?? serverErrors[field.column])
                "
            />
        </template>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2.5">
            <slot name="start" />
            <FmButton type="submit" class="ml-auto" :disabled="processing">
                {{ $t('Save') }}
            </FmButton>
        </div>
    </form>
</template>
