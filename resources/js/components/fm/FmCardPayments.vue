<script setup lang="ts">
/**
 * The planned expenses (or incomes) of a card and their payments, as the
 * boards of the cards and the column beside *Edit card* show them: the rows of
 * the planned items, which open *A planned expense*, with *Add* for an
 * `admin`; then the latest payments with *Record payment* and the link to all
 * of them, year by year. Without a planned item it says to create it first.
 */
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { create, show as showPlanned } from '@/routes/budget/planned';
import { payments as cardPayments } from '@/routes/cards';
import FmButton from './FmButton.vue';
import FmCard from './FmCard.vue';
import FmPayButton from './FmPayButton.vue';
import FmPaymentYears from './FmPaymentYears.vue';
import FmPlannedRow from './FmPlannedRow.vue';

const { budget, card } = defineProps<{
    /** The payments of the card. */
    budget: App.Data.CardBudgetData;
    /** The address of the card: its type and its number. */
    card: { type: string; id: number };
}>();

/** How many payments of the latest year the card shows. */
const LATEST = 2;

/** Whether the planned items are incomes, as the ones of an employment. */
const income = computed(() => budget.kind === 'income');

/** *Add*: the form of a new planned item, already linked to the card (an income takes its employment by itself). */
const addUrl = computed(
    () =>
        create(
            {
                kind: budget.kind,
            },
            income.value ? {} : { query: { card: `${card.type}:${card.id}` } },
        ).url,
);
</script>

<template>
    <FmCard v-if="budget.planned_label" :title="budget.planned_label" list>
        <template v-if="budget.can_add_planned" #actions>
            <FmButton variant="text" inline :icon="Plus" :href="addUrl">{{
                $t('Add')
            }}</FmButton>
        </template>
        <FmPlannedRow
            v-for="row in budget.planned"
            :key="`${row.kind}-${row.id}`"
            :row="row"
            :href="showPlanned({ kind: row.kind, id: row.id }).url"
        />
        <p
            v-if="budget.planned.length === 0"
            class="m-0 px-4 py-3 text-[15px] text-ink-soft"
        >
            {{ $t('Nothing planned yet.') }}
        </p>
    </FmCard>

    <div v-if="budget.payments_label" class="flex flex-col gap-2">
        <FmCard :title="budget.payments_label" list>
            <template v-if="budget.can_pay" #actions>
                <FmPayButton
                    :deadlines="budget.pay"
                    :label="income ? $t('Record income') : $t('Record payment')"
                />
            </template>
            <p
                v-if="
                    budget.planned.length === 0 && budget.payments.length === 0
                "
                class="m-0 px-4 py-3 text-[15px] text-ink-soft"
            >
                {{
                    income
                        ? $t('Create the planned income first.')
                        : $t('Create the planned expense first.')
                }}
            </p>
            <p
                v-else-if="budget.payments.length === 0"
                class="m-0 px-4 py-3 text-[15px] text-ink-soft"
            >
                {{ $t('No payments recorded.') }}
            </p>
            <FmPaymentYears v-else :years="budget.payments" :latest="LATEST" />
        </FmCard>
        <Link
            v-if="budget.payments.length > 0"
            :href="cardPayments.url(card)"
            class="px-1 text-sm font-semibold no-underline"
            >{{ budget.payments_more }}</Link
        >
    </div>
</template>
