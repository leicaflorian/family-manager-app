<script setup lang="ts">
/**
 * The panel of a movement, as the board *Pagamenti-pc* draws it: the title,
 * when it was paid (or received) and how much, the commission and the account,
 * the category and (for an expense) the card of its type, the organization,
 * the holder, the planned expense or income it
 * settles (and which of its due dates), with *Delete*, *Cancel* and *Save*.
 * The fields sit in pairs as the boards *Movimento* and *Entrata* draw them.
 * The documents are chosen on the page (`FmCardFiles`): the panel holds the
 * files, the documents to link and to unlink as models and sends them with
 * the movement. Without a movement it is the form of a new
 * expense or income, which starts with the choice between the two. Choosing a
 * planned expense or income, or a card (also the one *Add expense* of a card
 * starts with), fills the fields still empty from it. On the computer the
 * page has the title, so the form has no heading of its own there.
 *
 * @group panels
 */
import { router } from '@inertiajs/vue3';
import { createReusableTemplate } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, toRef, watch } from 'vue';
import { useWideLayout } from '@/composables/useWideLayout';
import { useCardChoice } from '@/composables/useCardChoice';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import type { MovementFormOptions, MovementStart } from '@/lib/budget';
import { errors, listErrors } from '@/lib/errors';
import {
    formatAmount,
    isEmpty,
    parseAmount,
    withMethod,
    withFee,
    withNone,
    withoutFee,
} from '@/lib/forms';
import { cn } from '@/lib/utils';
import { destroy, store, update } from '@/routes/budget/movements';
import FmButton from './FmButton.vue';
import FmDeleteButton from './FmDeleteButton.vue';

/** The organization field, beside the account for an income and beside the category for an expense. */
const [DefineOrganization, ReuseOrganization] = createReusableTemplate();

const {
    movement,
    options,
    start,
    occurrences = [],
    back = null,
} = defineProps<{
    /** The movement to show and change, or null for a new expense or income. */
    movement: App.Data.MovementData | null;
    /** The choices of the menus. */
    options: MovementFormOptions;
    /** What a new movement starts from: the kind, the planned item and the day. */
    start: MovementStart;
    /** The due dates of the planned item that the movement can settle. */
    occurrences?: App.Data.OptionData[];
    /** The page to go back to (a local path): the server sends the person there after saving. */
    back?: string | null;
}>();

/** The files chosen on the page and not saved yet, sent with the movement. */
const files = defineModel<File[]>('files', { default: () => [] });

/** The documents chosen in *Link*, linked when the movement is saved. */
const link = defineModel<App.Data.DocumentData[]>('link', {
    default: () => [],
});

/** The ids of the linked documents to unlink when the movement is saved. */
const unlink = defineModel<number[]>('unlink', { default: () => [] });

const emit = defineEmits<{
    /** The person gave up. */
    close: [];
}>();

/**
 * An id as the text FormKit compares.
 *
 * @param id - the id, or null
 * @returns the text, empty when there is no id
 */
function text(id: number | null | undefined): string {
    return id === null || id === undefined ? '' : String(id);
}

/** The planned item the form started with, and its due date. */
const initialPlanned = text(movement?.planned_id) || start.planned_id || '';
const initialOccurrence = movement
    ? (movement.occurrence_date ?? '')
    : (occurrences[0]?.value ?? '');

/** The fields of the form. */
const values = reactive({
    kind: movement?.kind ?? start.kind,
    title: movement?.title ?? '',
    date: movement?.date ?? start.date,
    // The form speaks in cost + commission; what is stored is the total
    amount: formatAmount(withoutFee(movement?.amount, movement?.fee_amount)),
    fee_amount: formatAmount(movement?.fee_amount),
    financial_account_id: text(movement?.account_id),
    category_id: movement
        ? text(movement.category_id)
        : (start.category_id ?? ''),
    card: movement ? movement.card : (start.card ?? null),
    organization_id: text(movement?.organization_id),
    person_id: text(movement?.person_id),
    employment_id: text(movement?.employment_id),
    planned_id: initialPlanned,
    occurrence_date: initialOccurrence,
    type: movement?.type ?? '',
});

/** The label and the choices of the card of an expense, by the type of the category; the card chosen fills the empty fields. */
const {
    label: cardLabel,
    choices: cardChoices,
    fillFrom: fillFromCard,
} = useCardChoice(() => values.category_id, toRef(values, 'card'), options, {
    category_id: toRef(values, 'category_id'),
    person_id: toRef(values, 'person_id'),
    organization_id: toRef(values, 'organization_id'),
});

// *Add expense* of a card: the new expense starts with what the card knows
if (movement === null && start.card) {
    fillFromCard(start.card);
}

/** The type a new income starts with, until a planned income says another. */
const defaultType =
    options.income_types.find((type) => type.value === 'other')?.value ??
    options.income_types[0]?.value ??
    '';

if (values.kind === 'income' && values.type === '') {
    values.type = defaultType;
}

/** The page has the title on the computer: the form keeps its own heading on the phone only. */
const wide = useWideLayout();

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The form is being sent. */
const processing = ref(false);

/** Whether it is an expense. */
const expense = computed(() => values.kind === 'expense');

/** The movement cannot be changed by this person: the fields are only read. */
const readonly = computed(() => movement !== null && !movement.can_edit);

/** The planned expenses or incomes the movement can settle, by its kind. */
const plannedChoices = computed(() =>
    withNone(options.planned.filter((choice) => choice.kind === values.kind)),
);

/** The planned item that the due dates of the page are for. */
const loadedFor = ref(initialPlanned);

/** The due dates are offered for the planned item chosen, once the page has sent them. */
const showOccurrences = computed(
    () =>
        occurrences.length > 0 &&
        values.planned_id !== '' &&
        values.planned_id === loadedFor.value,
);

/** The title of the panel. */
const heading = computed(
    () => movement?.title ?? trans('New expense or income'),
);

/** Whether the person can save: a new movement, or one they can change. */
const canSave = computed(() => movement === null || movement.can_edit);

/**
 * Fills what is still empty from the planned item chosen: the title, the
 * organization, the account, the holder and the category or the type.
 *
 * @param id - the id of the planned item
 */
function fillFrom(id: string): void {
    const choice = options.planned.find(
        (candidate) => candidate.kind === values.kind && candidate.value === id,
    );

    if (!choice) {
        return;
    }

    const fields: [keyof typeof values, string | null][] = [
        ['title', choice.label],
        ['organization_id', choice.organization_id],
        ['financial_account_id', choice.financial_account_id],
        ['person_id', choice.person_id],
    ];

    if (expense.value) {
        fields.push(['category_id', choice.category_id], ['card', choice.card]);
    } else {
        fields.push(
            ['employment_id', choice.employment_id],
            ['type', choice.type],
        );
    }

    for (const [field, value] of fields) {
        // A new income starts as «other», which a planned income may replace
        const empty =
            isEmpty(values[field]) ||
            (field === 'type' && values.type === defaultType);

        if (empty && value !== null) {
            values[field] = value;
        }
    }
}

if (movement === null && initialPlanned !== '') {
    fillFrom(initialPlanned);
}

// A planned item chosen fills the empty fields and asks the page for its open due dates
watch(
    () => values.planned_id,
    (id) => {
        values.occurrence_date = '';

        if (id === '') {
            loadedFor.value = '';

            return;
        }

        fillFrom(id);
        router.reload({
            only: ['occurrences'],
            data: {
                planned_kind: values.kind,
                planned: id,
                planned_date: values.date,
            },
            preserveUrl: true,
        });
    },
);

// The due dates of the planned item chosen arrived: the first is the one the day registers
watch(
    () => occurrences,
    (list) => {
        loadedFor.value = values.planned_id;
        values.occurrence_date =
            values.planned_id === initialPlanned && movement
                ? initialOccurrence
                : (list[0]?.value ?? '');
    },
);

// The planned expenses and the planned incomes are different lists
watch(
    () => values.kind,
    (kind) => {
        values.planned_id = '';

        if (kind === 'income' && values.type === '') {
            values.type = defaultType;
        }
    },
);

/**
 * The changes not saved, from the values filled in so far: the ones the
 * movement, the start and the planned item gave are not changes.
 */
const changes = useUnsavedChanges(() => ({
    ...values,
    files: files.value.length,
    link: link.value.map((document) => document.id),
    unlink: unlink.value,
}));

/** Why the server refused the files (`files`, `files.0`…), for the zone of the files on the page. */
const fileErrors = computed(() => listErrors(problems.value, 'files'));

/** *Cancel*: gives up the changes without asking. */
function cancel(): void {
    changes.reset();
    emit('close');
}

/** Sends the movement, if it has its title and amount. */
function save(): void {
    problems.value = {};

    for (const field of ['title', 'amount'] as const) {
        if (isEmpty(values[field])) {
            problems.value[field] = trans('This field is required.');
        }
    }

    if (Object.keys(problems.value).length > 0) {
        return;
    }

    const data = {
        ...(movement ? {} : { kind: values.kind }),
        title: values.title,
        amount: expense.value
            ? withFee(
                  parseAmount(values.amount),
                  parseAmount(values.fee_amount),
              )
            : parseAmount(values.amount),
        date: values.date,
        files: files.value,
        link_documents: link.value.map((document) => document.id),
        unlink_documents: unlink.value,
        ...(back ? { from: back } : {}),
        financial_account_id: values.financial_account_id || null,
        organization_id: values.organization_id || null,
        person_id: values.person_id || null,
        planned_id: values.planned_id || null,
        occurrence_date: values.occurrence_date || null,
        // From *Add expense* of a card, the server opens the card again
        ...(!movement && start.card ? { back_to_card: true } : {}),
        ...(expense.value
            ? {
                  fee_amount: parseAmount(values.fee_amount),
                  category_id: values.category_id || null,
                  card: values.card || null,
              }
            : {
                  type: values.type || null,
                  employment_id: values.employment_id || null,
              }),
    };
    processing.value = true;

    router.post(
        movement
            ? update.url({ kind: movement.kind, id: movement.id })
            : store.url(),
        movement ? withMethod(data, 'put') : data,
        {
            preserveScroll: true,
            onError: (messages) => {
                problems.value = messages;
            },
            onSuccess: () => {
                files.value = [];
                link.value = [];
                unlink.value = [];
                changes.reset();
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

useSaveShortcut(save, () => canSave.value && !processing.value);

defineExpose({ fileErrors });
</script>

<template>
    <form
        class="flex flex-col gap-4 rounded-sheet bg-card p-5 shadow-card wide:p-7"
        :aria-label="heading"
        novalidate
        @submit.prevent="save"
    >
        <DefineOrganization>
            <FormKit
                v-model="values.organization_id"
                type="autocomplete"
                open-on-click
                :label="$t('Organization')"
                optional
                name="organization_id"
                :options="withNone(options.organizations)"
                :errors="errors(problems.organization_id)"
            />
        </DefineOrganization>
        <h2 v-if="!wide" class="m-0 text-stat">{{ heading }}</h2>

        <fieldset
            :disabled="readonly"
            class="m-0 flex flex-col gap-3.5 border-0 p-0"
        >
            <FormKit
                v-if="!movement"
                v-model="values.kind"
                type="togglebuttons"
                enforced
                :label="$t('It is')"
                name="kind"
                :options="[
                    { value: 'expense', label: $t('An expense') },
                    { value: 'income', label: $t('An income') },
                ]"
                :errors="errors(problems.kind)"
            />
            <FormKit
                v-model="values.title"
                type="text"
                :label="$t('Title')"
                name="title"
                autocomplete="off"
                :errors="errors(problems.title)"
            />
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="values.date"
                    type="datepicker"
                    :label="expense ? $t('Paid on') : $t('Received on')"
                    name="date"
                    :errors="errors(problems.date)"
                />
                <FormKit
                    v-model="values.amount"
                    type="currency"
                    :label="$t('Amount')"
                    name="amount"
                    :errors="errors(problems.amount)"
                />
            </div>
            <div v-if="expense" class="flex flex-col gap-1.5">
                <FormKit
                    v-model="values.fee_amount"
                    type="currency"
                    :label="$t('Commission')"
                    optional
                    name="fee_amount"
                    :errors="errors(problems.fee_amount)"
                />
                <p class="m-0 text-meta text-ink-soft">
                    {{ $t('It is added to the amount.') }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="values.financial_account_id"
                    type="dropdown"
                    :label="$t('Bank account')"
                    name="financial_account_id"
                    :options="withNone(options.accounts)"
                    :errors="errors(problems.financial_account_id)"
                />
                <FormKit
                    v-if="expense"
                    v-model="values.category_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Category')"
                    name="category_id"
                    :options="withNone(options.categories)"
                    :errors="errors(problems.category_id)"
                />
                <ReuseOrganization v-else />
            </div>
            <ReuseOrganization v-if="expense" />
            <FormKit
                v-if="!expense"
                v-model="values.type"
                type="togglebuttons"
                enforced
                :label="$t('Type')"
                name="type"
                :options="options.income_types"
                :errors="errors(problems.type)"
            />
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-if="expense"
                    v-model="values.card"
                    type="autocomplete"
                    open-on-click
                    :label="cardLabel"
                    optional
                    name="card"
                    :options="withNone(cardChoices)"
                    :errors="errors(problems.card)"
                />
                <FormKit
                    v-model="values.person_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Holder')"
                    optional
                    name="person_id"
                    :options="withNone(options.people)"
                    :errors="errors(problems.person_id)"
                />
                <FormKit
                    v-if="!expense"
                    v-model="values.employment_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Employment')"
                    optional
                    name="employment_id"
                    :options="withNone(options.employments)"
                    :errors="errors(problems.employment_id)"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="values.planned_id"
                    type="autocomplete"
                    open-on-click
                    :label="
                        expense ? $t('Planned expense') : $t('Planned income')
                    "
                    name="planned_id"
                    optional
                    :options="plannedChoices"
                    :errors="errors(problems.planned_id)"
                />
                <FormKit
                    v-if="showOccurrences"
                    v-model="values.occurrence_date"
                    type="dropdown"
                    :label="$t('Due date')"
                    name="occurrence_date"
                    :options="occurrences"
                    :errors="errors(problems.occurrence_date)"
                />
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-2.5 pt-1">
            <FmDeleteButton
                v-if="movement?.can_delete"
                :url="destroy.url({ kind: movement.kind, id: movement.id })"
                :question="$t('Delete this movement?')"
                :text="$t('Its documents stay.')"
                :confirm-label="$t('Delete the movement')"
            />
            <FmButton variant="neutral" @click="cancel">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton
                v-if="canSave"
                type="submit"
                class="grow"
                :disabled="processing"
                >{{ $t('Save') }}</FmButton
            >
        </div>
    </form>
</template>
