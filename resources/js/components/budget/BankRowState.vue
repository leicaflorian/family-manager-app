<script setup lang="ts">
/**
 * What a bank transaction is, in one line, as the boards *Dalla banca, tutti i movimenti* draw it: the tag
 * (`BankStatusTag`) and next to it what goes with the status: the expense or income it is linked to as a link, with how
 * the link came («from the filter Satispay», «already recorded, now linked»), the way to sort a transaction left to
 * sort, or what a pending one waits for. Used under each row of the tab *All* and in the panel of the transaction as the
 * bank gave it.
 *
 * Commissions and transfers: their tag is drawn here (the server does not send them yet), and the words that go with
 * them («of «Satispay recharge»», «to Buddy Bank ·· 5521») are added in this component by the tasks that send their
 * data (7 and 10), in the `fee` and `transfer` cases of `detail`.
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

/** What goes with the tag: the kind of words it needs, or nothing. */
const detail = computed((): 'link' | 'sort' | 'pending' | null => {
    switch (row.status) {
        case 'linked':
            return row.link === null ? null : 'link';
        case 'unsorted':
            return 'sort';
        case 'pending':
            return 'pending';
        default:
            // `fee`, `transfer` and `ignored`: nothing yet (see the TSDoc above)
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
            v-else-if="detail === 'pending'"
            class="text-meta text-ink-soft"
            >{{ $t('It is sorted when the bank books it') }}</span
        >
    </div>
</template>
