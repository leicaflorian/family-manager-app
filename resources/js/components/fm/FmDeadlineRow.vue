<script setup lang="ts">
/**
 * A due date in a list: the dot of the area, the title, the card it belongs to
 * with its date, year included (red when late), and on the right the amount.
 * It leads to the address of the due date, which opens it on its planned item.
 *
 * @group rows
 */
import { computed } from 'vue';
import type { Area } from '@/lib/areas';
import { formatDate } from '@/lib/format';
import { trans } from 'laravel-vue-i18n';
import FmAmount from './FmAmount.vue';
import FmRow from './FmRow.vue';

const { deadline, href } = defineProps<{
    /** The due date. */
    deadline: App.Data.DeadlineData;
    /** The address of the due date, where the row leads. */
    href: string;
}>();

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
        :title="deadline.title"
        :subtitle="subtitle"
        :href="href"
        :area="deadline.card?.area as Area | undefined"
        :tone="deadline.late ? 'overdue' : 'normal'"
        :arrow="false"
    >
        <template #after>
            <FmAmount
                :amount="deadline.amount"
                :base="deadline.base_amount"
                :estimated="deadline.estimated"
                :income="deadline.kind === 'income'"
                :fee="deadline.fee"
            />
        </template>
    </FmRow>
</template>
