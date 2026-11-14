<script setup lang="ts">
/**
 * *I paid it* (for an income *It arrived*): the panel that records the payment
 * of a due date, with `FmPayForm` inside and *Cancel* beside its button. It
 * starts from today, from the amount and from the account of the due date
 * every time it opens; the page then shows the due date as paid. On the phone
 * it rises from the bottom.
 *
 * @group panels
 */
import { ref, watch } from 'vue';
import { useFormat } from '@/composables/useFormat';
import FmBottomSheet from './FmBottomSheet.vue';
import FmPayForm from './FmPayForm.vue';

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

/** The form inside, to ask before closing and to start it again. */
const form = ref<InstanceType<typeof FmPayForm> | null>(null);

/**
 * Closing with the ×, Esc or a tap outside asks first when something changed.
 *
 * @returns whether the panel may close
 */
function confirmClose(): Promise<boolean> {
    return form.value?.confirmClose() ?? Promise.resolve(true);
}

// Opened again, the form starts again from today and from the due date
watch(open, (isOpen) => {
    if (isOpen) {
        form.value?.reset();
    } else {
        emit('close');
    }
});
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :before-close="confirmClose"
        :title="deadline.kind === 'income' ? $t('It arrived') : $t('I paid it')"
    >
        <p class="m-0 text-body-sm text-ink-soft">
            {{ deadline.title }}
            <template v-if="deadline.kind === 'income' && deadline.card">
                · {{ deadline.card.label }}
            </template>
            <template v-else>
                · <strong class="text-ink">{{ euro(deadline.amount) }}</strong>
            </template>
        </p>
        <FmPayForm
            ref="form"
            :deadline="deadline"
            :accounts="accounts"
            :action="action"
            :active="open"
            cancellable
            @saved="open = false"
            @cancel="open = false"
        />
    </FmBottomSheet>
</template>
