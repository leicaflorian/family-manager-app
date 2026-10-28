<script setup lang="ts">
/**
 * The panel from below that asks again before deleting: the question, what
 * happens, *Cancel* and the button that deletes. Opened by *Delete*, as a
 * button (`FmDeleteButton`) or as an entry of a menu.
 */
import { router } from '@inertiajs/vue3';
import FmConfirm from './FmConfirm.vue';

const { url, question, text, confirmLabel } = defineProps<{
    /** The address the deletion is sent to. */
    url: string;
    /** The title of the panel, for example «Delete this document?». */
    question: string;
    /** What happens, under the title. */
    text: string;
    /** The text of the button that deletes. */
    confirmLabel: string;
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });

/** Deletes, once confirmed. */
function remove(): void {
    router.delete(url, { onFinish: () => (open.value = false) });
}
</script>

<template>
    <FmConfirm
        v-model:open="open"
        :question="question"
        :text="text"
        :confirm-label="confirmLabel"
        @confirm="remove"
    />
</template>
