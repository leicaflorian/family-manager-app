<script setup lang="ts">
/**
 * The section *Matches* of the form of a bank filter, as the board *Dalla-banca-filtro* draws it: the words the text
 * must contain and the ones it must not (tags: Enter adds one), the direction, the account the filter reads (or all of
 * them) and the amounts from and up to. An advanced filter, made from the rule of a planned item, has its expression
 * instead of the words, and it changes only as text.
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import type {
    BankFilterFormOptions,
    BankFilterFormValues,
} from '@/lib/bankFilters';
import { errors } from '@/lib/errors';

const { options, problems = {} } = defineProps<{
    /** The choices of the form: the accounts the bank reads. */
    options: BankFilterFormOptions;
    /** The messages of the server, by field. */
    problems?: Record<string, string>;
}>();

/** The fields of the form, changed in place. */
const values = defineModel<BankFilterFormValues>({ required: true });

/** The accounts the filter can read, with *All the accounts* first. */
const accounts = computed(() => [
    { value: '', label: trans('All the accounts') },
    ...options.accounts.map((account) => ({
        value: String(account.id),
        label: account.name,
    })),
]);
</script>

<template>
    <fieldset class="m-0 flex flex-col border-0 p-0">
        <legend class="mb-3 p-0 text-section">
            {{ $t('Matches') }}
            <span class="mt-0.5 block text-meta text-ink-soft">{{
                $t('They must all hold. Upper and lower case do not count.')
            }}</span>
        </legend>
        <FormKit
            v-if="values.pattern !== null"
            v-model="values.pattern"
            type="text"
            :label="$t('Expression')"
            name="pattern"
            autocomplete="off"
            :help="
                $t(
                    'It comes from the rule of a planned item: it changes only as text.',
                )
            "
            :errors="errors(problems.pattern)"
        />
        <template v-else>
            <FormKit
                v-model="values.contains"
                type="taglist"
                :label="$t('Contains the words')"
                name="contains"
                :options="[]"
                allow-new-values
                show-empty-message
                :empty-message="$t('Press Enter to add the word')"
                :placeholder="$t('Add a word')"
                :help="
                    $t(
                        'All of them, in any order. Between quotes the exact phrase counts.',
                    )
                "
                :errors="errors(problems.contains)"
            />
            <FormKit
                v-model="values.excludes"
                type="taglist"
                :label="$t('Does not contain')"
                name="excludes"
                optional
                :options="[]"
                allow-new-values
                show-empty-message
                :empty-message="$t('Press Enter to add the word')"
                :placeholder="$t('Add a word')"
                :errors="errors(problems.excludes)"
            />
        </template>
        <FormKit
            v-model="values.direction"
            type="togglebuttons"
            enforced
            :label="$t('Direction')"
            name="direction"
            :options="[
                { value: 'expense', label: $t('Going out') },
                { value: 'income', label: $t('Coming in') },
            ]"
            :errors="errors(problems.direction)"
        />
        <FormKit
            v-model="values.financial_account_id"
            type="dropdown"
            :label="$t('Bank account')"
            name="financial_account_id"
            :options="accounts"
            :errors="errors(problems.financial_account_id)"
        />
        <div class="grid grid-cols-2 gap-3">
            <FormKit
                v-model="values.amount_min"
                type="currency"
                :label="$t('Amount from')"
                optional
                name="amount_min"
                :errors="errors(problems.amount_min)"
            />
            <FormKit
                v-model="values.amount_max"
                type="currency"
                :label="$t('Amount up to')"
                optional
                name="amount_max"
                :errors="errors(problems.amount_max)"
            />
        </div>
    </fieldset>
</template>
