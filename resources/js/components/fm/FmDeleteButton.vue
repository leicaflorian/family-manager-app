<script setup lang="ts">
/**
 * *Delete*, red, which asks again in the panel from below before deleting:
 * the question, what happens, *Cancel* and the button that deletes.
 */
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import FmButton from './FmButton.vue';
import FmConfirmDelete from './FmConfirmDelete.vue';

const {
    url,
    question,
    text,
    confirmLabel,
    compact = false,
} = defineProps<{
    /** The address the deletion is sent to. */
    url: string;
    /** The title of the panel, for example «Delete this document?». */
    question: string;
    /** What happens, under the title. */
    text: string;
    /** The text of the button that deletes. */
    confirmLabel: string;
    /** Only the bin, without the text, as in the panel beside the list. */
    compact?: boolean;
}>();

/** The panel that asks before deleting. */
const confirming = ref(false);
</script>

<template>
    <FmButton
        variant="danger"
        :icon="Trash2"
        :label="compact ? $t('Delete') : undefined"
        @click="confirming = true"
    >
        {{ $t('Delete') }}
    </FmButton>
    <FmConfirmDelete
        v-model:open="confirming"
        :url="url"
        :question="question"
        :text="text"
        :confirm-label="confirmLabel"
    />
</template>
