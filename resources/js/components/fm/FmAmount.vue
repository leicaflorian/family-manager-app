<script setup lang="ts">
/**
 * An amount of the budget: «84,20 €», an estimate as «~72,00 €», a changed
 * amount with the old one struck through, an income as «+1.720,00 €» in green.
 */
import { useFormat } from '@/composables/useFormat';

const {
    amount,
    estimated = false,
    base = null,
    income = false,
} = defineProps<{
    /** The amount. */
    amount: number;
    /** The amount is an estimate. */
    estimated?: boolean;
    /** The amount before an exception changed it; it is shown struck through. */
    base?: number | null;
    /** The amount comes in: a plus and the colour of what is paid. */
    income?: boolean;
}>();

const { euro } = useFormat();
</script>

<template>
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
</template>
