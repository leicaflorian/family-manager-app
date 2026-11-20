<script setup lang="ts">
/**
 * *Budget → From the bank*, as the boards *Dalla banca* and *Dalla banca-pc* draw it: the transactions the bank
 * gave that no fixed expense recognised, newest first, 50 at a time and the next ones while scrolling. A pending
 * transaction says so and waits for the bank to book it. *To sort* and *Ignored* are two filters of the same list;
 * *Update* reads the banks now (the server says how many movements are new) and under it comes the last read and
 * the hours of the automatic ones. A bank that has to be connected again has a strip with *Reconnect* for who can
 * change the banks. The row actions (create the expense, link a fixed expense, ignore) come with their own routes.
 */
import { Head, router, WhenVisible } from '@inertiajs/vue3';
import { Landmark, Plus, RefreshCw } from '@lucide/vue';
import { vAutoAnimate } from '@formkit/auto-animate/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmChip from '@/components/fm/FmChip.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmTitleTools from '@/components/fm/FmTitleTools.vue';
import { useBankFeed } from '@/composables/useBankFeed';
import { useFormat } from '@/composables/useFormat';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { lastReadText } from '@/lib/banks';
import { budgetTabs } from '@/lib/budgetTabs';
import { listNames } from '@/lib/names';
import { cn } from '@/lib/utils';
import { index as bankIndex, sync } from '@/routes/budget/bank';
import { create } from '@/routes/budget/movements';
import { index as banksIndex } from '@/routes/banks';

const props = defineProps<{
    /** Which filter is open: the transactions to sort or the ones put aside. */
    show: 'unsorted' | 'ignored';
    /** The blocks of the list loaded so far. */
    rows: App.Data.BankTransactionData[];
    /** The number of the next block of the list, null at the last one. */
    next: number | null;
    /** The banks that have to be connected again. */
    banks_to_reconnect: string[];
    /** Whether who looks can sort the transactions (every account of the family). */
    can_sort: boolean;
    /** Whether who looks can connect the banks again (an administrator). */
    can_change_banks: boolean;
    /** When a bank was read last (`yyyy-mm-ddThh:mm`, Rome); null before the first read. */
    last_synced_at: string | null;
    /** The hours of the day the banks are read by themselves. */
    sync_hours: number[];
}>();

const mode = useLayoutMode();
const bankFeed = useBankFeed();
const { euro, date } = useFormat();

/** An update is running: the button waits, so a double tap does not read the banks twice. */
const updating = ref(false);

/** The number still to sort, from the shared props so it follows an update. */
const unsorted = computed((): number => bankFeed.value?.unsorted ?? 0);

/** The line under the buttons: the last read and when the next ones come by themselves. */
const readLine = computed((): string => {
    const times = listNames(props.sync_hours.map((hour) => String(hour)));

    return props.last_synced_at
        ? trans(':update · they arrive by themselves at :times', {
              update: lastReadText(props.last_synced_at),
              times,
          })
        : trans('They arrive by themselves at :times', { times });
});

/**
 * The amount of a transaction as the bank wrote it: in euros the way the app writes them, in another currency with its code.
 *
 * @param row - the transaction
 */
function amountText(row: App.Data.BankTransactionData): string {
    return row.currency === 'EUR'
        ? euro(row.amount)
        : `${new Intl.NumberFormat('it-IT', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          }).format(row.amount)} ${row.currency}`;
}

/** The day a row is shown with: the booked one, for a pending transaction the one the bank gave. */
function dayOf(row: App.Data.BankTransactionData): string {
    return date(row.booked_on ?? row.value_on);
}

/** What the next block of the list asks for, in the address of the page. */
const nextData = computed(() => ({
    page: props.next,
    ...(props.show === 'ignored' ? { show: 'ignored' } : {}),
}));

/** Reads the banks now. */
function update(): void {
    if (updating.value) {
        return;
    }

    router.post(
        sync().url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                updating.value = true;
            },
            onFinish: () => {
                updating.value = false;
            },
        },
    );
}

/** Adds an expense or an income by hand: its own page, which leads back here. */
function add(): void {
    router.get(create.url({ query: { from: bankIndex().url } }));
}
</script>

<template>
    <Head :title="$t('From the bank')" />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle :title="$t('Budget')">
            <template #actions>
                <FmTitleTools v-if="mode === 'phone'" />
                <FmButton :icon="Plus" @click="add">{{
                    $t('New expense or income')
                }}</FmButton>
            </template>
        </FmTitle>
        <FmTabs :tabs="budgetTabs('bank', bankFeed)" :label="$t('Budget')" />

        <FmNotice
            v-for="bank in banks_to_reconnect"
            :key="bank"
            tone="warning"
            :title="$t(':bank needs to be connected again', { bank })"
        >
            {{
                can_change_banks
                    ? $t('Its movements do not arrive any more.')
                    : $t(
                          'Only who manages the family connects and disconnects the banks.',
                      )
            }}
            <template v-if="can_change_banks" #action>
                <FmButton
                    variant="outline"
                    :icon="RefreshCw"
                    :href="banksIndex().url"
                    >{{ $t('Reconnect') }}</FmButton
                >
            </template>
        </FmNotice>

        <div class="flex flex-wrap items-center gap-2.5">
            <nav
                :aria-label="$t('Movements from the bank')"
                class="flex flex-1 basis-50 gap-2"
            >
                <FmChip
                    :label="$t('To sort')"
                    :count="unsorted > 0 ? unsorted : undefined"
                    :pressed="show === 'unsorted'"
                    :href="bankIndex().url"
                />
                <FmChip
                    :label="$t('Ignored')"
                    :pressed="show === 'ignored'"
                    :href="bankIndex({ query: { show: 'ignored' } }).url"
                />
            </nav>
            <FmButton
                variant="neutral"
                :icon="RefreshCw"
                :disabled="updating"
                @click="update"
                >{{ $t('Update') }}</FmButton
            >
            <span class="basis-full text-meta text-ink-soft">{{
                readLine
            }}</span>
        </div>

        <FmEmpty
            v-if="rows.length === 0"
            :icon="Landmark"
            :title="
                show === 'ignored'
                    ? $t('No ignored movement')
                    : $t('No movement to sort')
            "
            :text="
                show === 'ignored'
                    ? $t(
                          'Ignored movements stay here for 12 months, then they are removed.',
                      )
                    : $t(
                          'The movements that pay a fixed expense are recorded by themselves. The others arrive here, to create or to ignore.',
                      )
            "
        />

        <template v-else>
            <ul
                v-auto-animate
                class="m-0 flex list-none flex-col divide-y divide-divider overflow-hidden rounded-card bg-card p-0 shadow-card"
            >
                <li
                    v-for="row in rows"
                    :key="row.id"
                    class="flex flex-col gap-2.5 px-4 py-3.5"
                >
                    <div class="flex items-start gap-3">
                        <span class="flex min-w-0 grow flex-col gap-0.5">
                            <span class="text-body-sm wrap-anywhere text-ink">{{
                                row.text
                            }}</span>
                            <span class="text-meta text-ink-soft"
                                >{{ dayOf(row) }}
                                {{
                                    row.account ? `· ${row.account}` : ''
                                }}</span
                            >
                        </span>
                        <span
                            :class="
                                cn(
                                    'shrink-0 text-amount whitespace-nowrap',
                                    row.pending && 'text-ink-soft',
                                    row.amount > 0 && 'text-paid',
                                )
                            "
                            >{{ amountText(row) }}</span
                        >
                    </div>
                    <div
                        v-if="row.pending"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span
                            class="inline-flex self-start rounded-tag bg-warning-soft px-2 py-[3px] text-meta-strong whitespace-nowrap text-warning"
                            >{{ $t('Pending') }}</span
                        >
                        <span class="text-meta text-ink-soft">{{
                            $t('It is sorted when the bank books it')
                        }}</span>
                    </div>
                </li>
            </ul>
            <WhenVisible
                v-if="next"
                :key="next"
                always
                :params="{
                    data: nextData,
                    only: ['rows', 'next'],
                    preserveUrl: true,
                }"
            />
            <p class="m-0 px-1 text-meta text-ink-soft">
                {{
                    show === 'ignored'
                        ? $t(
                              'Ignored movements stay here for 12 months, then they are removed.',
                          )
                        : $t(
                              'Only the movements that no fixed expense recognises stay here. The recognised ones are marked paid by themselves.',
                          )
                }}
            </p>
        </template>
    </div>
</template>
