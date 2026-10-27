<script setup lang="ts">
/**
 * *Delete*, red, which asks again in the panel from below before deleting:
 * the question, what happens, *Cancel* and the button that deletes.
 */
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

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

/** The panel that asks before deleting. */
const confirming = ref(false);

/** Deletes, once confirmed. */
function remove(): void {
    router.delete(url, { onFinish: () => (confirming.value = false) });
}
</script>

<template>
    <FmButton variant="danger" :icon="Trash2" @click="confirming = true">
        {{ $t('Delete') }}
    </FmButton>
    <FmBottomSheet v-model:open="confirming" :title="question">
        <p class="m-0 text-base leading-normal text-ink-soft">{{ text }}</p>
        <div class="flex flex-wrap justify-end gap-2.5">
            <FmButton variant="text" @click="confirming = false">{{
                $t('Cancel')
            }}</FmButton>
            <FmButton variant="danger" @click="remove">{{
                confirmLabel
            }}</FmButton>
        </div>
    </FmBottomSheet>
</template>
