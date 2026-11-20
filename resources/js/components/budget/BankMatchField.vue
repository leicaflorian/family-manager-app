<script setup lang="ts">
/**
 * *How it shows in the bank*, in *Where* of a planned expense or income: the text the bank writes in the list of
 * movements for this payment (a rule, saved as it is written). Beside it the sparkles open *Write it with the AI*
 * (only while the reading is on), and under it, half a second after the last letter, what the rule matches among
 * the movements of the last 90 days, or why it does not work. With an `example` the panel opens by itself, with
 * that movement chosen.
 *
 * @group forms
 */
import { CircleCheck, Sparkles } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { useBankMatchPreview } from '@/composables/useBankMatchPreview';
import type { BankMatchKind } from '@/lib/bankMatch';
import { matchSummary } from '@/lib/bankMatch';
import { errors } from '@/lib/errors';
import BankRuleSheet from './BankRuleSheet.vue';

const {
    kind,
    accountId,
    ai,
    example = null,
    error,
} = defineProps<{
    /** The direction of the form: an expense looks at outgoing movements, an income at incoming ones. */
    kind: BankMatchKind;
    /** The account the form has chosen, null for all of them. */
    accountId: number | null;
    /** The reading is on: the button that writes the rule with the AI is there. */
    ai: boolean;
    /** A movement the panel opens with, already chosen. */
    example?: App.Data.BankTransactionData | null;
    /** What the server said about the field when the form was sent. */
    error?: string;
}>();

/** The rule. */
const rule = defineModel<string | null>({ default: null });

const sheet = ref(example !== null && ai);

const { result, error: broken } = useBankMatchPreview({
    pattern: () => rule.value ?? '',
    kind: () => kind,
    accountId: () => accountId,
});

/**
 * The message of a rule that does not work, with the way out added only when it exists: the server says the rule does
 * not work, and the button to write it with the AI is there only while the reading is on.
 *
 * @param text - what the server said
 * @returns the text, with the hint about the AI when it is on and the text is the one of a rule that does not work
 */
function withHint(text: string | undefined): string | undefined {
    return ai && text === trans('This rule does not work.')
        ? `${text} ${trans('Try writing it with the AI.')}`
        : text;
}

/** What is said under the field: the movements, none, or the reason the rule does not work. */
const line = computed(() => {
    if (broken.value !== null) {
        return { tone: 'danger' as const, text: withHint(broken.value) };
    }

    if (result.value === null) {
        return null;
    }

    return {
        tone: result.value.count === 0 ? ('warning' as const) : ('ok' as const),
        text: matchSummary(result.value),
    };
});

/**
 * Puts the rule of the panel in the field.
 *
 * @param pattern - the rule the AI wrote
 */
function use(pattern: string): void {
    rule.value = pattern;
}
</script>

<template>
    <div class="mb-3.5 min-w-0">
        <label for="bank_match" class="mb-1.5 block text-label text-ink">
            {{ $t('How it shows in the bank') }}
            <span class="text-body-sm text-ink-soft">{{
                $t('(optional)')
            }}</span>
        </label>
        <div class="flex items-start gap-2">
            <FormKit
                id="bank_match"
                v-model="rule"
                type="text"
                name="bank_match"
                autocomplete="off"
                outer-class="min-w-0 flex-1"
                :errors="errors(withHint(error))"
                :help="undefined"
                aria-describedby="bank_match_help"
            />
            <FmButton
                v-if="ai"
                variant="neutral"
                :icon="Sparkles"
                :label="$t('Write it with the AI')"
                class="text-action"
                @click="sheet = true"
            />
        </div>
        <span id="bank_match_help" class="mt-1 block text-meta text-ink-soft">{{
            $t(
                'The text the bank writes in the list of movements for this payment.',
            )
        }}</span>
        <FmNotice
            v-if="line"
            class="mt-2"
            :tone="line.tone"
            :icon="line.tone === 'ok' ? CircleCheck : undefined"
            >{{ line.text }}</FmNotice
        >
        <BankRuleSheet
            v-if="ai"
            v-model:open="sheet"
            :kind="kind"
            :account-id="accountId"
            :example="example"
            @use="use"
        />
    </div>
</template>
