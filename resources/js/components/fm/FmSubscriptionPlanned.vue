<script setup lang="ts">
/**
 * The section *Planned expense* of a new subscription, after its fields: the
 * cost, how often, the first payment, the category and *I already paid it*.
 * With a cost the planned expense of the card is created when it is saved,
 * and with *I already paid it* its first payment too; nothing of it is stored
 * on the card. The first payment starts from the activation of the card and
 * follows it until the person picks another day (board *Nuovo-abbonamento*).
 *
 * @group forms
 */
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, watch } from 'vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { isoDate } from '@/lib/format';
import { formatAmount, parseAmount, withNone } from '@/lib/forms';
import type { SubscriptionPlan } from '@/lib/subscriptionPlan';

const { categories, activation } = defineProps<{
    /** The expense categories, with their path. */
    categories: App.Data.OptionData[];
    /** The activation date of the card as written now, `yyyy-mm-dd`. */
    activation: string | null;
}>();

/** The values of the section. */
const plan = defineModel<SubscriptionPlan>({ required: true });

/** The units of how often, as in the form of a planned expense. */
const units = computed(() => [
    { value: 'week', label: trans('Weeks') },
    { value: 'month', label: trans('Months') },
    { value: 'year', label: trans('Years') },
]);

/**
 * Changes one value of the section.
 *
 * @param key - the value
 * @param value - its new content
 */
function set<K extends keyof SubscriptionPlan>(
    key: K,
    value: SubscriptionPlan[K],
): void {
    plan.value = { ...plan.value, [key]: value };
}

/**
 * The day the first payment starts from: the activation, or today.
 *
 * @param day - the activation date
 * @returns a `yyyy-mm-dd` date
 */
function startFrom(day: string | null): string {
    return day ?? isoDate();
}

// Leaving with something written asks first; the first payment the section
// fills in from the activation is not something the person wrote.
useUnsavedChanges(() => ({
    ...plan.value,
    start_date:
        plan.value.start_date === startFrom(activation)
            ? null
            : plan.value.start_date,
}));

onMounted(() => {
    if (plan.value.start_date === null) {
        set('start_date', startFrom(activation));
    }
});

// The first payment follows the activation only while the person has not
// picked another day: it still holds the day the activation gave.
watch(
    () => activation,
    (now, before) => {
        const start = plan.value.start_date;

        if (start === null || start === startFrom(before)) {
            set('start_date', startFrom(now));
        }
    },
);
</script>

<template>
    <fieldset
        class="m-0 mt-2 flex min-w-0 flex-col gap-3.5 border-0 border-t border-solid border-edge-soft p-0 pt-4.5"
    >
        <legend class="contents">
            <span class="text-section">{{ $t('Planned expense') }}</span>
        </legend>
        <p class="m-0 -mt-2.5 text-body-sm text-ink-soft">
            {{
                $t(
                    'Optional: with the cost the planned expense is created too.',
                )
            }}
        </p>
        <FormKit
            :model-value="formatAmount(plan.amount)"
            type="currency"
            :label="$t('Cost')"
            name="planned_amount"
            @update:model-value="
                (value: unknown) => set('amount', parseAmount(value))
            "
        />
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
            <FormKit
                :model-value="plan.interval"
                type="number"
                number="integer"
                min="1"
                :label="$t('Every')"
                name="planned_interval"
                @update:model-value="
                    (value: unknown) =>
                        set('interval', Math.max(1, Number(value) || 1))
                "
            />
            <FormKit
                :model-value="plan.interval_unit"
                type="togglebuttons"
                enforced
                :label="$t('How often')"
                name="planned_interval_unit"
                :options="units"
                @update:model-value="
                    (value: unknown) =>
                        set(
                            'interval_unit',
                            value as SubscriptionPlan['interval_unit'],
                        )
                "
            />
        </div>
        <FormKit
            :model-value="plan.start_date ?? undefined"
            type="datepicker"
            :label="$t('First payment')"
            name="planned_start_date"
            @update:model-value="
                (value: unknown) =>
                    set(
                        'start_date',
                        typeof value === 'string' && value !== ''
                            ? value
                            : null,
                    )
            "
        />
        <FormKit
            :model-value="plan.category_id ?? ''"
            type="autocomplete"
            open-on-click
            :label="$t('Category')"
            name="planned_category_id"
            :options="withNone(categories)"
            @update:model-value="
                (value: unknown) =>
                    set(
                        'category_id',
                        typeof value === 'string' && value !== ''
                            ? value
                            : null,
                    )
            "
        />
        <FormKit
            :model-value="plan.paid"
            type="toggle"
            :label="$t('I already paid it')"
            name="planned_paid"
            @update:model-value="
                (value: unknown) => set('paid', value === true)
            "
        />
    </fieldset>
</template>
