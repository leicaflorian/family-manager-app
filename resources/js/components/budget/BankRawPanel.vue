<script setup lang="ts">
/**
 * The transaction as the bank gave it, read only, as the boards *Dalla banca, il movimento* draw it: the full text
 * of the bank, who pays (or is paid), the booking and the value date, the amount with its currency, the account, the
 * status at the bank, the code of the bank and when it was read; under them what the transaction is in the app (the tag,
 * the movement it is linked to) and, for a linked one the server lets undo, *It was not this one*. On the phone it is a
 * bottom sheet; wide, a panel in the page at the right of the list (the page puts it in its column). The panel only says
 * what the person did (`close`, `undo`).
 *
 * @group panels
 */
import { Undo2, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import BankRowState from '@/components/budget/BankRowState.vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import { useAppName } from '@/composables/useAppName';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import { cn } from '@/lib/utils';

const { row, canSort = true } = defineProps<{
    /** The transaction to show. */
    row: App.Data.BankTransactionData;
    /** Whether who looks can take a link back. */
    canSort?: boolean;
}>();

const emit = defineEmits<{
    /** The panel was closed. */
    close: [];
    /** *It was not this one* was tapped. */
    undo: [row: App.Data.BankTransactionData];
}>();

const wide = useWideLayout();
const appName = useAppName();
const { money, date } = useFormat();

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

/** The lines under the text: what the bank says, in the order of the boards. */
const lines = computed((): { term: string; value: string; mono?: boolean }[] =>
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
        { term: trans('Status at the bank'), value: bankStatus.value },
        { term: trans('Bank code'), value: row.provider_id, mono: true },
        { term: trans('Read'), value: readAt.value },
    ].filter((line) => line.value !== ''),
);
</script>

<template>
    <component
        :is="wide ? 'aside' : FmBottomSheet"
        v-bind="
            wide
                ? {
                      role: 'dialog',
                      'aria-label': title,
                      class: 'flex flex-col gap-3.5 rounded-sheet bg-card px-6 pt-5 pb-6 shadow-card',
                  }
                : {
                      open: true,
                      title,
                      'onUpdate:open': (open: boolean) =>
                          !open && emit('close'),
                  }
        "
    >
        <div v-if="wide" class="flex items-center justify-between gap-3">
            <h2 class="m-0 text-subtitle text-ink">{{ title }}</h2>
            <button
                type="button"
                :aria-label="$t('Close')"
                class="flex size-11 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-ink-soft"
                @click="emit('close')"
            >
                <X aria-hidden="true" :size="20" />
            </button>
        </div>
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
                v-for="line in lines.slice(0, 3)"
                :key="line.term"
                class="grid grid-cols-[132px_minmax(0,1fr)] gap-3 border-b border-divider py-2.5"
            >
                <dt class="text-meta text-ink-soft">{{ line.term }}</dt>
                <dd class="m-0 text-body-sm wrap-anywhere text-ink">
                    {{ line.value }}
                </dd>
            </div>
            <div
                class="grid grid-cols-[132px_minmax(0,1fr)] gap-3 border-b border-divider py-2.5"
            >
                <dt class="text-meta text-ink-soft">{{ $t('Amount') }}</dt>
                <dd class="m-0 text-body-sm text-ink">
                    <span :class="cn(row.amount > 0 && 'text-paid')">{{
                        money(row.amount, row.currency)
                    }}</span>
                    · {{ row.currency }}
                </dd>
            </div>
            <div
                v-if="row.account"
                class="grid grid-cols-[132px_minmax(0,1fr)] gap-3 border-b border-divider py-2.5"
            >
                <dt class="text-meta text-ink-soft">{{ $t('Account') }}</dt>
                <dd class="m-0 text-body-sm wrap-anywhere text-ink">
                    {{ row.account }}
                </dd>
            </div>
            <div
                v-for="line in lines.slice(3)"
                :key="line.term"
                class="grid grid-cols-[132px_minmax(0,1fr)] gap-3 border-b border-divider py-2.5"
            >
                <dt class="text-meta text-ink-soft">{{ line.term }}</dt>
                <dd
                    :class="
                        cn(
                            'm-0 text-body-sm wrap-anywhere text-ink',
                            line.mono && 'font-mono',
                        )
                    "
                >
                    {{ line.value }}
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
    </component>
</template>
