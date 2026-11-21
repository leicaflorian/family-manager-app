<script setup lang="ts">
/**
 * The panel of a consumption written by hand, on the Consumption page: *Correct the consumption* of a bill (✎) or
 * *Add a consumption* (by hand, or for a bill whose reading did not find it: *Write it* of *To check*). On top the
 * bill and its utility, then the period, the consumption in the unit of the utility, the amount and *Estimated*.
 * A consumption saved here is never read again from its bill. *Delete* only for who may delete it.
 *
 * @group forms
 */
import { router } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { Droplet, Flame, Zap } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { billTitle } from '@/lib/consumption';
import { errors } from '@/lib/errors';
import { unitLabel } from '@/lib/format';
import { formatAmount, parseAmount } from '@/lib/forms';
import { destroy, store, update } from '@/routes/budget/consumption';
import { show as documentPage } from '@/routes/documents';

const {
    utility,
    bill = null,
    document = null,
} = defineProps<{
    /** The utility the consumption belongs to. */
    utility: Pick<
        App.Data.Consumption.ConsumptionPageData['utility'],
        'id' | 'title' | 'type' | 'unit'
    >;
    /** The consumption to correct; none to add one. */
    bill?: App.Data.Consumption.ConsumptionBillData | null;
    /** The bill whose consumption is written (*Write it* of *To check*), for a new one. */
    document?: { id: number; title: string } | null;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

/** The fields of the panel, as the fields hold them: dates `yyyy-mm-dd`, numbers as text. */
interface Values {
    period_start: string;
    period_end: string;
    quantity: string;
    amount: string;
    is_estimated: boolean;
}

/**
 * The fields as they start: the ones of the consumption to correct, or empty.
 *
 * @returns the values
 */
function startValues(): Values {
    return {
        period_start: bill?.period_start ?? '',
        period_end: bill?.period_end ?? '',
        quantity: bill === null ? '' : String(bill.quantity),
        amount: formatAmount(bill?.amount),
        is_estimated: bill?.is_estimated ?? false,
    };
}

const values = reactive<Values>(startValues());

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The consumption is being sent. */
const processing = ref(false);

/** Asks before leaving or closing with changes not saved. */
const changes = useUnsavedChanges(() => ({ ...values }), {
    active: () => open.value,
});

// Opened again, the panel starts from the consumption it shows now
watch(open, (isOpen) => {
    if (isOpen) {
        Object.assign(values, startValues());
        problems.value = {};
        changes.reset();
    }
});

/** The icon of the utility: a flame for gas, a drop for water, a bolt for electricity. */
const icon = computed(
    (): LucideIcon =>
        ({ gas: Flame, water: Droplet })[utility.type as string] ?? Zap,
);

/** The bill on top of the panel: its name, its second line and its document. */
const heading = computed(
    (): {
        name: string;
        line: string | null;
        documentId: number | null;
    } => {
        if (bill !== null) {
            return {
                name: billTitle(bill),
                line: trans(
                    bill.source === 'manual'
                        ? ':utility · written by hand'
                        : ':utility · read from the bill',
                    { utility: utility.title },
                ),
                documentId: bill.document_id,
            };
        }

        if (document !== null) {
            return {
                name: document.title,
                line: utility.title,
                documentId: document.id,
            };
        }

        return { name: utility.title, line: null, documentId: null };
    },
);

/** Whether saving keeps it from being read again from its bill: a consumption of a bill, not yet by hand. */
const keepsFromReading = computed(
    (): boolean =>
        (bill !== null && bill.source !== 'manual') || document !== null,
);

/** Sends the consumption: a correction to its row, a new one to the utility. */
function save(): void {
    const data = {
        period_start: values.period_start,
        period_end: values.period_end,
        quantity: parseAmount(values.quantity),
        amount: parseAmount(values.amount),
        is_estimated: values.is_estimated,
    };
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: (): void => {
            processing.value = true;
            problems.value = {};
        },
        onError: (messages: Record<string, string>): void => {
            problems.value = messages;
        },
        onSuccess: (): void => {
            changes.reset();
            open.value = false;
        },
        onFinish: (): void => {
            processing.value = false;
        },
    };

    if (bill !== null) {
        router.put(update(bill.id).url, data, options);

        return;
    }

    router.post(
        store(utility.id).url,
        document === null ? data : { ...data, document_id: document.id },
        options,
    );
}
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :before-close="changes.confirmClose"
        :title="
            bill === null
                ? $t('Add a consumption')
                : $t('Correct the consumption')
        "
    >
        <div class="flex items-center gap-3 rounded-xl bg-canvas px-3.5 py-3">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-home-soft text-home-text"
            >
                <FmIcon :icon="icon" :size="20" />
            </span>
            <span class="flex min-w-0 grow flex-col gap-0.5">
                <span class="truncate text-body-strong">{{
                    heading.name
                }}</span>
                <span
                    v-if="heading.line"
                    class="truncate text-meta text-ink-soft"
                    >{{ heading.line }}</span
                >
            </span>
            <a
                v-if="heading.documentId !== null"
                :href="documentPage(heading.documentId).url"
                class="shrink-0 text-label text-action no-underline hover:text-action-dark"
                >{{ $t('View') }}</a
            >
        </div>
        <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="values.period_start"
                    type="datepicker"
                    name="period_start"
                    :label="$t('From')"
                    :errors="errors(problems.period_start)"
                />
                <FormKit
                    v-model="values.period_end"
                    type="datepicker"
                    name="period_end"
                    :label="$t('To')"
                    :errors="errors(problems.period_end)"
                />
            </div>
            <FormKit
                v-model="values.quantity"
                type="number"
                step="any"
                name="quantity"
                :label="$t('Consumption')"
                :help="
                    $t(
                        'What this bill charges, not the yearly consumption nor the meter reading.',
                    )
                "
                :errors="errors(problems.quantity)"
            >
                <template #suffix>
                    <span class="shrink-0 pr-3.5 text-ink-soft">{{
                        unitLabel(utility.unit)
                    }}</span>
                </template>
            </FormKit>
            <FormKit
                v-model="values.amount"
                type="currency"
                name="amount"
                :label="$t('Amount')"
                :help="
                    $t(
                        'The total of the bill, with transport, charges and VAT.',
                    )
                "
                :errors="errors(problems.amount)"
            />
            <FormKit
                v-model="values.is_estimated"
                type="toggle"
                name="is_estimated"
                :label="$t('Estimated')"
                :help="
                    $t(
                        'The supplier estimated them, they were not read from the meter.',
                    )
                "
            />
            <p v-if="keepsFromReading" class="m-0 text-meta text-ink-soft">
                {{
                    $t(
                        "If you correct it, I won't read it again from the bill.",
                    )
                }}
            </p>
            <div class="flex flex-wrap items-center gap-2.5 pt-1">
                <FmDeleteButton
                    v-if="bill?.can_delete"
                    :url="destroy(bill.id).url"
                    :question="$t('Delete this consumption?')"
                    :text="$t('Its bill stays.')"
                    :confirm-label="$t('Delete the consumption')"
                    preserve-scroll
                />
                <span class="grow" />
                <FmButton variant="neutral" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton type="submit" :disabled="processing">{{
                    $t('Save')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
