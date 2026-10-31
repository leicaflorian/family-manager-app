<script setup lang="ts">
/**
 * A planned expense or income in a list: the icon of its category, the name
 * with the card and how it repeats below, who it is for, the next due date with
 * the account, and the amount. It leads to *A planned expense*.
 *
 * @group rows
 */
import { computed } from 'vue';
import { colorTile } from '@/lib/areas';
import { recurrenceText, shortDate } from '@/lib/budget';
import { expenseIcon } from '@/lib/icons';
import FmAmount from './FmAmount.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

const { row, href } = defineProps<{
    /** The planned expense or income. */
    row: App.Data.PlannedRowData;
    /** *A planned expense*, where the row leads. */
    href: string;
}>();

/** The second line: the card, then how it repeats. */
const subtitle = computed(() =>
    [
        row.card?.label,
        recurrenceText(
            row.unit as 'week' | 'month' | 'year',
            row.interval,
            row.start_date,
            row.recurring,
            row.end_date,
        ),
    ]
        .filter((part) => part)
        .join(' · '),
);
</script>

<template>
    <FmRow :title="row.title" :subtitle="subtitle" :href="href" :arrow="false">
        <template #before>
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                :style="colorTile(row.color)"
            >
                <FmIcon :icon="expenseIcon(row.icon)" :size="20" />
            </span>
        </template>
        <template #after>
            <span class="flex items-center gap-5">
                <span
                    class="hidden w-28 truncate text-sm font-normal text-ink-soft lg:block"
                    >{{ row.person ?? $t('Whole family') }}</span
                >
                <span
                    v-if="row.next_date"
                    class="hidden flex-col items-end text-[13px] font-normal text-ink-soft sm:flex"
                >
                    <span>{{
                        $t('Next on :date', { date: shortDate(row.next_date) })
                    }}</span>
                    <span v-if="row.account">{{ row.account }}</span>
                </span>
                <FmAmount
                    :amount="row.amount"
                    :estimated="row.estimated"
                    :income="row.kind === 'income'"
                />
            </span>
        </template>
    </FmRow>
</template>
