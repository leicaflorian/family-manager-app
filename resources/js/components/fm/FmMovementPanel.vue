<script setup lang="ts">
/**
 * The panel of a movement, as the board *Pagamenti-pc* draws it: the title,
 * when it was paid (or received) and how much, the commission and the account,
 * the category, the organization, the holder, the planned expense or income it
 * settles (and which of its due dates), and the documents linked to it, with
 * *Delete*, *Cancel* and *Save*. Without a movement it is the form of a new
 * expense or income, which starts with the choice between the two. Choosing a
 * planned expense or income fills the fields still empty from it. Used beside
 * the list on the computer (`compact`) and on its own page on the phone.
 */
import { router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import type { MovementFormOptions, MovementStart } from '@/lib/budget';
import { errors } from '@/lib/errors';
import { withMethod } from '@/lib/forms';
import { cn } from '@/lib/utils';
import { show as showDocument } from '@/routes/documents';
import { destroy, store, update } from '@/routes/budget/movements';
import FmButton from './FmButton.vue';
import FmDeleteButton from './FmDeleteButton.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

const {
    movement,
    options,
    start,
    occurrences = [],
    compact = false,
} = defineProps<{
    /** The movement to show and change, or null for a new expense or income. */
    movement: App.Data.MovementData | null;
    /** The choices of the menus. */
    options: MovementFormOptions;
    /** What a new movement starts from: the kind, the planned item and the day. */
    start: MovementStart;
    /** The due dates of the planned item that the movement can settle. */
    occurrences?: App.Data.OptionData[];
    /** Beside the list on the computer: it has the × to close it. */
    compact?: boolean;
}>();

const emit = defineEmits<{
    /** The person gave up, or closed the panel. */
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

/**
 * An amount for a currency field.
 *
 * @param amount - the amount, or null
 * @returns the amount with two decimals, empty when there is none
 */
function money(amount: number | null | undefined): string {
    return amount === null || amount === undefined ? '' : amount.toFixed(2);
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
    amount: money(movement?.amount),
    fee_amount: money(movement?.fee_amount),
    financial_account_id: text(movement?.account_id),
    category_id: text(movement?.category_id),
    organization_id: text(movement?.organization_id),
    person_id: text(movement?.person_id),
    planned_id: initialPlanned,
    occurrence_date: initialOccurrence,
    type: movement?.type ?? '',
});

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The form is being sent. */
const processing = ref(false);

/** Whether it is an expense. */
const expense = computed(() => values.kind === 'expense');

/** The movement cannot be changed by this person: the fields are only read. */
const readonly = computed(() => movement !== null && !movement.can_edit);

/** The empty choice at the top of a menu. */
const none = computed(() => ({ value: '', label: trans('None') }));

/**
 * The options of a menu that can stay empty.
 *
 * @param choices - the options of the menu
 * @returns the options with «None» in front
 */
function withNone(
    choices: { value: string; label: string }[],
): { value: string; label: string }[] {
    return [
        none.value,
        ...choices.map(({ value, label }) => ({ value, label })),
    ];
}

/** The planned expenses or incomes the movement can settle, by its kind. */
const plannedChoices = computed(() =>
    withNone(options.planned.filter((choice) => choice.kind === values.kind)),
);

/** The due dates are offered only for the planned item the form started with. */
const showOccurrences = computed(
    () =>
        occurrences.length > 0 &&
        values.planned_id === initialPlanned &&
        values.planned_id !== '',
);

/** The title of the panel. */
const heading = computed(
    () => movement?.title ?? trans('New expense or income'),
);

/** Whether the person can save: a new movement, or one they can change. */
const canSave = computed(() => movement === null || movement.can_edit);

/**
 * Whether a field is empty.
 *
 * @param value - the value of a field
 * @returns true when nothing is written or chosen
 */
function isEmpty(value: unknown): boolean {
    return value === null || value === undefined || value === '';
}

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
        expense.value
            ? ['category_id', choice.category_id]
            : ['type', choice.type],
    ];

    for (const [field, value] of fields) {
        if (isEmpty(values[field]) && value !== null) {
            values[field] = value;
        }
    }
}

if (movement === null && initialPlanned !== '') {
    fillFrom(initialPlanned);
}

// A new planned item fills the empty fields; its due dates are not known here, so the server picks the one of the day
watch(
    () => values.planned_id,
    (id) => {
        values.occurrence_date = id === initialPlanned ? initialOccurrence : '';

        if (id !== '') {
            fillFrom(id);
        }
    },
);

// The planned expenses and the planned incomes are different lists
watch(
    () => values.kind,
    () => {
        values.planned_id = '';
    },
);

/**
 * A number from a currency field.
 *
 * @param value - what the field holds, «3.50» or «3,50»
 * @returns the number, or null when the field is empty
 */
function number(value: string): number | null {
    return isEmpty(value) ? null : Number(String(value).replace(',', '.'));
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
        amount: number(values.amount),
        date: values.date,
        financial_account_id: values.financial_account_id || null,
        organization_id: values.organization_id || null,
        person_id: values.person_id || null,
        planned_id: values.planned_id || null,
        occurrence_date: values.occurrence_date || null,
        ...(expense.value
            ? {
                  fee_amount: number(values.fee_amount),
                  category_id: values.category_id || null,
              }
            : { type: values.type || null }),
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
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

useSaveShortcut(save, () => canSave.value && !processing.value);
</script>

<template>
    <form
        :class="
            cn(
                'flex flex-col gap-4 bg-white p-5',
                compact ? 'rounded-[20px]' : 'rounded-3xl lg:p-7',
            )
        "
        :aria-label="heading"
        novalidate
        @submit.prevent="save"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 class="m-0 text-xl font-extrabold">{{ heading }}</h2>
            <button
                v-if="compact"
                type="button"
                :aria-label="$t('Close')"
                class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-edge bg-white text-ink"
                @click="emit('close')"
            >
                <FmIcon :icon="X" :size="20" />
            </button>
        </div>

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
                    { value: 'expense', label: $t('Expense') },
                    { value: 'income', label: $t('Income') },
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
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-if="expense"
                    v-model="values.fee_amount"
                    type="currency"
                    :label="$t('Commission')"
                    name="fee_amount"
                    :errors="errors(problems.fee_amount)"
                />
                <FormKit
                    v-model="values.financial_account_id"
                    type="dropdown"
                    :label="$t('Account')"
                    name="financial_account_id"
                    :options="withNone(options.accounts)"
                    :errors="errors(problems.financial_account_id)"
                />
            </div>
            <p v-if="expense" class="m-0 -mt-1 text-[13px] text-ink-soft">
                {{ $t('The commission is already included in the amount.') }}
            </p>
            <div class="grid grid-cols-2 gap-3">
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
                <FormKit
                    v-else
                    v-model="values.type"
                    type="togglebuttons"
                    :label="$t('Type')"
                    name="type"
                    :options="options.income_types"
                    :errors="errors(problems.type)"
                />
                <FormKit
                    v-model="values.organization_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Organization')"
                    name="organization_id"
                    :options="withNone(options.organizations)"
                    :errors="errors(problems.organization_id)"
                />
                <FormKit
                    v-model="values.person_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Holder')"
                    name="person_id"
                    :options="withNone(options.people)"
                    :errors="errors(problems.person_id)"
                />
                <FormKit
                    v-model="values.planned_id"
                    type="autocomplete"
                    open-on-click
                    :label="
                        expense ? $t('Planned expense') : $t('Planned income')
                    "
                    name="planned_id"
                    :options="plannedChoices"
                    :errors="errors(problems.planned_id)"
                />
            </div>
            <FormKit
                v-if="showOccurrences"
                v-model="values.occurrence_date"
                type="dropdown"
                :label="$t('Due date')"
                name="occurrence_date"
                :options="occurrences"
                :errors="errors(problems.occurrence_date)"
            />
        </fieldset>

        <div
            v-if="movement && movement.documents.length > 0"
            class="flex flex-col gap-1.5"
        >
            <span class="text-[13px] font-semibold text-ink-soft">{{
                $t('Documents')
            }}</span>
            <div
                class="divide-y divide-divider overflow-hidden rounded-2xl border border-edge"
            >
                <FmRow
                    v-for="document in movement.documents"
                    :key="document.id"
                    :title="document.title"
                    :subtitle="
                        [document.category, document.organization]
                            .filter((part) => part !== null)
                            .join(' · ')
                    "
                    :href="showDocument.url(document.id)"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2.5">
            <FmDeleteButton
                v-if="movement?.can_delete"
                :url="destroy.url({ kind: movement.kind, id: movement.id })"
                :question="$t('Delete this movement?')"
                :text="$t('Its documents stay.')"
                :confirm-label="$t('Delete the movement')"
            />
            <div class="ml-auto flex flex-wrap gap-2.5">
                <FmButton variant="text" @click="emit('close')">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton v-if="canSave" type="submit" :disabled="processing">{{
                    $t('Save')
                }}</FmButton>
            </div>
        </div>
    </form>
</template>
