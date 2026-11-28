<script setup lang="ts">
/**
 * The commission that goes with a direct debit, under the text of the movement, as the boards *Dalla banca* draw it:
 * an arrow, the amount and the day it was booked («+ 0,40 € commission, 25/11»). Used by the lone movements and by
 * the movements of a group.
 *
 * @group rows
 */
import { CornerDownRight } from '@lucide/vue';
import { useFormat } from '@/composables/useFormat';
import { dayOf } from '@/lib/banks';

const { fee, currency } = defineProps<{
    /** The commission, as the server sends it. */
    fee: App.Data.BankFeeData;
    /** The currency of the movement. */
    currency: string;
}>();

const { money } = useFormat();
</script>

<template>
    <span class="flex items-center gap-1.5 text-meta text-ink-soft"
        ><CornerDownRight aria-hidden="true" class="size-3.5 shrink-0" />{{
            $t('+ :fee commission, :day', {
                fee: money(Math.abs(fee.amount), currency),
                day: dayOf({ booked_on: fee.booked_on, value_on: null }),
            })
        }}</span
    >
</template>
