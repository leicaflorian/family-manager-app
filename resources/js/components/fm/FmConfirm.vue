<script setup lang="ts">
/**
 * The panel from below that asks again before an action that is easy to do by
 * mistake: the question, what happens, *Cancel* and the button that confirms.
 * It sits above the other panels, also when it is asked from one of them.
 * `FmConfirmDelete` uses it to delete; the form of a document, to remove a
 * saved link.
 *
 * @group basics
 */
import FmBottomSheet from './FmBottomSheet.vue';
import type { ButtonVariant } from './FmButton.vue';
import FmButton from './FmButton.vue';

const {
    question,
    text,
    confirmLabel,
    cancelLabel,
    confirmVariant = 'danger',
} = defineProps<{
    /** The title of the panel, for example «Delete this document?». */
    question: string;
    /** What happens, under the title. */
    text: string;
    /** The text of the button that confirms. */
    confirmLabel: string;
    /** The text of the button that does not confirm (*Keep*, *Not yet*); *Cancel* by default. */
    cancelLabel?: string;
    /** The look of the button that confirms: red for a loss, solid for an action that loses nothing (*Upload*). */
    confirmVariant?: ButtonVariant;
}>();

defineEmits<{
    /** The person confirmed. */
    confirm: [];
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="question" above>
        <p class="m-0 text-body text-ink-soft">{{ text }}</p>
        <div class="flex flex-wrap justify-end gap-2.5">
            <FmButton variant="text" data-autofocus @click="open = false">{{
                cancelLabel ?? $t('Cancel')
            }}</FmButton>
            <FmButton :variant="confirmVariant" @click="$emit('confirm')">{{
                confirmLabel
            }}</FmButton>
        </div>
    </FmBottomSheet>
</template>
