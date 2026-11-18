<script setup lang="ts">
/**
 * An amount of the budget: «84,20 €», an estimate as «~72,00 €», a changed
 * amount with the old one struck through, an income as «+1.720,00 €» in green,
 * and under an amount that includes a commission «of which 1,00 € commission».
 *
 * @group figures
 */
import { useFormat } from '@/composables/useFormat';

const {
    amount,
    estimated = false,
    base = null,
    income = false,
    fee = null,
} = defineProps<{
    /** The amount. */
    amount: number;
    /** The amount is an estimate. */
    estimated?: boolean;
    /** The amount before an exception changed it; it is shown struck through. */
    base?: number | null;
    /** The amount comes in: a plus and the colour of what is paid. */
    income?: boolean;
    /** The commission the amount includes; above zero it is written under it. */
    fee?: number | null;
}>();

const { euro } = useFormat();
</script>

<template>
    <span
        :class="
            fee !== null && fee > 0
                ? 'inline-flex flex-col items-end gap-0.5'
                : 'contents'
        "
    >
        <span class="inline-flex items-baseline gap-1.5 whitespace-nowrap">
            <s
                v-if="base !== null"
                class="text-[0.85em] font-normal text-ink-soft"
                >{{ euro(base) }}</s
            >
            <span :class="income ? 'text-paid' : undefined">{{
                `${estimated ? '~' : ''}${income ? '+' : ''}${euro(amount)}`
            }}</span>
        </span>
        <span
            v-if="fee !== null && fee > 0"
            class="text-meta whitespace-nowrap text-ink-soft"
            >{{ $t('of which :fee commission', { fee: euro(fee) }) }}</span
        >
    </span>
</template>
