<script setup lang="ts">
/**
 * A figure of a card, as a tile beside its dates: the label, the amount (or
 * the number, as *Utilities 4*, or a word such as the status, coloured by its
 * tone) and the line below, where the page writes the amount the server names
 * with `:amount`. `inset` drops the card around it, for a figure inside the box
 * of the figures.
 *
 * @group figures
 */
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { cn } from '@/lib/utils';

const { number, inset = false } = defineProps<{
    /** The figure. */
    number: App.Data.CardNumberData;
    /** Inside the box of the figures: no card of its own. */
    inset?: boolean;
}>();

/** The colour of a word figure for each tone: green open, red overdue, grey closed. */
const toneColor: Record<
    NonNullable<App.Data.CardNumberData['tone']>,
    string
> = {
    open: cn('text-paid'),
    overdue: cn('text-overdue'),
    closed: cn('text-ink-soft'),
};

const { euro } = useFormat();

/** The big value: the amount, else the word, else the number. */
const value = computed((): string =>
    number.amount !== null
        ? euro(number.amount)
        : (number.text ?? String(number.count ?? '')),
);

/** The line below, with its amount written in. */
const hint = computed((): string | null =>
    number.hint === null || number.hint_amount === null
        ? number.hint
        : number.hint.replace(':amount', euro(number.hint_amount)),
);
</script>

<template>
    <div
        class="flex min-w-0 flex-col gap-1"
        :class="!inset && 'rounded-card bg-card p-4 shadow-card'"
    >
        <span class="text-meta-strong text-ink-soft">{{ number.label }}</span>
        <span
            class="text-subtitle whitespace-nowrap"
            :class="number.tone ? toneColor[number.tone] : 'text-ink'"
            >{{ value }}</span
        >
        <span v-if="hint" class="text-body-sm text-ink-soft">{{ hint }}</span>
    </div>
</template>
