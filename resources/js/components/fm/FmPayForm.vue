<script setup lang="ts">
/**
 * The form that records the payment of a due date (for an income, that it
 * arrived): when, how much and with what, starting from today, from the amount
 * and from the account of the due date, and a receipt if the person has one.
 * For an income it also says what was expected and what the income will be
 * called. It sends the form to `action`. `FmPaySheet` puts it in a panel of
 * its own; the panel of a row of the Agenda shows it under the details, so the
 * payment takes one tap.
 *
 * @group panels
 */
import { router } from '@inertiajs/vue3';
import { Paperclip, Wallet } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { monthName } from '@/lib/budget';
import { errors } from '@/lib/errors';
import { formatAmount, parseAmount } from '@/lib/forms';
import { isoDate, relativeDays } from '@/lib/format';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmNotice from './FmNotice.vue';

const {
    deadline,
    accounts,
    action,
    submitLabel,
    cancellable = false,
    active = true,
} = defineProps<{
    /** The due date to settle. */
    deadline: App.Data.DeadlineData;
    /** The accounts of the family to choose from. */
    accounts: App.Data.OptionData[];
    /** Where the payment is sent (POST). */
    action: string;
    /** The text of the button that sends it; *Confirm* without it. */
    submitLabel?: string;
    /** Shows *Cancel* beside the button, which emits `cancel`. */
    cancellable?: boolean;
    /** The form is in use: only then do its changes count as unsaved. */
    active?: boolean;
}>();

const emit = defineEmits<{
    /** The payment was recorded. */
    saved: [];
    /** *Cancel* was tapped. */
    cancel: [];
}>();

const { euro } = useFormat();

/** Whether the due date is an income. */
const income = computed(() => deadline.kind === 'income');

/**
 * The values the form starts from: today, the amount and the account of the due date.
 *
 * @returns the fields, without a receipt
 */
function startValues(): {
    date: string;
    amount: string;
    financial_account_id: string;
    receipt: File | null;
} {
    return {
        date: isoDate(),
        amount: formatAmount(deadline.amount),
        financial_account_id:
            deadline.account_id === null ? '' : String(deadline.account_id),
        receipt: null,
    };
}

/** The fields of the form. */
const values = reactive(startValues());

/** The changes not saved, while the form is in use. */
const changes = useUnsavedChanges(() => values, { active: () => active });

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The payment is being sent. */
const processing = ref(false);

/** The button that chooses the receipt opens this hidden picker. */
const picker = ref<HTMLInputElement | null>(null);

/** Puts the form back to the due date: today, its amount and account, no file, no messages. */
function reset(): void {
    Object.assign(values, startValues());
    problems.value = {};
    changes.reset();
}

// Another due date: the form starts again from it
watch(
    () => `${deadline.kind}:${deadline.planned_id}:${deadline.original_date}`,
    reset,
);

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
                changes.reset();
                emit('saved');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

defineExpose({ confirmClose: changes.confirmClose, reset });
</script>

<template>
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
            class="m-0 -mt-1 flex items-start gap-1.5 text-meta text-ink-soft"
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
                class="flex h-control-lg cursor-pointer items-center justify-center gap-2 rounded-lg border-[1.5px] border-dashed border-ink-faint bg-card px-3 text-row text-action"
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
                class="m-0 text-meta-strong text-overdue"
            >
                {{ problems.receipt }}
            </p>
        </div>
        <FmNotice v-if="income">
            <span>{{ becomes }}</span>
        </FmNotice>
        <div v-if="cancellable" class="flex justify-end gap-2.5">
            <FmButton variant="text" @click="emit('cancel')">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton type="submit" :disabled="processing">{{
                submitLabel ?? $t('Confirm')
            }}</FmButton>
        </div>
        <FmButton
            v-else
            type="submit"
            :icon="Wallet"
            wide
            :disabled="processing"
            >{{ submitLabel ?? $t('Confirm') }}</FmButton
        >
    </form>
</template>
