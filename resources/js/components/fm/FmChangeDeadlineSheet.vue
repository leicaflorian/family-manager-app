<script setup lang="ts">
/**
 * *Change this due date*: the panel that changes one due date and not the
 * planned expense. Three choices, more than one at a time: it is skipped
 * (which turns the other two off), it has another amount, it has another
 * date; and a note. It sends them to `action` as the server reads them.
 *
 * @group panels
 */
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { errors } from '@/lib/errors';
import { formatAmount, parseAmount } from '@/lib/forms';
import FmAmount from './FmAmount.vue';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

const { deadline, action } = defineProps<{
    /** The due date to change. */
    deadline: App.Data.DeadlineData;
    /** Where the changes are sent (PUT). */
    action: string;
}>();

const emit = defineEmits<{
    /** The panel was closed, by giving up or after the changes were saved. */
    close: [];
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

const { euro, date } = useFormat();

/**
 * The choices a due date already has: skipped, an amount other than the planned
 * one, a day other than the one of the rules, and its notes.
 *
 * @returns the fields of the form
 */
function initial() {
    return {
        skip: deadline.skipped,
        change_amount: deadline.base_amount !== null,
        amount: formatAmount(deadline.amount),
        change_date: deadline.date !== deadline.original_date,
        date: deadline.date,
        notes: deadline.notes ?? '',
    };
}

/** The fields of the form. */
const values = reactive(initial());

/** The changes not saved: closing with the × asks first, *Cancel* does not. */
const changes = useUnsavedChanges(() => values, { active: () => open.value });

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The changes are being sent. */
const processing = ref(false);

/** The amount the planned item says, which the new one replaces. */
const plannedAmount = computed(() => deadline.base_amount ?? deadline.amount);

// A due date that does not happen has no other amount or day
watch(
    () => values.skip,
    (skipped) => {
        if (skipped) {
            values.change_amount = false;
            values.change_date = false;
        }
    },
);

// Opened again, the form starts again from the due date
watch(open, (isOpen) => {
    if (isOpen) {
        Object.assign(values, initial());
        problems.value = {};
        changes.reset();
    } else {
        emit('close');
    }
});

/** Sends the changes. */
function save(): void {
    processing.value = true;
    problems.value = {};

    router.put(
        action,
        {
            skip: values.skip,
            change_amount: values.change_amount,
            ...(values.change_amount
                ? { amount: parseAmount(values.amount) }
                : {}),
            change_date: values.change_date,
            ...(values.change_date ? { date: values.date } : {}),
            notes: values.notes,
        },
        {
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
        :before-close="changes.confirmClose"
        :title="$t('Change this due date')"
    >
        <div class="flex items-center gap-3 rounded-card bg-canvas p-3.5">
            <span class="flex min-w-0 grow flex-col">
                <span class="truncate text-amount">{{
                    deadline.planned_title
                }}</span>
                <span class="truncate text-meta text-ink-soft">{{
                    $t(':subject · due on :date', {
                        subject: deadline.card?.label ?? deadline.planned_title,
                        date: date(deadline.original_date),
                    })
                }}</span>
            </span>
            <span class="shrink-0 text-amount">
                <FmAmount :amount="plannedAmount" />
            </span>
        </div>
        <form class="flex flex-col gap-3" novalidate @submit.prevent="save">
            <fieldset class="m-0 flex flex-col gap-2.5 border-0 p-0">
                <legend class="mb-1.5 p-0 text-label text-ink-soft">
                    {{
                        $t(
                            'What changes this time (more than one thing is fine)',
                        )
                    }}
                </legend>
                <FormKit
                    v-model="values.skip"
                    type="toggle"
                    name="skip"
                    :label="$t('Skip this due date')"
                    :help="$t('The due date disappears')"
                />
                <FormKit
                    v-model="values.change_amount"
                    type="toggle"
                    name="change_amount"
                    :disabled="values.skip"
                    :label="$t('Different amount')"
                    :help="
                        $t('Instead of :amount', {
                            amount: euro(plannedAmount),
                        })
                    "
                />
                <FormKit
                    v-if="values.change_amount"
                    v-model="values.amount"
                    type="currency"
                    name="amount"
                    :label="$t('New amount')"
                    :errors="errors(problems.amount)"
                />
                <FormKit
                    v-model="values.change_date"
                    type="toggle"
                    name="change_date"
                    :disabled="values.skip"
                    :label="$t('Different date')"
                    :help="
                        $t('Instead of :date', {
                            date: date(deadline.original_date),
                        })
                    "
                />
                <FormKit
                    v-if="values.change_date"
                    v-model="values.date"
                    type="datepicker"
                    name="date"
                    :label="$t('New date')"
                    :errors="errors(problems.date)"
                />
            </fieldset>
            <FormKit
                v-model="values.notes"
                type="text"
                name="notes"
                :label="$t('Note')"
                :errors="errors(problems.notes)"
            />
            <p class="m-0 text-meta text-ink-soft">
                {{
                    $t(
                        'Only this due date changes. The others stay as the planned expense says.',
                    )
                }}
            </p>
            <div class="flex justify-end gap-2.5">
                <FmButton variant="text" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton type="submit" :disabled="processing">{{
                    $t('Save')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
