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
import { wordsFieldProps } from '@/lib/bankFilters';
import { errors } from '@/lib/errors';

const { problems = {} } = defineProps<{
    /** The messages of the server, by field. */
    problems?: Record<string, string>;
}>();

/** The fields of the form, changed in place. */
const values = defineModel<BankFilterFormValues>({ required: true });

/** The props the two fields of words share. */
const words = wordsFieldProps();

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
        <div
            role="radiogroup"
            :aria-label="$t('Commission')"
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-xl border border-edge-soft bg-card"
        >
            <div
                v-for="choice in choices"
                :key="choice.value"
                :class="
                    values.fee_mode === choice.value
                        ? 'flex flex-col bg-action-soft'
                        : 'flex flex-col'
                "
            >
                <label
                    class="flex cursor-pointer items-start gap-3 px-3.5 py-3"
                >
                    <input
                        v-model="values.fee_mode"
                        type="radio"
                        name="fee_mode"
                        :value="choice.value"
                        class="peer sr-only"
                    />
                    <span
                        aria-hidden="true"
                        :class="
                            values.fee_mode === choice.value
                                ? 'mt-px size-[22px] shrink-0 rounded-full border-7 border-action bg-card'
                                : 'mt-px size-[22px] shrink-0 rounded-full border-[1.5px] border-edge bg-card'
                        "
                        class="peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-action"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span
                            :class="
                                values.fee_mode === choice.value
                                    ? 'text-body-strong text-ink'
                                    : 'text-body text-ink'
                            "
                            >{{ choice.label }}</span
                        >
                        <span class="text-meta text-ink-soft">{{
                            choice.help
                        }}</span>
                    </span>
                </label>
                <div
                    v-if="
                        choice.value === 'words' && values.fee_mode === 'words'
                    "
                    class="flex flex-col pr-3.5 pb-3.5 pl-12"
                >
                    <FormKit
                        v-bind="words"
                        v-model="values.fee_contains"
                        :label="$t('Contains the words')"
                        name="fee_contains"
                        :errors="errors(problems.fee_contains)"
                    />
                    <FormKit
                        v-bind="words"
                        v-model="values.fee_excludes"
                        :label="$t('Does not contain')"
                        name="fee_excludes"
                        optional
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
            </div>
        </div>
        <p
            v-if="problems.fee_mode"
            role="alert"
            class="m-0 mt-1.5 text-meta text-overdue"
        >
            {{ problems.fee_mode }}
        </p>
    </fieldset>
</template>
