<script setup lang="ts">
/**
 * *It is a transfer* of *From the bank*, as the board *Dalla banca, è un trasferimento* draws it: the transaction at
 * the top, the question («From which account does it come?» for a credit, «To which account does it go?» for a debit),
 * the other accounts of the family as radio buttons (the first is chosen; the one of the transaction is not offered),
 * *Add an account*, the note that a transfer does not count in the Budget and the one about the other half. The sheet
 * only says which account was chosen (`choose`); the page sends it and shows what the server answers in `error`.
 * It sits in `BankPanelFrame`: on the phone a bottom sheet, wide a panel beside the list (Escape closes it).
 *
 * @group panels
 */
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, Landmark, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BankPanelFrame from '@/components/budget/BankPanelFrame.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { useFormat } from '@/composables/useFormat';
import { bankMoney } from '@/lib/banks';
import { cn } from '@/lib/utils';
import { create } from '@/routes/cards';

const {
    transaction,
    accounts,
    error = null,
    processing = false,
    returnFocus,
} = defineProps<{
    /** The transaction to mark. */
    transaction: App.Data.BankTransactionData;
    /** The accounts of the family, with whether the bank gives their transactions. */
    accounts: { id: number; name: string; connected: boolean }[];
    /** What the server said when it refused the account, shown under the list. */
    error?: string | null;
    /** The account is being sent. */
    processing?: boolean;
    /** Where the focus goes at the closing when what opened it is gone (wide only): the row sent leaves the page. */
    returnFocus?: () => HTMLElement | null;
}>();

const emit = defineEmits<{
    /** The button under the list: the id of the account chosen. */
    choose: [accountId: number];
}>();

/** The sheet is open. */
const open = defineModel<boolean>('open', { default: false });

const { date } = useFormat();

/** Whether it is a credit: the money came from the other account. */
const income = computed(() => transaction.amount > 0);

/** The accounts offered: every one but the account of the transaction. */
const others = computed(() =>
    accounts.filter((account) => account.id !== transaction.account_id),
);

/** The id of the account the person tapped, null while the first one stands. */
const picked = ref<number | null>(null);

/** The account chosen: the one tapped if it is still offered, else the first. */
const selected = computed(
    () =>
        others.value.find((account) => account.id === picked.value) ??
        others.value[0] ??
        null,
);

// Opened again, it starts from the first account
watch(open, (now) => {
    if (now) {
        picked.value = null;
    }
});

/** Gives the account chosen. */
function confirm(): void {
    if (selected.value !== null) {
        emit('choose', selected.value.id);
    }
}
</script>

<template>
    <BankPanelFrame
        v-model:open="open"
        :title="$t('It is a transfer')"
        :return-focus="returnFocus"
    >
        <div class="flex items-start gap-3 rounded-card bg-canvas px-3.5 py-3">
            <FmIcon
                :icon="Landmark"
                :size="20"
                class="mt-0.5 text-banks-text"
            />
            <span class="flex min-w-0 grow flex-col gap-0.5">
                <span class="text-body-sm wrap-anywhere text-ink">{{
                    transaction.text
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    [
                        date(transaction.booked_on ?? transaction.value_on),
                        transaction.account,
                    ]
                        .filter(Boolean)
                        .join(' · ')
                }}</span>
            </span>
            <span
                :class="
                    cn(
                        'shrink-0 text-amount whitespace-nowrap',
                        transaction.amount > 0 && 'text-paid',
                    )
                "
                >{{ bankMoney(transaction.amount, transaction.currency) }}</span
            >
        </div>

        <fieldset class="m-0 flex min-w-0 flex-col gap-2.5 border-0 p-0">
            <legend class="mb-2.5 p-0 text-row text-ink">
                {{
                    income
                        ? $t('From which account does it come?')
                        : $t('To which account does it go?')
                }}
            </legend>
            <div
                v-if="others.length > 0"
                class="flex flex-col divide-y divide-divider overflow-hidden rounded-card border border-edge-soft"
            >
                <label
                    v-for="account in others"
                    :key="account.id"
                    class="relative flex min-h-11 cursor-pointer items-center gap-3 px-3.5 py-3"
                >
                    <input
                        type="radio"
                        name="account"
                        class="peer sr-only"
                        :checked="selected?.id === account.id"
                        @change="picked = account.id"
                    />
                    <span
                        aria-hidden="true"
                        :class="
                            cn(
                                'box-border size-[22px] shrink-0 rounded-full bg-card peer-focus-visible:ring-2 peer-focus-visible:ring-action peer-focus-visible:ring-offset-2',
                                selected?.id === account.id
                                    ? 'border-[7px] border-action'
                                    : 'border-[1.5px] border-edge',
                            )
                        "
                    />
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="text-body-strong text-ink">{{
                            account.name
                        }}</span>
                        <span class="text-meta text-ink-soft">{{
                            account.connected
                                ? $t('Linked to the bank')
                                : $t('Not linked to the bank')
                        }}</span>
                    </span>
                </label>
            </div>
            <p
                v-if="others.length === 0"
                class="m-0 text-body-sm text-ink-soft"
            >
                {{ $t('You have no other account yet.') }}
            </p>
            <Link
                :href="create({ query: { card: 'financial_account' } }).url"
                class="inline-flex min-h-11 items-center gap-2 self-start text-body text-action"
            >
                <FmIcon :icon="Plus" :size="20" />
                {{ $t('Add an account') }}
            </Link>
        </fieldset>

        <div
            class="flex items-start gap-2.5 rounded-card bg-banks-soft px-3.5 py-3"
        >
            <FmIcon
                :icon="ArrowLeftRight"
                :size="20"
                class="mt-0.5 shrink-0 text-banks-text"
            />
            <span class="text-body-sm text-ink">{{
                $t(
                    'A transfer is not an expense or an income: it does not count in the Budget.',
                )
            }}</span>
        </div>
        <p class="m-0 text-meta text-ink-soft">
            {{
                $t(
                    'If the other account is linked to the bank too, the two halves are sorted together: once this one is marked, the other becomes a transfer by itself.',
                )
            }}
        </p>

        <p v-if="error" role="alert" class="m-0 text-body-sm text-overdue">
            {{ error }}
        </p>

        <div class="flex justify-end gap-2.5">
            <FmButton variant="text" @click="open = false">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton
                class="grow"
                :disabled="selected === null || processing"
                @click="confirm"
                >{{ $t('Mark as a transfer') }}</FmButton
            >
        </div>
    </BankPanelFrame>
</template>
