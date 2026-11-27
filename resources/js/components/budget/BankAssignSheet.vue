<script setup lang="ts">
/**
 * *It belongs to an expense* (or *income*) of *From the bank*, as the board *Dalla banca, è di una spesa fissa* draws
 * it: the transaction at the top, a search box and two groups under it, the planned items (*Fixed expenses*, with the
 * due date each would settle) and the movements already recorded (*Recorded expenses*). The panel asks the server
 * (`budget.bank.candidates`) when it opens and 250 ms after the last key; an answer to an older text never replaces a
 * newer one. The first row is chosen; the panel only says which one was chosen (`assign`), the page sends it and shows
 * what the server answers in `error`.
 *
 * @group panels
 */
import { CircleCheck, Landmark } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import { useFormat } from '@/composables/useFormat';
import { cn } from '@/lib/utils';
import type { BankAssignChoice } from '@/lib/bankAssign';
import { candidates } from '@/routes/budget/bank';

/** One row of the list: a planned item or a recorded movement, with the key the radio buttons share. */
type Row =
    | { key: string; group: 'planned'; item: App.Data.BankPlannedChoiceData }
    | { key: string; group: 'recorded'; item: App.Data.BankRecordedChoiceData };

/** How long after the last key the server is asked, in milliseconds. */
const SEARCH_DELAY = 250;

const {
    transaction,
    error = null,
    processing = false,
} = defineProps<{
    /** The transaction to assign. */
    transaction: App.Data.BankTransactionData;
    /** What the server said when it refused the choice, shown under the list. */
    error?: string | null;
    /** The choice is being sent. */
    processing?: boolean;
}>();

const emit = defineEmits<{
    /** The button under the list: the planned due date or the recorded movement chosen. */
    assign: [choice: BankAssignChoice];
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

const { money, date } = useFormat();

/** The id of the search box, for the visible label over it. */
const searchId = useId();

/** Whether it is a credit: it belongs to an income. */
const income = computed(() => transaction.amount > 0);

/** What the box searches, as the visible label and the name of the box. */
const searchLabel = computed(() =>
    income.value ? trans('Search an income') : trans('Search an expense'),
);

/** The text in the search box. */
const search = ref('');

/** The planned items of the last answer, null before the first one. */
const planned = ref<App.Data.BankPlannedChoiceData[] | null>(null);

/** The recorded movements of the last answer. */
const recorded = ref<App.Data.BankRecordedChoiceData[]>([]);

/** The search did not get an answer. */
const failed = ref(false);

/** The key of the row the person tapped, null while the first one stands. */
const picked = ref<string | null>(null);

/** The number of the last search sent: an answer that is not this one is old. */
let sequence = 0;

/** The search in flight, so a newer text can drop it. */
let controller: AbortController | null = null;

/** The wait before the server is asked. */
let timer: ReturnType<typeof setTimeout> | undefined;

/**
 * Asks the server for a text and keeps the answer, unless a newer search started meanwhile.
 *
 * @param text - the words in the box
 */
async function ask(text: string): Promise<void> {
    sequence += 1;
    const mine = sequence;
    controller?.abort();
    controller = new AbortController();

    try {
        const response = await fetch(
            candidates(transaction.id, {
                query: text.trim() === '' ? {} : { q: text.trim() },
            }).url,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            throw new Error('The search failed');
        }

        const answer = (await response.json()) as {
            planned: App.Data.BankPlannedChoiceData[];
            recorded: App.Data.BankRecordedChoiceData[];
        };

        if (mine === sequence) {
            planned.value = answer.planned;
            recorded.value = answer.recorded;
            failed.value = false;
        }
    } catch {
        if (mine === sequence) {
            planned.value ??= [];
            failed.value = true;
        }
    }
}

// Opened, it starts from an empty search and the first row, and asks at once
watch(
    open,
    (now) => {
        if (now) {
            clearTimeout(timer);
            search.value = '';
            picked.value = null;
            planned.value = null;
            recorded.value = [];
            failed.value = false;
            void ask('');
        }
    },
    { immediate: true },
);

// Typing waits for a pause; the timer is cancelled with the component
watch(search, (text) => {
    clearTimeout(timer);
    timer = setTimeout(() => void ask(text), SEARCH_DELAY);
});

onBeforeUnmount(() => {
    clearTimeout(timer);
    sequence += 1;
    controller?.abort();
});

/** Every row, planned items first. */
const rows = computed<Row[]>(() => [
    ...(planned.value ?? []).map((item): Row => ({
        key: `planned-${item.kind}-${item.id}`,
        group: 'planned',
        item,
    })),
    ...recorded.value.map((item): Row => ({
        key: `recorded-${item.kind}-${item.id}`,
        group: 'recorded',
        item,
    })),
]);

/** The row chosen: the one tapped if it is still listed, else the first. */
const selected = computed(
    () =>
        rows.value.find((row) => row.key === picked.value) ??
        rows.value[0] ??
        null,
);

/** The planned rows, for their group. */
const plannedRows = computed(() =>
    rows.value.filter((row) => row.group === 'planned'),
);

/** The recorded rows, for their group. */
const recordedRows = computed(() =>
    rows.value.filter((row) => row.group === 'recorded'),
);

/** Whether the choice links something already recorded: a recorded movement, or a due date paid by hand. */
const links = computed(
    () =>
        selected.value !== null &&
        (selected.value.group === 'recorded' ||
            selected.value.item.paid_on !== null),
);

/**
 * The line under a planned item: who it is paid to, then what its due date is (already marked, ended, late or not).
 *
 * @param choice - the planned item
 */
function plannedDetail(choice: App.Data.BankPlannedChoiceData): string {
    const values = {
        date: date(choice.paid_on ?? choice.due_on),
        organization: choice.organization ?? '',
    };

    if (choice.paid_on !== null) {
        return choice.organization
            ? trans(':organization · already marked on :date', values)
            : trans('Already marked on :date', values);
    }

    if (choice.ended) {
        return choice.organization
            ? trans(':organization · ended · due on :date', values)
            : trans('Ended · due on :date', values);
    }

    if (choice.organization) {
        return choice.late
            ? trans(':organization · due on :date, late', values)
            : trans(':organization · due on :date', values);
    }

    return choice.late
        ? trans('Due on :date, late', values)
        : trans('Due on :date', values);
}

/**
 * The line under a recorded movement: who it is paid to and its day.
 *
 * @param choice - the recorded movement
 */
function recordedDetail(choice: App.Data.BankRecordedChoiceData): string {
    return choice.organization
        ? trans(':organization · :date', {
              organization: choice.organization,
              date: date(choice.date),
          })
        : date(choice.date);
}

/** What the choice does, in the line under the list. */
const effect = computed(() => {
    const row = selected.value;

    if (row === null) {
        return '';
    }

    if (row.group === 'recorded') {
        return income.value
            ? trans(
                  'Links the transaction to the income already recorded, without creating another one.',
              )
            : trans(
                  'Links the transaction to the expense already recorded, without creating another one.',
              );
    }

    if (row.item.paid_on !== null) {
        return income.value
            ? trans(
                  'Links the transaction to the income already marked, without creating another one.',
              )
            : trans(
                  'Links the transaction to the expense already marked, without creating another one.',
              );
    }

    const values = { date: date(row.item.due_on) };

    return income.value
        ? trans('The due date of :date is marked as received.', values)
        : trans('The due date of :date is marked as paid.', values);
});

/** The label of the button under the list. */
const confirmLabel = computed(() => {
    if (links.value) {
        return trans('Link the transaction');
    }

    return income.value ? trans('Mark as received') : trans('Mark as paid');
});

/** Gives the choice: the due date as the rules give it (never the day it falls), or the movement. */
function confirm(): void {
    const row = selected.value;

    if (row === null) {
        return;
    }

    emit(
        'assign',
        row.group === 'planned'
            ? {
                  kind: row.item.kind,
                  planned_id: row.item.id,
                  occurrence: row.item.occurrence,
              }
            : { kind: row.item.kind, movement_id: row.item.id },
    );
}
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :title="
            income
                ? $t('It belongs to an income')
                : $t('It belongs to an expense')
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

        <div class="flex flex-col gap-1.5">
            <label :for="searchId" class="text-row text-ink">{{
                searchLabel
            }}</label>
            <FmSearchField
                v-model="search"
                :input-id="searchId"
                :label="searchLabel"
                :placeholder="$t('Search by name, organization or amount')"
                clearable
            />
            <p class="m-0 text-meta text-ink-soft">
                {{
                    income
                        ? $t(
                              'Searches all the incomes, fixed and recorded, even old ones: by name, organization or amount.',
                          )
                        : $t(
                              'Searches all the expenses, fixed and recorded, even old ones: by name, organization or amount.',
                          )
                }}
            </p>
        </div>

        <p v-if="planned === null" class="m-0 p-5 text-body-sm text-ink-soft">
            {{ $t('Loading…') }}
        </p>
        <template v-else>
            <p
                v-if="rows.length === 0 && !failed"
                class="m-0 p-5 text-body-sm text-ink-soft"
            >
                {{ income ? $t('No income found.') : $t('No expense found.') }}
            </p>
            <div
                v-else
                class="flex max-h-[min(360px,45dvh)] flex-col gap-3.5 overflow-y-auto"
                role="radiogroup"
                :aria-label="searchLabel"
            >
                <template
                    v-for="group in [
                        {
                            id: 'planned',
                            list: plannedRows,
                            title: income
                                ? $t('Fixed incomes')
                                : $t('Fixed expenses'),
                        },
                        {
                            id: 'recorded',
                            list: recordedRows,
                            title: income
                                ? $t('Recorded incomes')
                                : $t('Recorded expenses'),
                        },
                    ]"
                    :key="group.id"
                >
                    <section
                        v-if="group.list.length > 0"
                        class="flex flex-col gap-1.5"
                    >
                        <h3 class="m-0 px-1 text-meta-strong text-ink-soft">
                            {{ group.title }}
                        </h3>
                        <div
                            class="flex flex-col divide-y divide-divider overflow-hidden rounded-card border border-edge-soft"
                        >
                            <label
                                v-for="row in group.list"
                                :key="row.key"
                                class="relative flex min-h-11 cursor-pointer items-center gap-3 px-3.5 py-3"
                            >
                                <input
                                    type="radio"
                                    name="choice"
                                    class="peer sr-only"
                                    :checked="selected?.key === row.key"
                                    @change="picked = row.key"
                                />
                                <span
                                    aria-hidden="true"
                                    :class="
                                        cn(
                                            'box-border size-[22px] shrink-0 rounded-full bg-card peer-focus-visible:ring-2 peer-focus-visible:ring-action peer-focus-visible:ring-offset-2',
                                            selected?.key === row.key
                                                ? 'border-[7px] border-action'
                                                : 'border-[1.5px] border-edge',
                                        )
                                    "
                                />
                                <span
                                    class="flex min-w-0 grow flex-col gap-0.5"
                                >
                                    <span class="text-body-strong text-ink">{{
                                        row.item.title
                                    }}</span>
                                    <span class="text-meta text-ink-soft">{{
                                        row.group === 'planned'
                                            ? plannedDetail(row.item)
                                            : recordedDetail(row.item)
                                    }}</span>
                                </span>
                                <span
                                    class="shrink-0 text-amount whitespace-nowrap"
                                    >{{
                                        `${row.group === 'planned' && row.item.estimated ? '~' : ''}${money(row.item.amount, 'EUR')}`
                                    }}</span
                                >
                            </label>
                        </div>
                    </section>
                </template>
            </div>
        </template>

        <p v-if="failed" role="alert" class="m-0 text-body-sm text-overdue">
            {{ $t('The search did not work. Try again.') }}
        </p>
        <p v-if="error" role="alert" class="m-0 text-body-sm text-overdue">
            {{ error }}
        </p>
        <p
            v-if="selected !== null"
            class="m-0 flex items-start gap-2 text-body-sm text-ink"
        >
            <FmIcon
                :icon="CircleCheck"
                :size="18"
                class="mt-px shrink-0 text-paid"
            />
            {{ effect }}
        </p>

        <div class="flex justify-end gap-2.5">
            <FmButton variant="text" @click="open = false">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton
                class="grow"
                :disabled="selected === null || processing"
                @click="confirm"
                >{{ confirmLabel }}</FmButton
            >
        </div>
    </FmBottomSheet>
</template>
