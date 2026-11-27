<script setup lang="ts">
/**
 * What a bank transaction is, in one line, as the boards *Dalla banca, tutti i movimenti* draw it: the tag
 * (`BankStatusTag`) and next to it what goes with the status: the expense or income it is linked to as a link, with how
 * the link came («from the filter Satispay», «already recorded, now linked»), the way to sort a transaction left to
 * sort, or what a pending one waits for. Used under each row of the tab *All* and in the panel of the transaction as the
 * bank gave it.
 *
 * A transfer says which account the money went to or came from («to Buddy Bank ·· 5521») and, when the other half is
 * on the list too, «with the other half». The words of a commission («of «Satispay recharge»») are added in the `fee`
 * case of `detail` by the task that sends its data.
 *
 * @group panels
 */
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import BankStatusTag from '@/components/budget/BankStatusTag.vue';
import { index as bankIndex } from '@/routes/budget/bank';

const { row } = defineProps<{
    /** The transaction. */
    row: App.Data.BankTransactionData;
}>();

/** How the link came, in words; empty for a transaction that is not linked. */
const how = computed((): string => {
    const link = row.link;

    if (link === null) {
        return '';
    }

    switch (link.how) {
        case 'rule':
            return link.filter_name
                ? trans('from the filter :name', { name: link.filter_name })
                : trans('from a rule');
        case 'assigned':
            return trans('chosen by hand');
        case 'created':
            return trans('created from here');
        default:
            return trans('already recorded, now linked');
    }
});

/** Marks where the account goes in the words of a transfer, to draw it apart from the rest. */
const ACCOUNT = '\u0000';

/** The words around the account of a transfer: «to :account» or «from :account». */
const transferWords = computed((): [string, string] => {
    const text =
        row.transfer?.direction === 'to'
            ? trans('to :account', { account: ACCOUNT })
            : trans('from :account', { account: ACCOUNT });
    const [before = '', after = ''] = text.split(ACCOUNT);

    return [before, after];
});

/** What goes with the tag: the kind of words it needs, or nothing. */
const detail = computed((): 'link' | 'sort' | 'pending' | 'transfer' | null => {
    switch (row.status) {
        case 'linked':
            return row.link === null ? null : 'link';
        case 'unsorted':
            return 'sort';
        case 'pending':
            return 'pending';
        case 'transfer':
            return row.transfer === null ? null : 'transfer';
        default:
            // `fee` and `ignored`: nothing yet (see the TSDoc above)
            return null;
    }
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
        <BankStatusTag :status="row.status" :kind="row.link?.kind" />
        <span
            v-if="detail === 'link' && row.link"
            class="min-w-0 text-meta text-ink-soft"
        >
            <Link
                :href="row.link.url"
                class="text-action hover:text-action-dark"
                >«{{ row.link.title }}»</Link
            >
            · {{ how }}
        </span>
        <span v-else-if="detail === 'sort'" class="text-meta text-ink-soft">
            <Link
                :href="bankIndex().url"
                class="text-action hover:text-action-dark"
                >{{ $t('Sort it') }}</Link
            >
        </span>
        <span
            v-else-if="detail === 'transfer' && row.transfer"
            class="min-w-0 text-meta text-ink-soft"
            >{{ transferWords[0]
            }}<span class="text-label text-ink">{{ row.transfer.account }}</span
            >{{ transferWords[1]
            }}<template v-if="row.transfer.paired">
                · {{ $t('with the other half') }}</template
            ></span
        >
        <span
            v-else-if="detail === 'pending'"
            class="text-meta text-ink-soft"
            >{{ $t('It is sorted when the bank books it') }}</span
        >
    </div>
</template>
