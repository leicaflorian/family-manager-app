<script setup lang="ts">
/**
 * The small tag that says what a bank transaction is in *From the bank* (the tab *All* and the transaction as the bank
 * gave it), with the colours of the boards *Dalla banca, tutti i movimenti*: blue for the ones left to sort, green for
 * the ones linked to an expense or an income and for a commission, the colour of the banks for a transfer, grey for the
 * ones set aside and amber for the ones the bank has not booked yet.
 *
 * @group panels
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const { status, kind = 'expense' } = defineProps<{
    /** What the transaction is (`BankRowStatus`). */
    status: App.Enums.BankRowStatus | string;
    /** For a linked transaction, whether its movement is an `expense` or an `income`. */
    kind?: string;
}>();

/** The words and the colours of each status. */
const look = computed((): { label: string; classes: string } => {
    switch (status) {
        case 'linked':
            return {
                label:
                    kind === 'income'
                        ? trans('An income')
                        : trans('An expense'),
                classes: cn('bg-paid-soft text-paid'),
            };
        case 'fee':
            return {
                label: trans('Commission'),
                classes: cn('bg-paid-soft text-paid'),
            };
        case 'transfer':
            return {
                label: trans('Transfer'),
                classes: cn('bg-banks-soft text-banks-text'),
            };
        case 'ignored':
            return {
                label: trans('Set aside'),
                classes: cn('bg-card-sunken text-ink-soft'),
            };
        case 'pending':
            return {
                label: trans('Pending at the bank'),
                classes: cn('bg-warning-soft text-warning'),
            };
        default:
            return {
                label: trans('Left to sort'),
                classes: cn('bg-action-soft text-action-dark'),
            };
    }
});
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex shrink-0 items-center rounded-tag px-2 py-[3px] text-meta-strong whitespace-nowrap',
                look.classes,
            )
        "
        >{{ look.label }}</span
    >
</template>
