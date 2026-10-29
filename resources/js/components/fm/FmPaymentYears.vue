<script setup lang="ts">
/**
 * The payments of the planned items of a page by year, the latest first: a
 * line with the year (and, with `counts`, how many payments) and its total,
 * then each payment with its day, title, account, the clip of a receipt and
 * the amount. *A planned expense* and the cards list them; on a card only the
 * latest ones (`latest`), on *Payments of a card* every year, the older ones
 * folded, each payment leading to its page.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Paperclip } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { shortDate } from '@/lib/budget';
import { show as showMovement } from '@/routes/budget/movements';
import FmIcon from './FmIcon.vue';

const {
    years,
    latest = null,
    counts = null,
    folded = false,
    linked = false,
} = defineProps<{
    /** The payments by year, the latest year first. */
    years: App.Data.PaymentYearData[];
    /** Only the latest year, with at most this many payments. */
    latest?: number | null;
    /** The words of the number of payments of each year, for example «9 bills». */
    counts?: Record<number, string> | null;
    /** The years after the first are folded, and their line opens them. */
    folded?: boolean;
    /** Each payment leads to its page and shows the whole date. */
    linked?: boolean;
}>();

const { euro, date } = useFormat();

/** The years shown, with the payments of each. */
const shown = computed((): App.Data.PaymentYearData[] =>
    latest === null
        ? years
        : years.slice(0, 1).map((year) => ({
              ...year,
              payments: year.payments.slice(0, latest),
          })),
);

/** The folded years the person opened. */
const opened = ref<number[]>([]);

/**
 * Whether the payments of a year are shown.
 *
 * @param index - the place of the year, 0 for the latest
 * @param year - the year
 * @returns true unless the year is folded and not opened
 */
function isOpen(index: number, year: number): boolean {
    return !folded || index === 0 || opened.value.includes(year);
}

/**
 * Opens or folds a year.
 *
 * @param year - the year
 */
function toggle(year: number): void {
    opened.value = opened.value.includes(year)
        ? opened.value.filter((other) => other !== year)
        : [...opened.value, year];
}
</script>

<template>
    <template v-for="(year, index) in shown" :key="year.year">
        <button
            v-if="folded && index > 0"
            type="button"
            :aria-expanded="isOpen(index, year.year)"
            class="flex w-full cursor-pointer items-center gap-3 border-0 bg-canvas px-4 py-2 text-left text-[15px] font-bold text-ink"
            @click="toggle(year.year)"
        >
            <FmIcon
                :icon="ChevronDown"
                :size="18"
                :class="isOpen(index, year.year) && 'rotate-180'"
            />
            <span class="grow">{{ year.year }}</span>
            <span
                v-if="counts?.[year.year]"
                class="text-[13px] font-semibold text-ink-soft"
                >{{ counts[year.year] }}</span
            >
            <span>{{ euro(year.total) }}</span>
        </button>
        <div
            v-else
            class="flex items-center gap-3 bg-canvas px-4 py-2 text-[15px] font-bold"
        >
            <span class="grow">{{ year.year }}</span>
            <span
                v-if="counts?.[year.year]"
                class="text-[13px] font-semibold text-ink-soft"
                >{{ counts[year.year] }}</span
            >
            <span>{{ euro(year.total) }}</span>
        </div>
        <template v-if="isOpen(index, year.year)">
            <component
                :is="linked ? Link : 'div'"
                v-for="payment in year.payments"
                :key="`${payment.kind}-${payment.id}`"
                :href="
                    linked
                        ? showMovement.url({
                              kind: payment.kind,
                              id: payment.id,
                          })
                        : undefined
                "
                class="flex items-center gap-3 px-4 py-3 text-ink no-underline"
                :class="linked && 'hover:bg-canvas'"
            >
                <span
                    class="shrink-0 text-[15px] font-semibold"
                    :class="linked ? 'w-24' : 'w-11'"
                    >{{
                        linked ? date(payment.date) : shortDate(payment.date)
                    }}</span
                >
                <span class="flex min-w-0 grow flex-col gap-0.5">
                    <span class="truncate text-[15px] font-semibold">{{
                        payment.title
                    }}</span>
                    <span
                        v-if="payment.account"
                        class="truncate text-[13px] text-ink-soft"
                        >{{ payment.account }}</span
                    >
                </span>
                <FmIcon
                    v-if="payment.documents.length > 0"
                    :icon="Paperclip"
                    :size="18"
                    :label="$t('Receipt attached')"
                    class="text-ink-soft"
                />
                <span class="shrink-0 text-[15px] font-bold">{{
                    euro(payment.amount)
                }}</span>
            </component>
        </template>
    </template>
</template>
