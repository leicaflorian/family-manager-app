<script setup lang="ts">
/**
 * *A due date*: the card it belongs to, the title, the amount and where it
 * stands (when it falls and with which account, or when and how it was paid),
 * with *I paid it* (*It arrived* for an income) and *Change this due date*,
 * which open their panels, and below the next due dates of the same planned
 * expense or income. A paid due date has no buttons, and its line leads to the
 * movement that paid it. A skipped one says so and has only *Change this due
 * date*, to bring it back. With `pay` the panel of
 * the payment is already open, as *Record payment* opens it.
 */
import { Head, Link } from '@inertiajs/vue3';
import { Check, Pencil } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmAmount from '@/components/fm/FmAmount.vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmChangeDeadlineSheet from '@/components/fm/FmChangeDeadlineSheet.vue';
import FmDeadlineRow from '@/components/fm/FmDeadlineRow.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmPaySheet from '@/components/fm/FmPaySheet.vue';
import { useFormat } from '@/composables/useFormat';
import { deadlineStatus, deadlineUrls } from '@/lib/budget';
import { dashboard } from '@/routes';
import { show as showArea } from '@/routes/areas';
import { show as showMovement } from '@/routes/budget/movements';
import { show as showCard } from '@/routes/cards';

const { deadline, canPay, canChange, pay } = defineProps<{
    /** The due date. */
    deadline: App.Data.DeadlineData;
    /** The next due dates of the same planned expense or income. */
    next: App.Data.DeadlineData[];
    /** The accounts of the family to pay with. */
    accounts: App.Data.OptionData[];
    /** The account may record payments. */
    canPay: boolean;
    /** The account may change a due date, which is for an `admin`. */
    canChange: boolean;
    /** The panel *I paid it* opens as soon as the page is shown. */
    pay: boolean;
}>();

const { euro } = useFormat();

/** The addresses of this due date. */
const urls = computed(() => deadlineUrls(deadline));

/** Whether it is an income. */
const income = computed(() => deadline.kind === 'income');

/** A payment settles it. */
const paid = computed(() => deadline.payment !== null);

/** It can be paid: nothing settles it and it is not skipped. */
const payable = computed(() => !paid.value && !deadline.skipped);

/** The panel of the payment is open: at once when asked, unless it is already paid or skipped. */
const paying = ref(pay && canPay && payable.value);

/** The panel that changes the due date is open. */
const changing = ref(false);

/** Where the page goes back to: the area of the card of the due date, else the home. */
const back = computed(() =>
    deadline.card === null
        ? { href: dashboard.url(), label: trans('Home') }
        : {
              href: showArea.url(deadline.card.area),
              label: deadline.card.area_label,
          },
);

/** The address of the card of the due date, if it has one. */
const cardHref = computed(() =>
    deadline.card === null
        ? null
        : showCard.url({ type: deadline.card.type, id: deadline.card.id }),
);

/** The page of the movement that settles the due date, if it is paid. */
const paymentHref = computed(() =>
    deadline.payment === null
        ? null
        : showMovement.url({
              kind: deadline.payment.kind,
              id: deadline.payment.id,
          }),
);

/** The line under the amount: where it stands, the payment or the account (not for a skipped one, which is not paid). */
const status = computed(() => {
    const payment = deadline.payment;
    const parts =
        payment === null
            ? [
                  deadlineStatus(deadline),
                  deadline.skipped ? null : deadline.account,
              ]
            : [deadlineStatus(deadline), euro(payment.amount), payment.account];

    return parts.filter((part) => part !== null && part !== '').join(' · ');
});
</script>

<template>
    <Head :title="deadline.title" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmBackLink :href="back.href" :label="back.label" />

        <div
            class="flex flex-wrap items-center justify-between gap-6 rounded-3xl bg-white p-5 lg:p-7"
        >
            <div class="flex min-w-0 flex-col gap-1.5">
                <Link
                    v-if="deadline.card && cardHref"
                    :href="cardHref"
                    class="text-sm font-semibold no-underline"
                    >{{ deadline.card.label }}</Link
                >
                <h1 class="m-0 text-[26px] font-extrabold">
                    {{ deadline.title }}
                </h1>
                <span class="text-[40px] font-extrabold whitespace-nowrap">
                    <FmAmount
                        :amount="deadline.amount"
                        :base="deadline.base_amount"
                        :estimated="deadline.estimated"
                        :income="income"
                    />
                </span>
                <Link
                    v-if="paymentHref"
                    :href="paymentHref"
                    class="text-[15px] font-semibold text-paid"
                    >{{ status }}</Link
                >
                <span
                    v-else
                    class="text-[15px]"
                    :class="
                        deadline.late
                            ? 'font-semibold text-overdue'
                            : 'text-ink-soft'
                    "
                    >{{ status }}</span
                >
            </div>
            <div
                v-if="!paid && (canChange || canPay)"
                class="flex flex-wrap gap-2.5"
            >
                <FmButton
                    v-if="canChange"
                    variant="outline"
                    :icon="Pencil"
                    @click="changing = true"
                    >{{ $t('Change this due date') }}</FmButton
                >
                <FmButton
                    v-if="canPay && payable"
                    :icon="Check"
                    @click="paying = true"
                    >{{ income ? $t('It arrived') : $t('I paid it') }}</FmButton
                >
            </div>
        </div>

        <FmNotice v-if="deadline.notes">{{ deadline.notes }}</FmNotice>

        <FmCard v-if="next.length > 0" :title="$t('Next due dates')" list>
            <FmDeadlineRow
                v-for="following in next"
                :key="following.original_date"
                :deadline="following"
                :href="deadlineUrls(following).show"
            />
        </FmCard>

        <FmPaySheet
            v-if="canPay && payable"
            v-model:open="paying"
            :deadline="deadline"
            :accounts="accounts"
            :action="urls.payment"
        />
        <FmChangeDeadlineSheet
            v-if="canChange && !paid"
            v-model:open="changing"
            :deadline="deadline"
            :action="urls.exceptions"
        />
    </div>
</template>
