<script setup lang="ts">
/**
 * *It belongs to a planned expense* (or *income*) of *From the bank*, as the board *Dalla banca, è di una spesa fissa*
 * draws it: the transaction at the top, a search box, the planned items that have a due date to pay (the page asks for
 * them when the panel opens, so `choices` is empty while it loads) and under them what *Mark as paid* does. The first
 * one is chosen; the panel only says which one was chosen (`assign`), the page sends it and shows what the server
 * answers in `error`.
 *
 * @group panels
 */
import { Landmark } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import { useFormat } from '@/composables/useFormat';
import { cn } from '@/lib/utils';

const {
    transaction,
    choices,
    error = null,
    processing = false,
} = defineProps<{
    /** The transaction to assign. */
    transaction: App.Data.BankTransactionData;
    /** The planned items it can pay, undefined while the page loads them. */
    choices?: App.Data.BankPlannedChoiceData[];
    /** What the server said when it refused, shown under the list. */
    error?: string | null;
    /** The choice is being sent. */
    processing?: boolean;
}>();

const emit = defineEmits<{
    /** *Mark as paid*: the planned item chosen. */
    assign: [choice: App.Data.BankPlannedChoiceData];
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

const { money, date } = useFormat();

/** The text in the search box. */
const search = ref('');

/** The planned item the person tapped, null while the first one stands. */
const picked = ref<number | null>(null);

// Opened again, it starts from the first planned item and an empty search
watch(open, (now) => {
    if (now) {
        search.value = '';
        picked.value = null;
    }
});

/** Whether it is a credit: it belongs to a planned income. */
const income = computed(() => transaction.amount > 0);

/** The planned items whose name or organization has the words typed. */
const shown = computed(() => {
    const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);

    return (choices ?? []).filter((choice) => {
        const text =
            `${choice.title} ${choice.organization ?? ''}`.toLowerCase();

        return words.every((word) => text.includes(word));
    });
});

/** The planned item chosen: the one tapped if it is still listed, else the first. */
const selected = computed(
    () =>
        shown.value.find((choice) => choice.id === picked.value) ??
        shown.value[0] ??
        null,
);

/**
 * The line under the name: who it is paid to and the due date, late or not.
 *
 * @param choice - the planned item
 */
function detail(choice: App.Data.BankPlannedChoiceData): string {
    const due = choice.late
        ? trans('Due on :date, late', { date: date(choice.due_on) })
        : trans('Due on :date', { date: date(choice.due_on) });

    return [choice.organization, due].filter(Boolean).join(' · ');
}

/** Gives the planned item chosen. */
function confirm(): void {
    if (selected.value !== null) {
        emit('assign', selected.value);
    }
}
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :title="
            income
                ? $t('It belongs to a planned income')
                : $t('It belongs to a planned expense')
        "
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
            <span class="shrink-0 text-amount whitespace-nowrap">{{
                money(transaction.amount, transaction.currency)
            }}</span>
        </div>

        <FmSearchField
            v-model="search"
            :label="
                income
                    ? $t('Search a planned income')
                    : $t('Search a planned expense')
            "
        />

        <p
            v-if="choices === undefined"
            class="m-0 p-5 text-body-sm text-ink-soft"
        >
            {{ $t('Loading…') }}
        </p>
        <p
            v-else-if="shown.length === 0"
            class="m-0 p-5 text-body-sm text-ink-soft"
        >
            {{
                income
                    ? $t('No planned income found.')
                    : $t('No planned expense found.')
            }}
        </p>
        <div
            v-else
            role="radiogroup"
            :aria-label="
                income ? $t('Planned incomes') : $t('Planned expenses')
            "
            class="flex max-h-[min(360px,45dvh)] flex-col divide-y divide-divider overflow-y-auto rounded-card border border-edge-soft"
        >
            <label
                v-for="choice in shown"
                :key="`${choice.kind}-${choice.id}`"
                class="flex cursor-pointer items-center gap-3 px-3.5 py-3"
            >
                <input
                    type="radio"
                    name="planned"
                    class="peer sr-only"
                    :checked="selected?.id === choice.id"
                    @change="picked = choice.id"
                />
                <span
                    aria-hidden="true"
                    :class="
                        cn(
                            'box-border size-[22px] shrink-0 rounded-full bg-card peer-focus-visible:ring-2 peer-focus-visible:ring-action peer-focus-visible:ring-offset-2',
                            selected?.id === choice.id
                                ? 'border-[7px] border-action'
                                : 'border-[1.5px] border-edge',
                        )
                    "
                />
                <span class="flex min-w-0 grow flex-col gap-0.5">
                    <span class="text-label text-ink">{{ choice.title }}</span>
                    <span class="text-meta text-ink-soft">{{
                        detail(choice)
                    }}</span>
                </span>
                <span class="shrink-0 text-amount whitespace-nowrap">{{
                    `${choice.estimated ? '~' : ''}${money(choice.amount, 'EUR')}`
                }}</span>
            </label>
        </div>

        <p v-if="error" role="alert" class="m-0 text-body-sm text-overdue">
            {{ error }}
        </p>
        <p class="m-0 text-meta text-ink-soft">
            {{
                $t(
                    'The due date closest to :date is marked as paid. Afterwards you can write the rule, so next time it pays by itself.',
                    {
                        date: date(
                            transaction.booked_on ?? transaction.value_on,
                        ),
                    },
                )
            }}
        </p>

        <div class="flex justify-end gap-2.5">
            <FmButton variant="text" @click="open = false">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton
                class="grow"
                :disabled="selected === null || processing"
                @click="confirm"
                >{{ $t('Mark as paid') }}</FmButton
            >
        </div>
    </FmBottomSheet>
</template>
