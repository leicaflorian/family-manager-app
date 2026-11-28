<script setup lang="ts">
/**
 * A group of similar movements opened from the tab *To sort* of *From the bank*, as the boards *Dalla banca, il
 * gruppo* draw it: the name, the account and the direction, how many movements with their amounts and days, what the
 * system proposes and what saving the filter would do, *Create the filter*, and the movements of the group, six at
 * first and *Show … more* for the rest, each with its day, its text, its commission, its amount and what the proposal
 * would do with it. Each movement has *It is not of this group* (`notOfGroup`): taking it out of the group is Task 19,
 * here the panel only says which movement was tapped. On the phone it is a bottom sheet; wide, a panel at the right of
 * the list of groups (the page puts it in its column). While the page asks the movements (`rows` null) the list waits.
 *
 * @group panels
 */
import {
    ChevronDown,
    CornerDownRight,
    Link as LinkIcon,
    ListFilter,
    Plus,
    Sparkles,
    SearchX,
    Split,
    X,
} from '@lucide/vue';
import { vAutoAnimate } from '@formkit/auto-animate/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import { willTag } from '@/lib/bankFilters';
import { groupAmounts, groupDays, groupProposal } from '@/lib/bankGroups';
import { dayOf } from '@/lib/banks';
import { formatDayMonth } from '@/lib/format';
import { listNames } from '@/lib/names';
import { cn } from '@/lib/utils';
import { create as createFilter } from '@/routes/budget/bank/filters';

const { group, rows } = defineProps<{
    /** The group, as the server sends it in `groups`. */
    group: App.Data.BankGroupData;
    /** Its movements, as `group_rows` brings them; null while the page asks them. */
    rows: App.Data.BankGroupRowData[] | null;
}>();

const emit = defineEmits<{
    /** The panel was closed. */
    close: [];
    /** *It is not of this group* was tapped on a movement (the action comes with Task 19). */
    notOfGroup: [row: App.Data.BankTransactionData];
}>();

const wide = useWideLayout();
const { money } = useFormat();

/** How many movements show before *Show … more*, as the boards. */
const FIRST = 6;

/** The whole list shows. */
const all = ref(false);

/** The movements shown now. */
const shown = computed((): App.Data.BankGroupRowData[] =>
    all.value ? (rows ?? []) : (rows ?? []).slice(0, FIRST),
);

/** What the system proposes, in a few words; null without a proposal. */
const proposal = computed((): string | null => groupProposal(group));

/** The new filter of the group, filled in with the proposal. */
const filterUrl = computed(
    (): string => createFilter({ query: { group: group.key } }).url,
);

/** The account and whether the money goes out or comes in. */
const accountLine = computed((): string =>
    group.kind === 'income'
        ? trans(':account · money in', { account: group.account })
        : trans(':account · money out', { account: group.account }),
);

/** How many movements, their amounts and their days. */
const amountsLine = computed(
    (): string => `${groupAmounts(group)} · ${groupDays(group)}`,
);

/**
 * What saving the filter would do to the movements, counted from what the server says of each: «Saving the filter:
 * 10 new expenses and 1 already recorded to link. No duplicates.»; null while the movements are asked or when
 * nothing would happen.
 */
const outcome = computed((): string | null => {
    if (rows === null) {
        return null;
    }

    const count = (will: string): number =>
        rows.filter((row) => row.will === will).length;
    const income = group.kind === 'income';
    const created = count('create');
    const linked = count('link');
    const paid = count('pay');
    const parts = [
        created > 0
            ? transChoice(
                  income
                      ? '{1} :count new income|[2,*] :count new incomes'
                      : '{1} :count new expense|[2,*] :count new expenses',
                  created,
              )
            : null,
        linked > 0
            ? transChoice(
                  '{1} :count already recorded to link|[2,*] :count already recorded to link',
                  linked,
              )
            : null,
        paid > 0
            ? transChoice(
                  income
                      ? '{1} :count income to mark received|[2,*] :count incomes to mark received'
                      : '{1} :count due date to pay|[2,*] :count due dates to pay',
                  paid,
              )
            : null,
    ].filter((part): part is string => part !== null);

    return parts.length === 0
        ? null
        : trans('Saving the filter: :what. No duplicates.', {
              what: listNames(parts),
          });
});

/** The text of *Show … more*: how many and from which day. */
const moreText = computed((): string => {
    const hidden = (rows ?? []).slice(FIRST);
    const days = hidden
        .map((row) => row.transaction.booked_on ?? row.transaction.value_on)
        .filter((day): day is string => day !== null)
        .sort();

    return transChoice(
        '{1} Show one more movement, of :day|[2,*] Show :count more movements, from :day',
        hidden.length,
        { day: days[0] ? formatDayMonth(days[0]) : '' },
    );
});

/**
 * The look of the tag of what the proposal would do, as the boards: green for a link, grey otherwise.
 *
 * @param tone - the tone `willTag` gives
 * @returns the classes
 */
function tagClass(tone: 'paid' | 'action' | 'muted'): string {
    return cn(
        'inline-flex items-center gap-1.5 self-start rounded-tag px-2 py-[3px] text-meta-strong',
        tone === 'paid' && 'bg-paid-soft text-paid',
        tone === 'action' && 'bg-action-soft text-action-dark',
        tone === 'muted' && 'bg-card-sunken text-ink-soft',
    );
}
</script>

<template>
    <component
        :is="wide ? 'aside' : FmBottomSheet"
        v-bind="
            wide
                ? {
                      role: 'dialog',
                      'aria-label': group.name,
                      class: 'flex min-w-0 flex-col gap-3.5 rounded-card bg-card p-6 shadow-card',
                  }
                : {
                      open: true,
                      title: group.name,
                      'onUpdate:open': (open: boolean) =>
                          !open && emit('close'),
                  }
        "
    >
        <div v-if="wide" class="flex items-center justify-between gap-3">
            <h2 class="m-0 text-sheet-title text-ink">{{ group.name }}</h2>
            <button
                type="button"
                :aria-label="$t('Close')"
                class="-mr-2.5 flex size-11 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-ink-soft"
                @click="emit('close')"
            >
                <X aria-hidden="true" :size="20" />
            </button>
        </div>
        <div class="flex flex-col gap-0.5">
            <span class="text-meta text-ink-soft">{{ accountLine }}</span>
            <span class="text-meta text-ink-soft">{{ amountsLine }}</span>
        </div>

        <span
            v-if="proposal"
            class="flex items-start gap-2 rounded-lg bg-action-soft px-3 py-2.5 text-label text-action-dark"
        >
            <Sparkles aria-hidden="true" class="mt-px size-[18px] shrink-0" />
            <span class="min-w-0">{{
                $t('Proposal: :proposal', { proposal })
            }}</span>
        </span>
        <span
            v-else
            class="flex items-start gap-2 rounded-lg bg-card-sunken px-3 py-2.5 text-meta text-ink-soft"
        >
            <SearchX aria-hidden="true" class="mt-px size-[18px] shrink-0" />
            <span class="min-w-0">{{
                $t(
                    'No similar expense found. When you create the filter, the AI suggests title and category.',
                )
            }}</span>
        </span>
        <span v-if="outcome" class="text-meta text-ink-soft">{{
            outcome
        }}</span>
        <FmButton :href="filterUrl" :icon="ListFilter">{{
            $t('Create the filter')
        }}</FmButton>

        <h3 class="m-0 mt-1 text-row text-ink">
            {{ $t('The movements of the group') }}
        </h3>
        <div
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-xl border border-edge-soft"
        >
            <ul
                v-auto-animate
                :aria-label="$t('The movements of the group')"
                :aria-busy="rows === null ? 'true' : undefined"
                class="m-0 flex list-none flex-col divide-y divide-divider p-0"
            >
                <li
                    v-for="row in shown"
                    :key="row.transaction.id"
                    class="flex flex-col gap-1.5 px-4 pt-3 pb-1.5"
                >
                    <div class="flex items-start gap-3">
                        <span class="flex min-w-0 grow flex-col gap-0.5">
                            <span class="text-label text-ink">{{
                                dayOf(row.transaction)
                            }}</span>
                            <span
                                class="text-meta wrap-anywhere text-ink-soft"
                                >{{ row.transaction.text }}</span
                            >
                            <span
                                v-if="row.fee"
                                class="flex items-center gap-1.5 text-meta text-ink-soft"
                            >
                                <CornerDownRight
                                    aria-hidden="true"
                                    class="size-3.5 shrink-0"
                                />{{
                                    $t('+ :fee commission, :day', {
                                        fee: money(
                                            Math.abs(row.fee.amount),
                                            row.transaction.currency,
                                        ),
                                        day: dayOf({
                                            booked_on: row.fee.booked_on,
                                            value_on: null,
                                        }),
                                    })
                                }}
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end">
                            <span
                                :class="
                                    cn(
                                        'text-amount whitespace-nowrap text-ink',
                                        row.transaction.amount > 0 &&
                                            'text-paid',
                                    )
                                "
                                >{{
                                    money(
                                        row.transaction.amount,
                                        row.transaction.currency,
                                    )
                                }}</span
                            >
                            <span
                                v-if="row.fee"
                                class="text-meta whitespace-nowrap text-ink-soft"
                                >+
                                {{
                                    money(
                                        Math.abs(row.fee.amount),
                                        row.transaction.currency,
                                    )
                                }}</span
                            >
                        </span>
                    </div>
                    <span :class="tagClass(willTag(row).tone)">
                        <LinkIcon
                            v-if="row.will === 'link'"
                            aria-hidden="true"
                            class="size-3.5 shrink-0"
                        />
                        <Plus
                            v-else-if="row.will === 'create'"
                            aria-hidden="true"
                            class="size-3.5 shrink-0"
                        />{{ willTag(row).text }}
                    </span>
                    <div class="flex">
                        <FmButton
                            variant="text"
                            muted
                            :icon="Split"
                            class="-ml-2 px-2"
                            @click="emit('notOfGroup', row.transaction)"
                            >{{ $t('It is not of this group') }}</FmButton
                        >
                    </div>
                </li>
            </ul>
            <button
                v-if="!all && (rows ?? []).length > FIRST"
                type="button"
                class="flex min-h-13 cursor-pointer items-center justify-center gap-2 border-0 bg-transparent text-label text-action hover:text-action-dark"
                @click="all = true"
            >
                {{ moreText }}
                <ChevronDown aria-hidden="true" class="size-[18px]" />
            </button>
        </div>
    </component>
</template>
