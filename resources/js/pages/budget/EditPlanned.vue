<script setup lang="ts">
/**
 * *New planned expense*, *New planned income* and the change of one (only an
 * `admin`): what it is, when it happens and how often, where it goes (who it
 * is for, the account, the category or the type, and for an expense the card
 * of the type of its category) and, for an income, the employment it comes
 * from. A new one can start already filled in from a card, and the card
 * chosen fills the category, the holder and the organization still empty.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, toRef } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useCardChoice } from '@/composables/useCardChoice';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import type { PlannedFormOptions, PlannedFormValues } from '@/lib/budget';
import { errors } from '@/lib/errors';
import { isEmpty, withNone } from '@/lib/forms';
import { destroy, index, show, store, update } from '@/routes/budget/planned';

const { kind, planned, values, options } = defineProps<{
    /** `expense` or `income`. */
    kind: string;
    /** The planned item to change, or null for a new one. */
    planned: App.Data.PlannedRowData | null;
    /** The values the fields start from. */
    values: PlannedFormValues;
    /** The choices of the menus. */
    options: PlannedFormOptions;
}>();

const form = useForm<PlannedFormValues>({ ...values });

/** The card of the expense, null when none is chosen. */
const card = computed<string | null>({
    get: () => form.card ?? null,
    set: (value) => {
        form.card = value;
    },
});

/** The label and the choices of the card, by the type of the category; the card chosen fills the empty fields. */
const { label: cardLabel, choices: cardChoices } = useCardChoice(
    () => form.category_id,
    card,
    options,
    {
        category_id: toRef(form, 'category_id'),
        person_id: toRef(form, 'person_id'),
        organization_id: toRef(form, 'organization_id'),
    },
);

/** Whether it is an income. */
const income = computed(() => kind === 'income');

/** The title of the page. */
const heading = computed(() =>
    planned
        ? trans('Edit :name', { name: planned.title })
        : income.value
          ? trans('New planned income')
          : trans('New planned expense'),
);

/** Where *Close* and *Cancel* go back to. */
const back = computed(() =>
    planned ? show.url({ kind, id: planned.id }) : index().url,
);

/** The units of how often. */
const units = computed(() => [
    { value: 'week', label: trans('Weeks') },
    { value: 'month', label: trans('Months') },
    { value: 'year', label: trans('Years') },
]);

/** Sends the planned item, if the title, the amount and the start are there. */
function save(): void {
    form.clearErrors();

    for (const field of ['title', 'amount', 'start_date'] as const) {
        if (isEmpty(form[field])) {
            form.setError(field, trans('This field is required.'));
        }
    }

    if (form.hasErrors) {
        return;
    }

    if (planned) {
        form.put(update.url({ kind, id: planned.id }));
    } else {
        form.post(store.url({ kind }));
    }
}

useSaveShortcut(save, () => !form.processing);
</script>

<template>
    <Head :title="heading" />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle
            :title="heading"
            :back="back"
            :back-label="planned?.title ?? $t('Planned expenses')"
        >
            <template #actions>
                <Link
                    :href="back"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-xl border border-edge bg-white text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>

        <form
            class="flex max-w-[720px] flex-col gap-6 rounded-3xl bg-white p-5 lg:p-7"
            novalidate
            @submit.prevent="save"
        >
            <fieldset class="m-0 flex flex-col border-0 p-0">
                <legend class="mb-3 p-0 text-[17px] font-bold">
                    {{ $t('What') }}
                </legend>
                <FormKit
                    v-model="form.title"
                    type="text"
                    :label="$t('Title')"
                    name="title"
                    autocomplete="off"
                    :errors="errors(form.errors.title)"
                />
                <FormKit
                    v-model="form.amount"
                    type="currency"
                    :label="$t('Amount')"
                    name="amount"
                    :errors="errors(form.errors.amount)"
                />
                <FormKit
                    v-model="form.is_estimated"
                    type="toggle"
                    name="is_estimated"
                    :label="$t('Estimated amount')"
                    :help="
                        $t(
                            'It changes with every bill: the amount is an average.',
                        )
                    "
                />
                <FormKit
                    v-model="form.notes"
                    type="textarea"
                    :label="$t('Notes')"
                    name="notes"
                    :errors="errors(form.errors.notes)"
                />
            </fieldset>

            <fieldset class="m-0 flex flex-col border-0 p-0">
                <legend class="mb-3 p-0 text-[17px] font-bold">
                    {{ $t('When') }}
                </legend>
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="form.start_date"
                        type="datepicker"
                        :label="$t('Starts on')"
                        name="start_date"
                        :errors="errors(form.errors.start_date)"
                    />
                    <FormKit
                        v-model="form.end_date"
                        type="datepicker"
                        :label="$t('Ends on')"
                        name="end_date"
                        :errors="errors(form.errors.end_date)"
                    />
                </div>
                <FormKit
                    v-model="form.is_recurring"
                    type="toggle"
                    name="is_recurring"
                    :label="$t('Repeats')"
                />
                <div
                    v-if="form.is_recurring"
                    class="mt-3.5 grid grid-cols-[7rem_minmax(0,1fr)] gap-3"
                >
                    <FormKit
                        v-model="form.interval"
                        type="number"
                        min="1"
                        :label="$t('Every')"
                        name="interval"
                        :errors="errors(form.errors.interval)"
                    />
                    <FormKit
                        v-model="form.interval_unit"
                        type="togglebuttons"
                        enforced
                        :label="$t('How often')"
                        name="interval_unit"
                        :options="units"
                        :errors="errors(form.errors.interval_unit)"
                    />
                </div>
            </fieldset>

            <fieldset class="m-0 flex flex-col border-0 p-0">
                <legend class="mb-3 p-0 text-[17px] font-bold">
                    {{ $t('Where') }}
                </legend>
                <FormKit
                    v-model="form.organization_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Organization')"
                    name="organization_id"
                    :options="withNone(options.organizations)"
                    :errors="errors(form.errors.organization_id)"
                />
                <FormKit
                    v-if="income"
                    v-model="form.type"
                    type="togglebuttons"
                    enforced
                    :label="$t('Type')"
                    name="type"
                    :options="options.income_types"
                    :errors="errors(form.errors.type)"
                />
                <FormKit
                    v-else
                    v-model="form.category_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Category')"
                    name="category_id"
                    :options="withNone(options.categories)"
                    :errors="errors(form.errors.category_id)"
                />
                <FormKit
                    v-if="!income"
                    v-model="form.card"
                    type="autocomplete"
                    open-on-click
                    :label="cardLabel"
                    name="card"
                    :options="withNone(cardChoices)"
                    :errors="errors(form.errors.card)"
                />
                <FormKit
                    v-model="form.financial_account_id"
                    type="dropdown"
                    :label="$t('Account')"
                    name="financial_account_id"
                    :options="withNone(options.accounts)"
                    :help="$t('Empty means the default account.')"
                    :errors="errors(form.errors.financial_account_id)"
                />
                <FormKit
                    v-model="form.person_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Holder')"
                    name="person_id"
                    :options="withNone(options.people)"
                    :help="$t('Empty means the whole family.')"
                    :errors="errors(form.errors.person_id)"
                />
            </fieldset>

            <fieldset v-if="income" class="m-0 flex flex-col border-0 p-0">
                <legend class="mb-3 p-0 text-[17px] font-bold">
                    {{ $t('Refers to') }}
                </legend>
                <FormKit
                    v-model="form.employment_id"
                    type="autocomplete"
                    open-on-click
                    :label="$t('Employment')"
                    name="employment_id"
                    :options="withNone(options.employments)"
                    :errors="errors(form.errors.employment_id)"
                />
            </fieldset>

            <div class="flex flex-wrap items-center justify-between gap-2.5">
                <FmDeleteButton
                    v-if="planned"
                    :url="destroy.url({ kind, id: planned.id })"
                    :question="$t('Delete this planned expense?')"
                    :text="$t('Its payments stay, without the link to it.')"
                    :confirm-label="$t('Delete the planned expense')"
                />
                <div class="ml-auto flex flex-wrap gap-2.5">
                    <FmButton variant="text" :href="back">{{
                        $t('Cancel')
                    }}</FmButton>
                    <FmButton type="submit" :disabled="form.processing">{{
                        $t('Save')
                    }}</FmButton>
                </div>
            </div>
        </form>
    </div>
</template>
