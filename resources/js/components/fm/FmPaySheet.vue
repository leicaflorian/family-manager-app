<script setup lang="ts">
/**
 * *I paid it* (for an income *It arrived*): the panel that records the payment
 * of a due date. It asks when, how much and with what, starting from today,
 * from the amount and from the account of the due date, and takes a receipt if
 * the person has one. For an income it also says what was expected and what
 * the income will be called. It sends the form to `action`; the page then
 * shows the due date as paid. On the phone it rises from the bottom.
 */
import { router } from '@inertiajs/vue3';
import { Paperclip } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { longMonth, monthName } from '@/lib/budget';
import { errors } from '@/lib/errors';
import { formatAmount, parseAmount } from '@/lib/forms';
import { isoDate, relativeDays } from '@/lib/format';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmNotice from './FmNotice.vue';

const { deadline, accounts, action } = defineProps<{
    /** The due date to settle. */
    deadline: App.Data.DeadlineData;
    /** The accounts of the family to choose from. */
    accounts: App.Data.OptionData[];
    /** Where the payment is sent (POST). */
    action: string;
}>();

const emit = defineEmits<{
    /** The panel was closed, by giving up or after the payment was recorded. */
    close: [];
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

const { euro } = useFormat();

/** Whether the due date is an income. */
const income = computed(() => deadline.kind === 'income');

/** The fields of the form. */
const values = reactive({
    date: isoDate(),
    amount: formatAmount(deadline.amount),
    financial_account_id:
        deadline.account_id === null ? '' : String(deadline.account_id),
    receipt: null as File | null,
});

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The payment is being sent. */
const processing = ref(false);

/** The button that chooses the receipt opens this hidden picker. */
const picker = ref<HTMLInputElement | null>(null);

// Opened again, the form starts again from today and from the due date
watch(open, (isOpen) => {
    if (isOpen) {
        values.date = isoDate();
        values.amount = formatAmount(deadline.amount);
        values.financial_account_id =
            deadline.account_id === null ? '' : String(deadline.account_id);
        values.receipt = null;
        problems.value = {};
    } else {
        emit('close');
    }
});

/** The text of the button of the file. */
const fileLabel = computed(() =>
    income.value && deadline.income_type === 'salary'
        ? trans('Add the payslip (optional)')
        : trans('Add the receipt (optional)'),
);

/** What the income will be called: the planned title with the month of the due date. */
const incomeTitle = computed(
    () =>
        `${deadline.planned_title} ${monthName(deadline.original_date, false)}`,
);

/** The box that says what the income becomes. */
const becomes = computed(() => {
    const params = {
        title: incomeTitle.value,
        person: deadline.person ?? '',
        link: deadline.card?.label ?? '',
    };

    if (deadline.person && deadline.card) {
        return trans(
            'It becomes the income :title of :person, linked to :link.',
            params,
        );
    }

    if (deadline.person) {
        return trans('It becomes the income :title of :person.', params);
    }

    return deadline.card
        ? trans('It becomes the income :title, linked to :link.', params)
        : trans('It becomes the income :title.', params);
});

/** Keeps the file the person chose. */
function pickReceipt(event: Event): void {
    values.receipt = (event.target as HTMLInputElement).files?.[0] ?? null;
}

/** Sends the payment. A receipt makes it a form with files, which PHP only reads as a POST. */
function save(): void {
    processing.value = true;
    problems.value = {};

    router.post(
        action,
        {
            date: values.date,
            amount: parseAmount(values.amount),
            financial_account_id: values.financial_account_id || null,
            ...(values.receipt ? { receipt: values.receipt } : {}),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (messages) => {
                problems.value = messages;
            },
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :title="income ? $t('It arrived') : $t('I paid it')"
    >
        <p class="m-0 text-[15px] text-[#4A5565]">
            {{ deadline.title }}
            <template v-if="income && deadline.card">
                · {{ deadline.card.label }}
            </template>
            <template v-else>
                · <strong class="text-ink">{{ euro(deadline.amount) }}</strong>
            </template>
        </p>
        <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="values.date"
                    type="datepicker"
                    :label="$t('When')"
                    name="date"
                    :errors="errors(problems.date)"
                />
                <FormKit
                    v-model="values.amount"
                    type="currency"
                    :label="$t('How much')"
                    name="amount"
                    :errors="errors(problems.amount)"
                />
            </div>
            <p
                v-if="income"
                class="m-0 -mt-1 flex items-start gap-1.5 text-[13px] text-ink-soft"
            >
                {{
                    $t(
                        'It was expected :when, :amount. If the payslip says a different figure, correct it.',
                        {
                            when: relativeDays(deadline.date),
                            amount: euro(deadline.amount),
                        },
                    )
                }}
            </p>
            <FormKit
                v-model="values.financial_account_id"
                type="togglebuttons"
                enforced
                :label="income ? $t('Into which account') : $t('With what')"
                name="financial_account_id"
                :options="accounts"
                :errors="errors(problems.financial_account_id)"
            />
            <div class="flex flex-col gap-1.5">
                <button
                    type="button"
                    class="flex h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-[#9AA6B6] bg-white px-3 text-[15px] font-semibold text-action"
                    @click="picker?.click()"
                >
                    <FmIcon :icon="Paperclip" :size="18" />
                    <span class="truncate">{{
                        values.receipt ? values.receipt.name : fileLabel
                    }}</span>
                </button>
                <input
                    ref="picker"
                    type="file"
                    class="hidden"
                    :aria-label="fileLabel"
                    accept="application/pdf,image/jpeg,image/png,image/heic,image/heif,.heic,.heif"
                    @change="pickReceipt"
                />
                <p
                    v-if="problems.receipt"
                    class="m-0 text-[13px] font-semibold text-overdue"
                >
                    {{ problems.receipt }}
                </p>
            </div>
            <FmNotice v-if="income">
                <span>{{ becomes }}</span>
            </FmNotice>
            <div class="flex justify-end gap-2.5">
                <FmButton variant="text" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton type="submit" :disabled="processing">{{
                    $t('Confirm')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
