<script setup lang="ts">
/**
 * The total to pay and the cost of a loan under its instalment in the form,
 * as the server works them out from what is written so far. A figure that
 * cannot be worked out yet is a dash.
 */
import { useFormat } from '@/composables/useFormat';

const { figures } = defineProps<{
    /** The figures from the server, or null until they come. */
    figures: App.Data.LoanFiguresData | null;
}>();

const { euro } = useFormat();

/**
 * A figure as the person reads it.
 *
 * @param amount - the amount, or nothing
 * @returns the amount written, or a dash
 */
function written(amount: number | null | undefined): string {
    return amount === null || amount === undefined ? '—' : euro(amount);
}
</script>

<template>
    <dl
        class="grid grid-cols-2 gap-3 rounded-2xl bg-canvas px-4 py-3"
        data-testid="loan-figures"
    >
        <div class="flex flex-col gap-0.5">
            <dt class="text-xs text-ink-soft">{{ $t('Total to pay') }}</dt>
            <dd class="text-base font-semibold text-ink">
                {{ written(figures?.total_to_pay) }}
            </dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="text-xs text-ink-soft">{{ $t('Cost') }}</dt>
            <dd class="text-base font-semibold text-ink">
                {{ written(figures?.cost) }}
            </dd>
        </div>
    </dl>
</template>
