<script setup lang="ts">
/**
 * *Movement taken out*, in the form of a bank filter after *It is not of this group*, as the board
 * *Dalla-banca-filtro-escludi* draws it: where it was taken out from (the group, which it never comes back to, or the
 * filter), the movement with the word that leaves it out marked, and «What is this movement?» with four choices:
 * *It belongs to an expense* (the search panel of *From the bank*), *Create the expense* (the form of a new movement
 * from it), *It is a transfer* (the sheet of the other account) and *Ignore*. The choices are sent without leaving
 * the form, so the filter being written stays; once one worked the box says so.
 */
import { ArrowLeftRight, EyeOff, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import BankAssignSheet from '@/components/budget/BankAssignSheet.vue';
import BankTransferSheet from '@/components/budget/BankTransferSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import { useFormat } from '@/composables/useFormat';
import type { BankAssignChoice } from '@/lib/bankAssign';
import { BankSortError, sendBankAction } from '@/lib/bankFilters';
import { formatDayMonth } from '@/lib/format';
import { highlightParts } from '@/lib/highlight';
import { trans } from 'laravel-vue-i18n';
import { assign, ignore, transfer } from '@/routes/budget/bank';
import { create } from '@/routes/budget/movements';

const {
    transaction,
    group = null,
    word = null,
    accounts,
    error = null,
} = defineProps<{
    /** The movement taken out. */
    transaction: App.Data.BankTransactionData;
    /** The name of the group it left for good, null when the filter does not come from a group. */
    group?: string | null;
    /** The word that leaves it out of the filter now, marked in its text. */
    word?: string | null;
    /** The open accounts of the family, for *It is a transfer*. */
    accounts: { id: number; name: string; connected: boolean }[];
    /** Why the movement could not leave its group, null when it could. */
    error?: string | null;
}>();

const { money } = useFormat();

/** Whether it is a credit: it belongs to an income. */
const income = computed((): boolean => transaction.amount > 0);

/** The day and the account, as «15/11 · Conto BancoPosta ·· 3307». */
const where = computed((): string => {
    const day = transaction.booked_on ?? transaction.value_on;

    return [day ? formatDayMonth(day) : null, transaction.account]
        .filter((piece): piece is string => piece !== null && piece !== '')
        .join(' · ');
});

/** One of the choices worked: the movement is sorted. */
const done = ref(false);

/** A choice is being sent. */
const processing = ref(false);

/** What the server said when it refused a choice. */
const failure = ref<string | null>(null);

/** The panel *It belongs to an expense* is open. */
const assignOpen = ref(false);

/** The sheet *It is a transfer* is open. */
const transferOpen = ref(false);

/**
 * Sends a choice; on success the sheets close and the box says the movement is sorted.
 *
 * @param url - the address of the action
 * @param data - the body
 */
async function send(
    url: string,
    data: Record<string, unknown> = {},
): Promise<void> {
    processing.value = true;
    failure.value = null;

    try {
        await sendBankAction(url, data);
        done.value = true;
        assignOpen.value = false;
        transferOpen.value = false;
    } catch (error) {
        failure.value =
            error instanceof BankSortError
                ? error.message
                : trans('This did not work. Try again in a while.');
    } finally {
        processing.value = false;
    }
}

/**
 * The choice of the panel *It belongs to an expense*.
 *
 * @param choice - the due date or the movement chosen
 */
function assignTo(choice: BankAssignChoice): void {
    void send(assign.url(transaction.id), choice);
}

/**
 * The account of the sheet *It is a transfer*.
 *
 * @param accountId - the account on the other side
 */
function markTransfer(accountId: number): void {
    void send(transfer.url(transaction.id), { account_id: accountId });
}
</script>

<template>
    <section
        :aria-label="$t('Movement taken out')"
        class="flex flex-col gap-3 rounded-card border-[1.5px] border-edge-soft bg-card p-4 shadow-card"
    >
        <div class="flex items-center justify-between gap-2">
            <span
                class="inline-flex self-start rounded-tag bg-card-sunken px-2 py-[3px] text-meta-strong text-ink-soft"
                >{{
                    group
                        ? $t('Taken out of the group :name', { name: group })
                        : $t('Taken out of the filter')
                }}</span
            >
            <span v-if="group" class="text-meta text-ink-soft">{{
                $t('It does not come back')
            }}</span>
        </div>
        <div
            class="flex items-start gap-3 rounded-xl bg-card-sunken px-3.5 py-3"
        >
            <span class="flex min-w-0 grow flex-col gap-0.5">
                <span class="text-meta-strong text-ink-soft">{{ where }}</span>
                <span class="text-body-sm wrap-anywhere text-ink"
                    ><template
                        v-for="(part, index) in highlightParts(
                            transaction.text,
                            word ?? '',
                        )"
                        :key="index"
                        ><mark
                            v-if="part.match"
                            class="rounded-xs bg-overdue-soft px-0.5 text-label text-overdue"
                            >{{ part.text }}</mark
                        ><template v-else>{{ part.text }}</template></template
                    ></span
                >
            </span>
            <span class="shrink-0 text-amount whitespace-nowrap">{{
                money(transaction.amount, transaction.currency)
            }}</span>
        </div>
        <p v-if="error" role="alert" class="m-0 text-body-sm text-overdue">
            {{ error }}
        </p>
        <p v-if="done" role="status" class="m-0 text-row text-paid">
            {{ $t('Done: the movement is sorted.') }}
        </p>
        <template v-else>
            <span class="text-row text-ink">{{
                $t('What is this movement?')
            }}</span>
            <div class="grid grid-cols-2 gap-2">
                <FmButton
                    variant="neutral"
                    row-action
                    class="min-w-0 justify-start"
                    :icon="Search"
                    :disabled="processing"
                    @click="assignOpen = true"
                    >{{
                        income
                            ? $t('It belongs to an income')
                            : $t('It belongs to an expense')
                    }}</FmButton
                >
                <FmButton
                    variant="neutral"
                    row-action
                    class="min-w-0 justify-start"
                    :icon="Plus"
                    :href="create.url({ query: { bank: transaction.id } })"
                    >{{
                        income
                            ? $t('Create the income')
                            : $t('Create the expense')
                    }}</FmButton
                >
                <FmButton
                    variant="neutral"
                    row-action
                    class="min-w-0 justify-start"
                    :icon="ArrowLeftRight"
                    :disabled="processing"
                    @click="transferOpen = true"
                    >{{ $t('It is a transfer') }}</FmButton
                >
                <FmButton
                    variant="neutral"
                    row-action
                    class="min-w-0 justify-start"
                    :icon="EyeOff"
                    :disabled="processing"
                    @click="send(ignore.url(transaction.id))"
                    >{{ $t('Ignore') }}</FmButton
                >
            </div>
            <p
                v-if="failure"
                role="alert"
                class="m-0 text-body-sm text-overdue"
            >
                {{ failure }}
            </p>
        </template>
        <BankAssignSheet
            v-model:open="assignOpen"
            :transaction="transaction"
            :error="failure"
            :processing="processing"
            @assign="assignTo"
        />
        <BankTransferSheet
            v-model:open="transferOpen"
            :transaction="transaction"
            :accounts="accounts"
            :error="failure"
            :processing="processing"
            @choose="markTransfer"
        />
    </section>
</template>
