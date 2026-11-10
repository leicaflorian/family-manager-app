<script setup lang="ts">
/**
 * *A document*: the way back to *Documents*, the title with *Download*, *Edit*,
 * *Delete* and the ⋯ menu on its right, and the document.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmDocumentActions from '@/components/fm/FmDocumentActions.vue';
import FmDocumentDetail from '@/components/fm/FmDocumentDetail.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { documentSubtitle } from '@/lib/documents';
import { index } from '@/routes/documents';
import { usePageTrail } from '@/composables/usePageTrail';

const { document } = defineProps<{
    /** The document. */
    document: App.Data.DocumentDetailData;
}>();

const subtitle = computed(() => documentSubtitle(document));

usePageTrail(() => [
    { label: trans('Documents'), href: index().url },
    { label: document.title },
]);
</script>

<template>
    <Head :title="document.title" />

    <div class="flex max-w-[1120px] flex-col gap-6">
        <FmTitle
            :title="document.title"
            :subtitle="subtitle || undefined"
            :back="index().url"
            :back-label="$t('Documents')"
        >
            <template #actions>
                <FmDocumentActions :document="document" />
            </template>
        </FmTitle>
        <FmDocumentDetail :document="document" />
    </div>
</template>
