<script setup lang="ts">
/**
 * *Write it with the AI*: the panel of the field *How it shows in the bank*. The person chooses one or more
 * transactions of this payment (or pastes the text the bank wrote, a line each), the AI writes the rule that keeps
 * the words that stay the same, and the panel shows the rule, a sentence that explains it and the movements it
 * matches. *Use this rule* gives the rule to the field and closes. A reading that fails is told here.
 *
 * @group panels
 */
import { Check, CircleCheck, Regex } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { useFormat } from '@/composables/useFormat';
import type { BankMatchKind, BankRuleResult } from '@/lib/bankMatch';
import { tickBoxClass } from '@/lib/tick';
import {
    BankMatchError,
    fetchExamples,
    matchSummary,
    writeRule,
} from '@/lib/bankMatch';

const {
    kind,
    accountId,
    example = null,
} = defineProps<{
    /** The direction of the form: the transactions offered go the same way. */
    kind: BankMatchKind;
    /** The account the form has chosen, null for all of them. */
    accountId: number | null;
    /** A transaction to start from, already chosen (the one *Dalla banca* came from). */
    example?: App.Data.BankTransactionData | null;
}>();

const emit = defineEmits<{
    /** *Use this rule* was tapped: the rule to put in the field. */
    use: [pattern: string];
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });

const { date, money } = useFormat();

/** What the rule is written from: a transaction of the list or a pasted text. */
type Mode = 'choose' | 'paste';

const mode = ref<Mode>('choose');
const transactions = ref<App.Data.BankTransactionData[]>([]);
const chosen = ref<number[]>([]);
const pasted = ref('');
const proposal = ref<BankRuleResult | null>(null);
const problem = ref<string | null>(null);
const writing = ref(false);
/** The last opening, so an answer that arrives after the panel was closed and opened again is dropped. */
let opening = 0;

const modes = computed(() => [
    { value: 'choose', label: trans('Choose a movement') },
    { value: 'paste', label: trans('Paste the text') },
]);

/** The pasted lines that are not empty, at most five. */
const lines = computed(() =>
    pasted.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .slice(0, 5),
);

/** Whether there is something to write the rule from. */
const ready = computed(() =>
    mode.value === 'choose' ? chosen.value.length > 0 : lines.value.length > 0,
);

/** What the rule is written from, as the server asks it. */
const source = computed(() =>
    mode.value === 'choose'
        ? { transactions: chosen.value }
        : { examples: lines.value },
);

/**
 * Starts again at every opening: the example chosen, the list of transactions loaded.
 */
async function begin(): Promise<void> {
    const mine = ++opening;

    mode.value = 'choose';
    chosen.value = example === null ? [] : [example.id];
    pasted.value = '';
    proposal.value = null;
    problem.value = null;
    transactions.value = example === null ? [] : [example];

    try {
        const rows = await fetchExamples(kind, accountId);

        if (mine === opening) {
            transactions.value = [
                ...(example === null ? [] : [example]),
                ...rows.filter((row) => row.id !== example?.id),
            ];
        }
    } catch (failure) {
        if (mine === opening && failure instanceof BankMatchError) {
            problem.value = failure.message;
        }
    }
}

watch(
    open,
    (now) => {
        if (now) {
            void begin();
        }
    },
    { immediate: true },
);

/**
 * Chooses or lets go of a transaction.
 *
 * @param id - the transaction
 */
function toggle(id: number): void {
    chosen.value = chosen.value.includes(id)
        ? chosen.value.filter((one) => one !== id)
        : [...chosen.value, id].slice(-5);
}

/** Asks the AI for the rule of what is chosen or pasted. */
async function write(): Promise<void> {
    if (!ready.value || writing.value) {
        return;
    }

    const mine = opening;

    writing.value = true;
    problem.value = null;

    try {
        const answer = await writeRule(source.value, kind, accountId);

        if (mine === opening) {
            proposal.value = answer;
        }
    } catch (failure) {
        if (mine === opening) {
            proposal.value = null;
            problem.value =
                failure instanceof BankMatchError
                    ? failure.message
                    : trans('This did not work. Try again in a while.');
        }
    } finally {
        writing.value = false;
    }
}

/** Gives the rule to the field and closes. */
function use(): void {
    if (proposal.value === null) {
        return;
    }

    emit('use', proposal.value.pattern);
    open.value = false;
}

/**
 * The line under the text of a transaction: its day and month, and its account.
 *
 * @param row - the transaction
 */
function detail(row: App.Data.BankTransactionData): string {
    const day = date(row.booked_on ?? row.value_on).slice(0, 5);

    return row.account ? `${day} · ${row.account}` : day;
}
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="$t('Write it with the AI')">
        <p class="m-0 text-body-sm text-ink-soft">
            {{
                mode === 'choose'
                    ? $t(
                          'Choose one or more movements of this payment: the AI keeps the words that stay the same.',
                      )
                    : $t(
                          'Give one or more examples of this payment: the AI keeps the words that stay the same and leaves out dates, amounts and numbers.',
                      )
            }}
        </p>

        <div role="group" :aria-label="$t('Where the rule is written from')">
            <FormKit
                v-model="mode"
                type="togglebuttons"
                enforced
                :options="modes"
                @input="proposal = null"
            />
        </div>

        <ul
            v-if="mode === 'choose'"
            class="m-0 flex list-none flex-col divide-y divide-divider overflow-hidden rounded-xl border border-edge-soft p-0"
        >
            <li v-for="row in transactions" :key="row.id">
                <label
                    class="flex cursor-pointer items-center gap-3 px-3.5 py-2.5"
                >
                    <input
                        type="checkbox"
                        class="peer sr-only"
                        :checked="chosen.includes(row.id)"
                        @change="toggle(row.id)"
                    />
                    <span
                        aria-hidden="true"
                        :class="tickBoxClass(chosen.includes(row.id))"
                    >
                        <FmIcon
                            v-if="chosen.includes(row.id)"
                            :icon="Check"
                            :size="15"
                            :stroke="3"
                        />
                    </span>
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="text-body-sm wrap-anywhere text-ink">{{
                            row.text
                        }}</span>
                        <span class="text-meta text-ink-soft">{{
                            detail(row)
                        }}</span>
                    </span>
                    <span class="shrink-0 text-amount whitespace-nowrap">{{
                        money(row.amount, row.currency)
                    }}</span>
                </label>
            </li>
        </ul>

        <FormKit
            v-else
            v-model="pasted"
            type="textarea"
            rows="4"
            :label="$t('The text of the bank')"
            :help="
                $t(
                    'One line for each example. Copy it from the list of movements of the bank.',
                )
            "
        />

        <FmNotice v-if="problem" tone="danger">{{ problem }}</FmNotice>

        <div
            v-if="proposal"
            class="flex flex-col gap-2 rounded-xl bg-card-sunken p-3.5"
        >
            <span
                class="flex items-center gap-1.5 text-meta-strong text-ink-soft"
            >
                <FmIcon :icon="Regex" :size="16" />{{ $t('The rule') }}
            </span>
            <span class="text-label wrap-anywhere text-ink">{{
                proposal.pattern
            }}</span>
            <span class="text-body-sm text-ink">{{
                proposal.explanation
            }}</span>
            <span class="flex items-start gap-2 text-meta text-paid">
                <FmIcon :icon="CircleCheck" :size="16" />
                <span class="text-ink">{{ matchSummary(proposal) }}</span>
            </span>
        </div>

        <div v-if="proposal" class="flex gap-2.5">
            <FmButton variant="text" :disabled="writing" @click="write">{{
                $t('Write again')
            }}</FmButton>
            <FmButton class="grow" @click="use">{{
                $t('Use this rule')
            }}</FmButton>
        </div>
        <FmButton v-else wide :disabled="!ready || writing" @click="write">{{
            $t('Write the rule')
        }}</FmButton>
    </FmBottomSheet>
</template>
