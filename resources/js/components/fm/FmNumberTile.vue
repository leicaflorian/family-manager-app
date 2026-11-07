<script setup lang="ts">
/**
 * A figure of the payments of a card, as a tile beside its dates: the label,
 * the amount (or the number, as *Utilities 4*) and the line below, where the
 * page writes the amount the server names with `:amount`.
 *
 * @group figures
 */
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';

const { number } = defineProps<{
    /** The figure. */
    number: App.Data.CardNumberData;
}>();

const { euro } = useFormat();

/** The big value: the amount, else the number. */
const value = computed((): string =>
    number.amount !== null ? euro(number.amount) : String(number.count ?? ''),
);

/** The line below, with its amount written in. */
const hint = computed((): string | null =>
    number.hint === null || number.hint_amount === null
        ? number.hint
        : number.hint.replace(':amount', euro(number.hint_amount)),
);
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1 rounded-card bg-card p-4">
        <span class="text-[13px] font-semibold text-ink-soft">{{
            number.label
        }}</span>
        <span class="text-lg font-bold whitespace-nowrap text-ink">{{
            value
        }}</span>
        <span v-if="hint" class="text-sm text-ink-soft">{{ hint }}</span>
    </div>
</template>
