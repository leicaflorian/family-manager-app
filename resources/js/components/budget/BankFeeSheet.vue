<script setup lang="ts">
/**
 * *It is the commission of…* of *From the bank*, as the boards *Dalla banca, è la commissione di* draw it: the sheet
 * of a commission the bank gave with no direct debit to sort. It is `BankAssignSheet` in its commission form (only the
 * recorded expenses, the ones of the same organization first, and the line that says the new amount and the fee); this
 * only says which expense was chosen (`choose`), the page sends it and shows what the server answers in `error`.
 *
 * @group panels
 */
import BankAssignSheet from '@/components/budget/BankAssignSheet.vue';

const {
    transaction,
    error = null,
    processing = false,
} = defineProps<{
    /** The commission left alone. */
    transaction: App.Data.BankTransactionData;
    /** What the server said when it refused the expense, shown under the list. */
    error?: string | null;
    /** The choice is being sent. */
    processing?: boolean;
}>();

const emit = defineEmits<{
    /** The button under the list: the id of the expense chosen. */
    choose: [expenseId: number];
}>();

/** The sheet is open. */
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <BankAssignSheet
        v-model:open="open"
        fee
        :transaction="transaction"
        :error="error"
        :processing="processing"
        @assign="
            (choice) =>
                'movement_id' in choice && emit('choose', choice.movement_id)
        "
    />
</template>
