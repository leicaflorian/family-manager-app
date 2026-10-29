<script setup lang="ts">
/**
 * *Payments of a card*: what the card paid (or received) this year and the
 * average payment, *Record payment*, and every payment of its planned items
 * year by year, the older years folded, each payment leading to its page.
 * Below, it says which payments these are: the ones linked to the planned
 * items of the card.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmNumberTile from '@/components/fm/FmNumberTile.vue';
import FmPayButton from '@/components/fm/FmPayButton.vue';
import FmPaymentYears from '@/components/fm/FmPaymentYears.vue';
import { listNames } from '@/lib/names';
import { show } from '@/routes/cards';

const { card, budget } = defineProps<{
    /** The card. */
    card: App.Data.CardData;
    /** Its payments, with its planned items. */
    budget: App.Data.CardBudgetData;
    /** What was paid this year and the average payment. */
    numbers: App.Data.CardNumberData[];
    /** The words of the number of payments of each year, for example «9 bills». */
    counts: Record<number, string>;
}>();

/** Whether the planned items are incomes, as the ones of an employment. */
const income = computed(() => budget.kind === 'income');

/** The note below the list: which payments it holds. */
const note = computed((): string => {
    const planned = listNames(budget.planned.map((row) => row.title));

    return income.value
        ? trans(
              'These are the incomes linked to :planned. An income without that link does not show here: you link it from its form, in the field Planned income.',
              { planned },
          )
        : trans(
              'These are the payments linked to :planned. A payment without that link does not show here: you link it from its form, in the field Planned expense.',
              { planned },
          );
});
</script>

<template>
    <Head :title="`${budget.payments_label} · ${card.label}`" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmBackLink
            :href="show.url({ type: card.type, id: card.id })"
            :label="card.label"
        />

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1.5">
                <span class="text-sm font-semibold text-ink-soft">{{
                    `${card.area_label} · ${card.type_label}`
                }}</span>
                <h1 class="m-0 text-[28px] leading-tight font-extrabold">
                    {{ budget.payments_label }}
                </h1>
            </div>
            <FmPayButton
                v-if="budget.can_pay"
                :deadlines="budget.pay"
                :label="income ? $t('Record income') : $t('Record payment')"
            />
        </div>

        <div
            v-if="numbers.length > 0"
            class="grid grid-cols-2 gap-3 lg:max-w-[560px]"
        >
            <FmNumberTile
                v-for="number in numbers"
                :key="number.label"
                :number="number"
            />
        </div>

        <div
            data-testid="payments"
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white"
        >
            <FmPaymentYears
                v-if="budget.payments.length > 0"
                :years="budget.payments"
                :counts="counts"
                folded
                linked
            />
            <p v-else class="m-0 px-4 py-3 text-[15px] text-ink-soft">
                {{ $t('No payments recorded.') }}
            </p>
        </div>

        <p
            v-if="budget.planned.length > 0"
            class="m-0 text-[13px] text-ink-soft"
        >
            {{ note }}
        </p>
    </div>
</template>
