<script setup lang="ts">
/**
 * The transaction as the bank gave it, read only, as the boards *Dalla banca, il movimento* draw it: the full text
 * of the bank, who pays (or is paid), the booking and the value date, the amount with its currency, the account, the
 * status at the bank, the code of the bank and when it was read; under them what the transaction is in the app (the tag,
 * the movement it is linked to) and, for a linked one the server lets undo, *It was not this one*. It sits in
 * `BankPanelFrame`: on the phone a bottom sheet; wide, a panel in the column the page gives it, which Escape closes,
 * never taller than the screen. The panel only says what the person did (`close`, `undo`).
 *
 * @group panels
 */
import { Undo2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import BankPanelFrame from '@/components/budget/BankPanelFrame.vue';
import BankRowState from '@/components/budget/BankRowState.vue';
import { useAppName } from '@/composables/useAppName';
import { useFormat } from '@/composables/useFormat';
import { bankMoney } from '@/lib/banks';
import { cn } from '@/lib/utils';

const {
    row,
    canSort = true,
    returnFocus,
} = defineProps<{
    /** The transaction to show. */
    row: App.Data.BankTransactionData;
    /** Whether who looks can take a link back. */
    canSort?: boolean;
    /** Where the focus goes at the closing when what opened the panel is gone (wide only). */
    returnFocus?: () => HTMLElement | null;
}>();

const emit = defineEmits<{
    /** The panel was closed. */
    close: [];
    /** *It was not this one* was tapped. */
    undo: [row: App.Data.BankTransactionData];
}>();

const appName = useAppName();
const { date } = useFormat();

/** The title of the panel. */
const title = computed((): string => trans('Bank transaction'));

/** The status at the bank in words. */
const bankStatus = computed((): string =>
    row.bank_status === 'booked' ? trans('Booked') : trans('Pending'),
);

/** When the transaction was read, «27/11/2026 at 07:00». */
const readAt = computed((): string =>
    trans(':date at :time', {
        date: date(row.read_at.slice(0, 10)),
        time: row.read_at.slice(11, 16),
    }),
);

/** One line under the text. */
interface Line {
    /** What the line is about. */
    term: string;
    /** What the bank says, empty when it says nothing (the line is left out). */
    value: string;
    /** The value is the amount, drawn with its colour and the currency. */
    amount?: boolean;
}

/** The lines under the text in the order of the boards; the ones the bank leaves empty are left out where they are. */
const lines = computed((): Line[] =>
    (
        [
            {
                term: row.amount > 0 ? trans('Who pays') : trans('Who is paid'),
                value: row.counterparty ?? '',
            },
            {
                term: trans('Booking date'),
                value: row.booked_on ? date(row.booked_on) : '',
            },
            {
                term: trans('Value date'),
                value: row.value_on ? date(row.value_on) : '',
            },
            {
                term: trans('Amount'),
                value: bankMoney(row.amount, row.currency),
                amount: true,
            },
            { term: trans('Bank account'), value: row.account ?? '' },
            { term: trans('Status at the bank'), value: bankStatus.value },
            { term: trans('Bank code'), value: row.provider_id },
            { term: trans('Read'), value: readAt.value },
        ] satisfies Line[]
    ).filter((line) => line.value !== ''),
);
</script>

<template>
    <BankPanelFrame
        :open="true"
        :title="title"
        :return-focus="returnFocus"
        @update:open="(open: boolean) => !open && emit('close')"
    >
        <p class="m-0 text-body-sm text-ink-soft">
            {{ $t('As the bank gave it, read only.') }}
        </p>

        <div class="flex flex-col gap-1.5">
            <span class="text-meta text-ink-soft">{{
                $t('Text of the bank')
            }}</span>
            <span
                class="rounded-card bg-card-sunken px-3.5 py-3 text-body-sm wrap-anywhere text-ink"
                >{{ row.text }}</span
            >
        </div>

        <dl class="m-0 flex flex-col">
            <div
                v-for="line in lines"
                :key="line.term"
                class="grid grid-cols-[132px_minmax(0,1fr)] gap-3 border-b border-divider py-2.5"
            >
                <dt class="text-meta text-ink-soft">{{ line.term }}</dt>
                <dd class="m-0 text-body-sm wrap-anywhere text-ink">
                    <template v-if="line.amount">
                        <span
                            :class="
                                cn('text-label', row.amount > 0 && 'text-paid')
                            "
                            >{{ line.value }}</span
                        >
                        · {{ row.currency }}
                    </template>
                    <template v-else>{{ line.value }}</template>
                </dd>
            </div>
        </dl>

        <div class="flex flex-col gap-2 rounded-card bg-banks-soft px-3.5 py-3">
            <span class="text-meta text-ink-soft">{{
                $t('In :app', { app: appName })
            }}</span>
            <BankRowState :row="row" />
            <button
                v-if="canSort && row.link?.undo_url"
                type="button"
                class="inline-flex h-touch cursor-pointer items-center gap-2 self-start border-0 bg-transparent p-0 text-body text-overdue"
                @click="emit('undo', row)"
            >
                <Undo2 aria-hidden="true" :size="20" />{{
                    $t('It was not this one')
                }}
            </button>
        </div>
    </BankPanelFrame>
</template>
