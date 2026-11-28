<script setup lang="ts">
/**
 * The form of a bank filter, new or changed. A placeholder until the task of its page (Task 14 of the plan
 * *From the bank*) replaces it: the server already sends the filter, the proposal, the preview and the choices.
 */
import { trans } from 'laravel-vue-i18n';
import { usePageTrail } from '@/composables/usePageTrail';
import { index as bankIndex } from '@/routes/budget/bank';

defineProps<{
    /** The filter being changed, null for a new one. */
    filter: App.Data.BankFilterData | null;
    /** The proposal of a new filter, not saved. */
    draft: App.Data.BankFilterData | null;
    /** The movements the filter takes and what saving it would do. */
    preview: App.Data.BankFilterPreviewData;
}>();

usePageTrail(() => [
    { label: trans('From the bank'), href: bankIndex().url },
    { label: trans('Filters') },
]);
</script>

<template>
    <div />
</template>
