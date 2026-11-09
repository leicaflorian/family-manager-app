<script setup lang="ts">
/**
 * The question «Leave without saving?» of the layouts: while a form has changes not saved, it asks before a
 * visit to another page, and the browser asks before the tab closes or reloads. One for the whole page, put in
 * each layout of the signed-in pages.
 */
import { onMounted, onUnmounted } from 'vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import { answerLeave, guardVisits, leaveQuestion } from '@/lib/unsavedChanges';

let stopGuard: (() => void) | undefined;
onMounted(() => {
    stopGuard = guardVisits();
});
onUnmounted(() => stopGuard?.());

/**
 * Closing the question in any way but «Leave without saving» is staying.
 *
 * @param open - the question is open
 */
function onQuestion(open: boolean): void {
    if (!open && leaveQuestion.open) {
        answerLeave(false);
    }
}
</script>

<template>
    <FmConfirm
        :open="leaveQuestion.open"
        :question="$t('Leave without saving?')"
        :text="$t('You have changes not saved: if you leave, you lose them.')"
        :confirm-label="$t('Leave without saving')"
        @update:open="onQuestion"
        @confirm="answerLeave(true)"
    />
</template>
