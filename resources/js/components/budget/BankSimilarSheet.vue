<script setup lang="ts">
/**
 * The sheet that follows a movement of a group sorted by hand, as the boards *Dalla banca, altri movimenti come
 * questo* draw it: «I found 10 more movements like this», the first five of them with their commission, *Show the
 * other 5* for the rest of the ones the server carried, what the filter for them would do (`pay`, `create` or
 * `transfer`), *No, leave it so* (closes) and *Create the filter* (the form of the new filter, filled with what was
 * just chosen). It only shows what the flash `similar` says; the page opens it and closes it. On the phone a bottom
 * sheet, wide a panel beside the list (`BankPanelFrame`).
 *
 * @group panels
 */
import { ChevronDown, ListFilter, Sparkles } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import BankFeeLine from '@/components/budget/BankFeeLine.vue';
import BankPanelFrame from '@/components/budget/BankPanelFrame.vue';
import FmButton from '@/components/fm/FmButton.vue';
import { useFormat } from '@/composables/useFormat';
import { actionLine } from '@/lib/bankFilters';
import { groupAmounts, groupDays } from '@/lib/bankGroups';
import { dayOf } from '@/lib/banks';
import type { FlashSimilar } from '@/types/ui';

/** How many of the movements show before *Show the other…*. */
const FIRST = 5;

const { similar, returnFocus } = defineProps<{
    /** What the server flashed: how many others, the movements it carried, the action and the filter form. */
    similar: FlashSimilar;
    /** Where the focus goes at the closing when what opened it is gone (wide only). */
    returnFocus?: () => HTMLElement | null;
}>();

/** The sheet is open. */
const open = defineModel<boolean>('open', { default: false });

const { money } = useFormat();

/** All the carried movements show, after *Show the other…*. */
const all = ref(false);

// Opened for another offer, it starts with the first five again
watch(
    () => similar,
    () => {
        all.value = false;
    },
);

/** The movements shown. */
const shown = computed((): App.Data.BankTransactionData[] =>
    all.value ? similar.rows : similar.rows.slice(0, FIRST),
);

/** How many carried movements the button would add. */
const hidden = computed((): number =>
    all.value ? 0 : Math.max(0, similar.rows.length - FIRST),
);

/** The title: how many movements like the one just sorted are left. */
const title = computed((): string =>
    similar.count === 1
        ? trans('I found one more movement like this')
        : trans('I found :count more movements like this', {
              count: String(similar.count),
          }),
);

/** The account, the amounts and the days of the other movements, as the board writes them under the heading. */
const accountLine = computed((): string =>
    [
        similar.rows[0]?.account,
        groupAmounts(group.value),
        groupDays(group.value),
    ]
        .filter(Boolean)
        .join(' · '),
);

/** The fields `lib/bankGroups` reads, from the flash. */
const group = computed(() => ({
    key: '',
    kind: similar.kind,
    count: similar.count,
    min: similar.min,
    max: similar.max,
    from: similar.from,
    to: similar.to,
    proposal_action: null,
    proposal_title: null,
    proposal_detail: null,
}));

/** Why they are alike: the mandate they share, or the words; null when the server has no name or words for it. */
const reason = computed((): string | null => {
    const name = similar.reason?.name.trim() ?? '';

    if (similar.reason === null || name === '') {
        return null;
    }

    return similar.reason.kind === 'mandate'
        ? trans('They are of the same mandate :name.', { name })
        : trans('They have the same words: :words.', { words: name });
});

/** The opening lines: why they are alike, when the server says it, and what a filter would do for them. */
const intro = computed((): string =>
    [
        reason.value,
        trans(
            'With a filter they are all sorted, and the ones that arrive next too.',
        ),
    ]
        .filter((line): line is string => line !== null)
        .join(' '),
);

/** What the filter would do, as the line *Action* of the filter writes it. */
const will = computed((): string => actionLine(similar.filter));
</script>

<template>
    <BankPanelFrame
        v-model:open="open"
        :title="title"
        :return-focus="returnFocus"
    >
        <p class="m-0 text-body-sm text-ink">{{ intro }}</p>

        <div class="flex flex-col gap-0.5">
            <h3 class="m-0 mt-1 text-row text-ink">
                {{
                    $tChoice(
                        '{1} The other movement|[2,*] The other :count movements',
                        similar.count,
                    )
                }}
            </h3>
            <span class="text-meta text-ink-soft">{{ accountLine }}</span>
        </div>
        <div
            class="flex flex-col overflow-hidden rounded-card border border-edge-soft"
        >
            <ul class="m-0 flex list-none flex-col divide-y divide-divider p-0">
                <li
                    v-for="row in shown"
                    :key="row.id"
                    class="flex items-center gap-3 px-3.5 py-2.5"
                >
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="text-label text-ink">{{
                            dayOf(row)
                        }}</span>
                        <BankFeeLine
                            v-if="row.fee"
                            :fee="row.fee"
                            :currency="row.currency"
                        />
                    </span>
                    <span class="flex shrink-0 flex-col items-end">
                        <span class="text-amount whitespace-nowrap">{{
                            money(row.amount, row.currency)
                        }}</span>
                        <span
                            v-if="row.fee"
                            class="text-meta whitespace-nowrap text-ink-soft"
                            >+
                            {{
                                money(Math.abs(row.fee.amount), row.currency)
                            }}</span
                        >
                    </span>
                </li>
            </ul>
            <button
                v-if="hidden > 0"
                type="button"
                class="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 border-0 border-t border-divider bg-transparent text-label text-action"
                @click="all = true"
            >
                {{ $t('Show the other :count', { count: String(hidden) })
                }}<ChevronDown aria-hidden="true" class="size-[18px]" />
            </button>
        </div>

        <h3 class="m-0 mt-1 text-row text-ink">
            {{ $t('What the filter does') }}
        </h3>
        <span
            class="flex items-start gap-2 rounded-card bg-action-soft px-3 py-2.5 text-label text-action-dark"
        >
            <Sparkles aria-hidden="true" class="mt-0.5 size-4 shrink-0" />
            <span class="min-w-0 first-letter:uppercase">{{ will }}</span>
        </span>
        <p class="m-0 text-meta text-ink-soft">
            {{
                $t(
                    'The filter opens already filled with what you have just chosen: you check it and save it. Nothing is linked before that.',
                )
            }}
        </p>

        <div class="flex justify-end gap-2.5">
            <FmButton variant="text" @click="open = false">{{
                $t('No, leave it so')
            }}</FmButton>
            <FmButton class="grow" :href="similar.url" :icon="ListFilter">{{
                $t('Create the filter')
            }}</FmButton>
        </div>
    </BankPanelFrame>
</template>
