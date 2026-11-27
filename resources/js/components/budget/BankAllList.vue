<script setup lang="ts">
/**
 * The list of the tab *All* of *From the bank*, as the boards *Dalla banca, tutti i movimenti* draw it: every
 * transaction the bank gave with its day, its text, the amount, the tag of what it is (`BankRowState`) and the one
 * action it has: *It was not this one* on a linked one (when the server says it can be taken back) and *Put back among
 * the movements* on one set aside or marked as a transfer. On a computer a row is a grid (day, text, amount, action); on the phone the day and
 * the account go under the text and the action under the tag. The text opens the transaction as the bank gave it. The
 * last line says how long the movements not linked to an expense are kept. The list only says what the person did
 * (`open`, `undo`, `restore`): the page asks the server.
 *
 * Under a row the slot `under` receives the row: that is where the commission of a debit and the transfer between two
 * accounts are drawn by tasks 7 and 10, once the server sends them.
 *
 * @group rows
 */
import { RotateCcw, Undo2 } from '@lucide/vue';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import BankRowState from '@/components/budget/BankRowState.vue';
import FmButton from '@/components/fm/FmButton.vue';
import { dayOf } from '@/lib/banks';
import { cn } from '@/lib/utils';
import { vAutoAnimate } from '@formkit/auto-animate/vue';

const {
    rows,
    canSort,
    selected = null,
} = defineProps<{
    /** The transactions to list, in the order to show them. */
    rows: App.Data.BankTransactionData[];
    /** Whether who looks can change them (every account of the family). */
    canSort: boolean;
    /** The id of the transaction whose bank panel is open, marked in the list. */
    selected?: number | null;
}>();

const emit = defineEmits<{
    /** The text of a row was tapped: show it as the bank gave it. */
    open: [row: App.Data.BankTransactionData];
    /** *It was not this one* was tapped on a linked row. */
    undo: [row: App.Data.BankTransactionData];
    /** *Put back among the movements* was tapped on a row set aside. */
    restore: [row: App.Data.BankTransactionData];
}>();

defineSlots<{
    /** What goes under a row: the commission or the transfer it has. */
    under?: (props: { row: App.Data.BankTransactionData }) => unknown;
}>();

const wide = useWideLayout();
const { money } = useFormat();

/**
 * Whether a row has the button to take the link back.
 *
 * @param row - the transaction
 * @returns true for a linked row that the server lets undo
 */
function undoable(row: App.Data.BankTransactionData): boolean {
    return canSort && row.link?.undo_url != null;
}

/**
 * Whether a row has *Put back among the movements*: one set aside or marked as a transfer.
 *
 * @param row - the transaction
 * @returns true for a row that goes back to the movements to sort
 */
function putBack(row: App.Data.BankTransactionData): boolean {
    return row.ignored || row.status === 'transfer';
}
</script>

<template>
    <ul
        v-auto-animate
        class="m-0 flex list-none flex-col divide-y divide-divider overflow-hidden rounded-card bg-card p-0 shadow-card"
    >
        <li
            v-for="row in rows"
            :key="row.id"
            :aria-current="selected === row.id ? 'true' : undefined"
            :class="
                cn(
                    wide
                        ? 'grid grid-cols-[56px_minmax(0,1fr)_104px_auto] items-start gap-4 px-5 py-3.5'
                        : 'flex flex-col gap-2 px-4 py-3.5',
                    selected === row.id && 'bg-action-soft',
                )
            "
        >
            <span v-if="wide" class="pt-px text-label text-ink">{{
                dayOf(row)
            }}</span>
            <div :class="wide ? 'contents' : 'flex items-start gap-3'">
                <span class="flex min-w-0 grow flex-col gap-1.5">
                    <button
                        type="button"
                        class="m-0 cursor-pointer border-0 bg-transparent p-0 text-left text-body-sm wrap-anywhere text-ink"
                        @click="emit('open', row)"
                    >
                        {{ row.text }}
                    </button>
                    <span v-if="!wide" class="text-meta text-ink-soft">{{
                        `${dayOf(row)}${row.account ? ` · ${row.account}` : ''}`
                    }}</span>
                    <BankRowState :row="row" />
                    <slot name="under" :row="row" />
                </span>
                <span
                    :class="
                        cn(
                            'shrink-0 text-amount whitespace-nowrap',
                            wide && 'text-right',
                            row.pending && 'text-ink-soft',
                            row.amount > 0 && 'text-paid',
                        )
                    "
                    >{{ money(row.amount, row.currency) }}</span
                >
            </div>
            <span v-if="wide" class="flex min-h-9 justify-end">
                <FmButton
                    v-if="undoable(row)"
                    variant="text"
                    row-action
                    :icon="Undo2"
                    class="text-overdue"
                    @click="emit('undo', row)"
                    >{{ $t('It was not this one') }}</FmButton
                >
                <FmButton
                    v-else-if="canSort && putBack(row)"
                    variant="neutral"
                    row-action
                    :icon="RotateCcw"
                    @click="emit('restore', row)"
                    >{{ $t('Put back among the movements') }}</FmButton
                >
            </span>
            <div
                v-else-if="canSort && (undoable(row) || putBack(row))"
                class="flex flex-wrap items-center gap-2"
            >
                <FmButton
                    v-if="undoable(row)"
                    variant="text"
                    row-action
                    :icon="Undo2"
                    class="text-overdue"
                    @click="emit('undo', row)"
                    >{{ $t('It was not this one') }}</FmButton
                >
                <FmButton
                    v-else
                    variant="neutral"
                    row-action
                    :icon="RotateCcw"
                    @click="emit('restore', row)"
                    >{{ $t('Put back among the movements') }}</FmButton
                >
            </div>
        </li>
    </ul>
    <p class="m-0 px-1 text-meta text-ink-soft">
        {{ $t('Movements not linked to an expense are kept 12 months.') }}
    </p>
</template>
