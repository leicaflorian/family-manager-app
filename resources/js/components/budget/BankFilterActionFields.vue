<script setup lang="ts">
/**
 * The section *Action* of the form of a bank filter, as the board *Dalla-banca-filtro* draws it: *What it does*
 * (pay a due date, create the expense or the income, it is a transfer, ignore) and the fields of the action chosen.
 * *Create the expense* has the fields of an expense (title, with the month or not, category, organization, person,
 * card and notes: amount, day and account come from the movement), *Pay a due date* the planned items of the
 * direction (only for who can make a filter that pays), *It is a transfer* the account on the other side.
 */
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed, toRef } from 'vue';
import { useCardChoice } from '@/composables/useCardChoice';
import type {
    BankFilterFormOptions,
    BankFilterFormValues,
} from '@/lib/bankFilters';
import { errors } from '@/lib/errors';
import { withNone } from '@/lib/forms';

const {
    options,
    canPay,
    problems = {},
} = defineProps<{
    /** The choices of the form: categories, organizations, people, cards, planned items and accounts. */
    options: BankFilterFormOptions;
    /** Whether who writes can make a filter that pays a due date (not a member). */
    canPay: boolean;
    /** The messages of the server, by field. */
    problems?: Record<string, string>;
}>();

/** The fields of the form, changed in place. */
const values = defineModel<BankFilterFormValues>({ required: true });

/** The filter takes incoming movements. */
const income = computed((): boolean => values.value.direction === 'income');

/** The actions a filter can have; *Pay a due date* only for who can, or when the filter already pays. */
const actions = computed(() => [
    ...(canPay || values.value.action === 'pay'
        ? [
              {
                  value: 'pay',
                  label: income.value
                      ? trans('Mark an income received')
                      : trans('Pay a due date'),
              },
          ]
        : []),
    {
        value: 'create',
        label: income.value
            ? trans('Create the income')
            : trans('Create the expense'),
    },
    { value: 'transfer', label: trans('It is a transfer') },
    { value: 'ignore', label: trans('Ignore') },
]);

/** The planned items of the direction the filter takes. */
const planned = computed(() =>
    withNone(
        options.planned.filter(
            (choice) => choice.kind === values.value.direction,
        ),
    ),
);

/** The accounts on the other side of a transfer: the open ones, not the one the filter reads. */
const transferAccounts = computed(() =>
    options.transfer_accounts
        .filter(
            (account) =>
                String(account.id) !== values.value.financial_account_id,
        )
        .map((account) => ({ value: String(account.id), label: account.name })),
);

/** The label and the choices of the card, by the category; a card chosen fills the empty fields. */
const { label: cardLabel, choices: cardChoices } = useCardChoice(
    () => values.value.category_id,
    toRef(values.value, 'card'),
    options,
    {
        category_id: toRef(values.value, 'category_id'),
        person_id: toRef(values.value, 'person_id'),
        organization_id: toRef(values.value, 'organization_id'),
    },
);

/** The example under *Add the month to the title*: the title with the month of today. */
const monthExample = computed((): string => {
    const month = new Intl.DateTimeFormat(getActiveLanguage(), {
        month: 'long',
        year: 'numeric',
        timeZone: 'Europe/Rome',
    }).format(new Date());

    return trans('For example «:example».', {
        example: `${values.value.title || trans('Title')} ${month}`,
    });
});
</script>

<template>
    <fieldset class="m-0 flex flex-col border-0 p-0">
        <legend class="mb-3 p-0 text-section">{{ $t('Action') }}</legend>
        <FormKit
            v-model="values.action"
            type="dropdown"
            :label="$t('What it does')"
            name="action"
            :options="actions"
            :errors="errors(problems.action)"
        />
        <FormKit
            v-if="values.action === 'pay'"
            v-model="values.planned_id"
            type="autocomplete"
            open-on-click
            :label="income ? $t('Planned income') : $t('Planned expense')"
            name="planned_id"
            :options="planned"
            :errors="errors(problems.planned_id)"
        />
        <template v-else-if="values.action === 'create'">
            <FormKit
                v-model="values.title"
                type="text"
                :label="$t('Title')"
                name="title"
                autocomplete="off"
                :errors="errors(problems.title)"
            />
            <FormKit
                v-model="values.title_with_month"
                type="toggle"
                :label="$t('Add the month to the title')"
                name="title_with_month"
                :help="monthExample"
            />
            <FormKit
                v-if="!income"
                v-model="values.category_id"
                type="autocomplete"
                open-on-click
                :label="$t('Category')"
                name="category_id"
                :options="withNone(options.categories)"
                :errors="errors(problems.category_id)"
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
                :label="$t('Person')"
                optional
                name="person_id"
                :placeholder="$t('Whole family')"
                :options="withNone(options.people)"
                :errors="errors(problems.person_id)"
            />
            <FormKit
                v-if="!income"
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
                v-model="values.notes"
                type="textarea"
                :label="$t('Notes')"
                optional
                name="notes"
                :errors="errors(problems.notes)"
            />
            <p class="m-0 text-meta text-ink-soft">
                {{ $t('Amount, date and account come from the movement.') }}
            </p>
        </template>
        <FormKit
            v-else-if="values.action === 'transfer'"
            v-model="values.transfer_account_id"
            type="dropdown"
            :label="$t('The other account')"
            name="transfer_account_id"
            :options="transferAccounts"
            :errors="errors(problems.transfer_account_id)"
        />
    </fieldset>
</template>
