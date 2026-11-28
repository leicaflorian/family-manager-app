<script setup lang="ts">
/**
 * The section *Commission* of the form of a bank filter, as the board *Dalla-banca-filtro* draws it: three choices
 * (*Find it by itself*, the one with the same mandate; *Recognise it with these words*; *No commission*), each with
 * the line that explains it. Only *Recognise it with these words* shows the words of the commission, the ones it
 * contains and the ones it does not, under the choices.
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import type { BankFilterFormValues } from '@/lib/bankFilters';
import { errors } from '@/lib/errors';

const { problems = {} } = defineProps<{
    /** The messages of the server, by field. */
    problems?: Record<string, string>;
}>();

/** The fields of the form, changed in place. */
const values = defineModel<BankFilterFormValues>({ required: true });

/** The three ways of finding the commission, each with what it means. */
const choices = computed(() => [
    {
        value: 'auto',
        label: trans('Find it by itself'),
        help: trans('The one with the same mandate, within 5 days.'),
    },
    {
        value: 'words',
        label: trans('Recognise it with these words'),
        help: trans('For texts without a mandate or with a different one.'),
    },
    {
        value: 'none',
        label: trans('No commission'),
        help: trans('The movement has none.'),
    },
]);
</script>

<template>
    <fieldset class="m-0 flex flex-col border-0 p-0">
        <legend class="mb-3 p-0 text-section">
            {{ $t('Commission') }}
            <span class="mt-0.5 block text-meta text-ink-soft">{{
                $t(
                    'It goes into the expense of the movement, included in the amount.',
                )
            }}</span>
        </legend>
        <FormKit
            v-model="values.fee_mode"
            type="radio"
            name="fee_mode"
            :options="choices"
            :errors="errors(problems.fee_mode)"
        />
        <div
            v-if="values.fee_mode === 'words'"
            class="flex flex-col rounded-lg bg-action-soft px-3.5 pt-3.5 pb-3"
        >
            <FormKit
                v-model="values.fee_contains"
                type="taglist"
                :label="$t('Contains the words')"
                name="fee_contains"
                :options="[]"
                allow-new-values
                show-empty-message
                :empty-message="$t('Press Enter to add the word')"
                :placeholder="$t('Add a word')"
                :errors="errors(problems.fee_contains)"
            />
            <FormKit
                v-model="values.fee_excludes"
                type="taglist"
                :label="$t('Does not contain')"
                name="fee_excludes"
                optional
                :options="[]"
                allow-new-values
                show-empty-message
                :empty-message="$t('Press Enter to add the word')"
                :placeholder="$t('Add a word')"
                :errors="errors(problems.fee_excludes)"
            />
            <p class="m-0 text-meta text-ink-soft">
                {{
                    $t(
                        'On the same account, within 5 days of the movement, with a smaller amount.',
                    )
                }}
            </p>
        </div>
    </fieldset>
</template>
