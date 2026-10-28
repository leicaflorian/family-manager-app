<script setup lang="ts">
/**
 * The panel from below that asks again before an action that is easy to do by
 * mistake: the question, what happens, *Cancel* and the button that confirms.
 * `FmConfirmDelete` uses it to delete; the form of a document, to remove a
 * saved link.
 */
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

const { question, text, confirmLabel } = defineProps<{
    /** The title of the panel, for example «Delete this document?». */
    question: string;
    /** What happens, under the title. */
    text: string;
    /** The text of the button that confirms. */
    confirmLabel: string;
}>();

defineEmits<{
    /** The person confirmed. */
    confirm: [];
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="question">
        <p class="m-0 text-base leading-normal text-ink-soft">{{ text }}</p>
        <div class="flex flex-wrap justify-end gap-2.5">
            <FmButton variant="text" @click="open = false">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton variant="danger" @click="$emit('confirm')">{{
                confirmLabel
            }}</FmButton>
        </div>
    </FmBottomSheet>
</template>
