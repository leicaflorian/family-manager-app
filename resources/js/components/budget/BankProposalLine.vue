<script setup lang="ts">
/**
 * «Maybe it is…» under a lone movement of *From the bank*, as the boards *Dalla banca* draw it: a soft blue line
 * with the sparkles, what the system thinks the movement is (the title in bold, then the day and the amount) and
 * *Yes* and *No*. An expense or income already recorded says «already recorded on…», a due date to pay says «due
 * on…», a transfer says «a transfer from (or to) the other account» by the direction of the money. The line only
 * says what was tapped (`yes`, `no`): the page sends it and the server decides what *Yes* does.
 *
 * @group rows
 */
import { Check, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import { useFormat } from '@/composables/useFormat';
import { splitAround } from '@/lib/text';

const { row, busy = false } = defineProps<{
    /** The movement, with its `proposal` (the line is only drawn for a movement that has one). */
    row: App.Data.BankTransactionData;
    /** *Yes* or *No* is being sent: both wait. */
    busy?: boolean;
}>();

const emit = defineEmits<{
    /** *Yes* was tapped. */
    yes: [row: App.Data.BankTransactionData];
    /** *No* was tapped. */
    no: [row: App.Data.BankTransactionData];
}>();

const { date, money } = useFormat();

/** The proposal of the movement. */
const proposal = computed((): App.Data.BankProposalData | null => row.proposal);

/** The sentence with the title cut out, to draw the title in bold: what comes before and after it. */
const parts = computed((): [string, string] => {
    const p = proposal.value;

    if (p === null) {
        return ['', ''];
    }

    const replacements = {
        date: date(p.date),
        amount: p.amount === null ? '' : money(p.amount, row.currency),
    };

    return splitAround(
        p.type === 'transfer'
            ? row.amount > 0
                ? 'Maybe it is a transfer from :title'
                : 'Maybe it is a transfer to :title'
            : p.type === 'deadline'
              ? 'Maybe it is: :title · due on :date · :amount'
              : p.recorded
                ? 'Maybe it is: :title · already recorded on :date · :amount'
                : 'Maybe it is: :title · :date · :amount',
        'title',
        replacements,
    );
});
</script>

<template>
    <span
        v-if="proposal"
        class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg bg-action-soft px-3 py-2.5 text-action-dark"
    >
        <span class="flex min-w-0 flex-1 basis-50 items-start gap-2">
            <Sparkles aria-hidden="true" class="mt-px size-[18px] shrink-0" />
            <span class="text-body-sm wrap-anywhere"
                >{{ parts[0]
                }}<span class="text-label">{{ proposal.title }}</span
                >{{ parts[1] }}</span
            >
        </span>
        <span class="flex shrink-0 gap-2">
            <FmButton
                row-action
                :icon="Check"
                :disabled="busy"
                @click="emit('yes', row)"
                >{{ $t('Yes') }}</FmButton
            >
            <FmButton
                variant="neutral"
                row-action
                :disabled="busy"
                @click="emit('no', row)"
                >{{ $t('No') }}</FmButton
            >
        </span>
    </span>
</template>
