<script setup lang="ts">
/**
 * *The movements it takes*, beside the form of a bank filter (under it on the phone), as the board
 * *Dalla-banca-filtro* draws it: how many movements of the last 90 days the filter written so far takes, from which
 * day to which and from which amount to which, and the whole list, ten at first and *Show the other…* for the rest.
 * Each movement has its day, its text with the words found highlighted, the commission found (or none), the amount
 * and what the filter will do to it; one another filter already takes says which, since two filters on the same
 * movement cannot be saved.
 */
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { TriangleAlert } from '@lucide/vue';
import { useFormat } from '@/composables/useFormat';
import { willTag } from '@/lib/bankFilters';
import { formatDayMonth } from '@/lib/format';
import { highlightParts } from '@/lib/highlight';
import { cn } from '@/lib/utils';

const {
    preview,
    feeMode,
    error = null,
    loading = false,
} = defineProps<{
    /** The movements the filter takes and what saving it would do, as the server answers. */
    preview: App.Data.BankFilterPreviewData;
    /** How the filter finds the commission: with `none` the rows say nothing of it. */
    feeMode: string;
    /** Why the list could not be asked, null when it could. */
    error?: string | null;
    /** A new list is on its way. */
    loading?: boolean;
}>();

/** How many movements show before *Show the other…*. */
const FIRST = 10;

const { money } = useFormat();

/** The whole list shows. */
const all = ref(false);

/** The rows shown now. */
const rows = computed(() =>
    all.value ? preview.rows : preview.rows.slice(0, FIRST),
);

/** Marks where the name of the other filter goes, to draw it strong. */
const NAME = '\u0000';

/** The words around the name in the note of a movement another filter takes. */
const takenWords = computed((): [string, string] => {
    const [before = '', after = ''] = trans(
        'The filter :name already takes it. Two filters on the same movement cannot be saved: change one of the two.',
        { name: NAME },
    ).split(NAME);

    return [before, after];
});

/** The line under the heading: the days and the amounts of the movements taken. */
const range = computed((): string | null => {
    const list = preview.rows.map((row) => row.transaction);
    const days = list
        .map((row) => row.booked_on ?? row.value_on)
        .filter((day): day is string => day !== null)
        .sort();
    const amounts = list.map((row) => Math.abs(row.amount));

    if (days.length === 0) {
        return null;
    }

    const from = formatDayMonth(days[0]!);
    const to = formatDayMonth(days[days.length - 1]!);
    const min = Math.min(...amounts);
    const max = Math.max(...amounts);

    return min === max
        ? trans('Last 90 days, from :from to :to, :amount', {
              from,
              to,
              amount: money(min, 'EUR'),
          })
        : trans('Last 90 days, from :from to :to, from :min to :max', {
              from,
              to,
              min: money(min, 'EUR'),
              max: money(max, 'EUR'),
          });
});

/** The text of *Show the other…*: how many and from which day. */
const moreText = computed((): string => {
    const hidden = preview.rows.slice(FIRST);
    const days = hidden
        .map((row) => row.transaction.booked_on ?? row.transaction.value_on)
        .filter((day): day is string => day !== null)
        .sort();

    return trans('Show the other :count (from :day)', {
        count: String(hidden.length),
        day: days[0] ? formatDayMonth(days[0]) : '',
    });
});

/**
 * The day of a movement, as 24/11.
 *
 * @param row - the movement
 * @returns the day
 */
function dayOf(row: App.Data.BankTransactionData): string {
    const day = row.booked_on ?? row.value_on;

    return day ? formatDayMonth(day) : '';
}

/**
 * The look of the tag of what the filter will do.
 *
 * @param tone - green for a link, blue for an action, grey for nothing
 * @returns the classes
 */
function tagClass(tone: 'paid' | 'action' | 'muted'): string {
    return cn(
        'inline-flex self-start rounded-tag px-2 py-[3px] text-meta-strong',
        tone === 'paid' && 'bg-paid-soft text-paid',
        tone === 'action' && 'bg-action-soft text-action-dark',
        tone === 'muted' && 'bg-card-sunken text-ink-soft',
    );
}
</script>

<template>
    <section
        :aria-label="
            $t('The movements it takes · :count', {
                count: String(preview.rows.length),
            })
        "
        :aria-busy="loading ? 'true' : undefined"
        class="flex flex-col gap-2.5"
    >
        <div class="flex flex-col gap-0.5 px-1">
            <h2 class="m-0 text-sheet-title">
                {{
                    $t('The movements it takes · :count', {
                        count: String(preview.rows.length),
                    })
                }}
            </h2>
            <span v-if="range" class="text-meta text-ink-soft">{{
                range
            }}</span>
        </div>
        <p v-if="error" role="alert" class="m-0 px-1 text-body-sm text-overdue">
            {{ error }}
        </p>
        <p
            v-if="preview.rows.length === 0"
            class="m-0 rounded-card bg-card px-4 py-3.5 text-body-sm text-ink-soft shadow-card"
        >
            {{ $t('No movement of the last 90 days matches.') }}
        </p>
        <ul
            v-else
            :class="
                cn(
                    'm-0 flex list-none flex-col divide-y divide-divider overflow-hidden rounded-card bg-card p-0 shadow-card transition-opacity',
                    loading && 'opacity-60',
                )
            "
        >
            <li
                v-for="row in rows"
                :key="row.transaction.id"
                class="flex flex-col gap-1.5 pt-3 pr-2 pb-2.5 pl-4"
            >
                <div class="flex items-start gap-3 pr-2">
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="text-meta-strong text-ink-soft">{{
                            dayOf(row.transaction)
                        }}</span>
                        <span class="text-body-sm wrap-anywhere text-ink"
                            ><template
                                v-for="(part, index) in highlightParts(
                                    row.transaction.text,
                                    row.hits.join(' '),
                                )"
                                :key="index"
                                ><mark
                                    v-if="part.match"
                                    class="rounded-xs bg-warning-soft px-0.5 text-label text-ink"
                                    >{{ part.text }}</mark
                                ><template v-else>{{
                                    part.text
                                }}</template></template
                            ></span
                        >
                        <span v-if="row.fee" class="text-meta text-ink-soft">{{
                            $t('+ :fee on :day', {
                                fee: money(
                                    Math.abs(row.fee.amount),
                                    row.transaction.currency,
                                ),
                                day: row.fee.booked_on
                                    ? formatDayMonth(row.fee.booked_on)
                                    : '',
                            })
                        }}</span>
                        <span
                            v-else-if="feeMode !== 'none'"
                            class="text-meta text-ink-faint"
                            >{{ $t('no commission') }}</span
                        >
                    </span>
                    <span
                        :class="
                            cn(
                                'shrink-0 text-amount whitespace-nowrap',
                                row.transaction.amount > 0 && 'text-paid',
                            )
                        "
                        >{{
                            money(
                                row.transaction.amount,
                                row.transaction.currency,
                            )
                        }}</span
                    >
                </div>
                <span
                    v-if="row.taken_by"
                    role="note"
                    class="mr-2 flex items-start gap-2 rounded-md bg-warning-soft px-2.5 py-2 text-meta text-warning"
                >
                    <TriangleAlert
                        aria-hidden="true"
                        class="mt-px size-4 shrink-0"
                    />
                    <span class="text-ink"
                        >{{ takenWords[0]
                        }}<strong class="text-meta-strong">{{
                            row.taken_by
                        }}</strong
                        >{{ takenWords[1] }}</span
                    >
                </span>
                <div class="flex flex-wrap items-center gap-2 pb-0.5">
                    <span :class="tagClass(willTag(row).tone)">{{
                        willTag(row).text
                    }}</span>
                </div>
            </li>
            <li v-if="!all && preview.rows.length > FIRST" class="flex">
                <button
                    type="button"
                    class="h-12 grow cursor-pointer border-0 bg-transparent text-row text-action hover:text-action-dark"
                    @click="all = true"
                >
                    {{ moreText }}
                </button>
            </li>
        </ul>
    </section>
</template>
