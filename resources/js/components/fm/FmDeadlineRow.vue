<script setup lang="ts">
/**
 * A due date in a list: the dot of the area, the title, the card it belongs to
 * with its date, year included (red when late), and on the right the amount.
 * It leads to *A due date*; `current` marks the due date of the page. `dated`
 * writes it as the lists of one planned item do (*A planned expense*, *A due
 * date*): the date as the title, where it stands below, no area and no title,
 * since every row has the same.
 *
 * @group rows
 */
import { computed } from 'vue';
import type { Area } from '@/lib/areas';
import { deadlineStanding } from '@/lib/budget';
import { formatDate } from '@/lib/format';
import { trans } from 'laravel-vue-i18n';
import FmAmount from './FmAmount.vue';
import FmRow from './FmRow.vue';

const {
    deadline,
    href,
    current = false,
    dated = false,
} = defineProps<{
    /** The due date. */
    deadline: App.Data.DeadlineData;
    /** *A due date*, where the row leads. */
    href: string;
    /** It is the due date of the page: the row is highlighted. */
    current?: boolean;
    /** In the list of one planned item: the date as the title, where it stands below. */
    dated?: boolean;
}>();

/** Where it stands, for a `dated` row: paid, late, changed, or nothing. */
const standing = computed(() => deadlineStanding(deadline));

/** The second line: whose it is, then the date with the year, with «late» if it is. */
const subtitle = computed(() =>
    [
        deadline.card?.label ??
            (deadline.title === deadline.planned_title
                ? null
                : deadline.planned_title),
        deadline.late
            ? trans(':date, late', { date: formatDate(deadline.date) })
            : formatDate(deadline.date),
    ]
        .filter((part) => part !== null)
        .join(' · '),
);
</script>

<template>
    <FmRow
        :title="dated ? formatDate(deadline.date) : deadline.title"
        :subtitle="dated ? (standing.text ?? undefined) : subtitle"
        :href="href"
        :area="dated ? undefined : (deadline.card?.area as Area | undefined)"
        :tone="dated ? standing.tone : deadline.late ? 'overdue' : 'normal'"
        :arrow="false"
        :current="current"
    >
        <template #after>
            <FmAmount
                :amount="deadline.amount"
                :base="deadline.base_amount"
                :estimated="deadline.estimated"
                :income="deadline.kind === 'income'"
            />
        </template>
    </FmRow>
</template>
