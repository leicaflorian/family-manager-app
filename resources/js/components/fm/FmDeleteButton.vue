<script setup lang="ts">
/**
 * *Delete*, red, which asks again in the panel from below before deleting:
 * the question, what happens, *Cancel* and the button that deletes.
 *
 * Use it instead of a red `FmButton` every time something is deleted: a red
 * button that deletes at once is never right. `compact` leaves only the bin,
 * with `label` as its name, where a row or a panel has no room for the text.
 *
 * @group basics
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
    label,
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
    /** The name of the button, when it is not *Delete*: «Delete the copy». */
    label?: string;
}>();

/** The panel that asks before deleting. */
const confirming = ref(false);

// Two elements at the root: the class and the attributes of the page go to the button
defineOptions({ inheritAttrs: false });
</script>

<template>
    <FmButton
        v-bind="$attrs"
        variant="danger"
        :icon="Trash2"
        :label="compact ? (label ?? $t('Delete')) : undefined"
        @click="confirming = true"
    >
        {{ label ?? $t('Delete') }}
    </FmButton>
    <FmConfirmDelete
        v-model:open="confirming"
        :url="url"
        :question="question"
        :text="text"
        :confirm-label="confirmLabel"
    />
</template>
