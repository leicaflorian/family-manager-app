<script setup lang="ts">
/**
 * A group of similar movements still to sort, in the tab *To sort* of *From the bank*, as the boards *Dalla banca*
 * draw it: an icon for money out or in, the name, how many movements and their amounts, the months, what the system
 * proposes for it (or that no similar expense was found), *It comes back every month* when the server gives its
 * address, and *Create the filter* with the new filter of the group. The head opens the group (`open`): the page
 * shows its movements.
 * With `variant="row"` it is a row of the list of groups beside the open group (boards *Dalla banca, il gruppo*, wide):
 * name, amounts and the proposal in one line, with the one open marked.
 *
 * @group rows
 */
import {
    ArrowDownLeft,
    ArrowUpRight,
    ChevronRight,
    Repeat1,
    SearchX,
    Sparkles,
} from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import { groupAmounts, groupMonths, groupProposal } from '@/lib/bankGroups';
import { cn } from '@/lib/utils';
import { create as createFilter } from '@/routes/budget/bank/filters';

const {
    group,
    variant = 'card',
    selected = false,
} = defineProps<{
    /** The group, as the server sends it. */
    group: App.Data.BankGroupData;
    /** `card` in the page, `row` in the list beside the open group. */
    variant?: 'card' | 'row';
    /** The group is the one open (only for a row). */
    selected?: boolean;
}>();

const emit = defineEmits<{
    /** The head was tapped: the page opens the group. */
    open: [];
}>();

/** Money that came in. */
const income = computed((): boolean => group.kind === 'income');

/** What the system proposes, in a few words; null without a proposal. */
const proposal = computed((): string | null => groupProposal(group));

/** The new filter of the group, filled in with the proposal. */
const filterUrl = computed(
    (): string => createFilter({ query: { group: group.key } }).url,
);

/** The words of *It comes back every month* for money out or in. */
const recurLabel = computed((): string =>
    income.value
        ? trans('It comes back every month: create a planned income?')
        : trans('It comes back every month: create a planned expense?'),
);

/** The look of the head, a button that covers the whole top of the card or the whole row. */
const headClass = computed((): string =>
    variant === 'row'
        ? cn(
              'flex w-full cursor-pointer items-center gap-3 border-0 px-4 py-3 text-left text-ink',
              selected
                  ? 'bg-action-soft'
                  : 'bg-transparent hover:bg-card-sunken',
          )
        : cn(
              'flex w-full cursor-pointer items-center gap-3 border-0 bg-transparent p-0 text-left text-ink',
          ),
);
</script>

<template>
    <button
        v-if="variant === 'row'"
        type="button"
        :class="headClass"
        :aria-current="selected ? 'true' : undefined"
        @click="emit('open')"
    >
        <span class="flex min-w-0 grow flex-col gap-0.5">
            <span
                :class="
                    cn(
                        'text-body-strong',
                        selected ? 'text-action-dark' : 'text-ink',
                    )
                "
                >{{ group.name }}</span
            >
            <span class="text-meta text-ink-soft">{{
                groupAmounts(group)
            }}</span>
            <span class="truncate text-meta text-ink-soft">{{
                proposal ?? $t('No similar expense found')
            }}</span>
        </span>
        <ChevronRight
            aria-hidden="true"
            class="size-5 shrink-0 text-ink-soft"
        />
    </button>

    <article
        v-else
        class="flex min-w-0 flex-col gap-3 rounded-card bg-card p-4 shadow-card"
    >
        <button type="button" :class="headClass" @click="emit('open')">
            <span
                :class="
                    cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-lg',
                        income
                            ? 'bg-income-soft text-income-text'
                            : 'bg-expenses-soft text-expenses-text',
                    )
                "
            >
                <component
                    :is="income ? ArrowDownLeft : ArrowUpRight"
                    aria-hidden="true"
                    class="size-5"
                />
            </span>
            <span class="flex min-w-0 grow flex-col gap-0.5">
                <span class="text-body-strong">{{ group.name }}</span>
                <span class="text-meta text-ink-soft">{{
                    groupAmounts(group)
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    groupMonths(group)
                }}</span>
            </span>
            <ChevronRight
                aria-hidden="true"
                class="size-5 shrink-0 text-ink-soft"
            />
        </button>

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

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Link
                v-if="group.recurring_url"
                :href="group.recurring_url"
                class="flex min-h-11 min-w-0 flex-[1_1_250px] items-center gap-2 rounded-md bg-action-soft px-3 py-2 text-label text-action-dark"
            >
                <Repeat1 aria-hidden="true" class="size-[18px] shrink-0" />
                <span class="min-w-0 grow">{{ recurLabel }}</span>
                <ChevronRight aria-hidden="true" class="size-[18px] shrink-0" />
            </Link>
            <FmButton :href="filterUrl"
                >{{ $t('Create the filter')
                }}<ChevronRight aria-hidden="true" class="size-[18px]"
            /></FmButton>
        </div>
    </article>
</template>
