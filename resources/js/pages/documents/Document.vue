<script setup lang="ts">
/**
 * *A document*: the way back to *Documents*, the title with *Download*, *Edit*,
 * *Delete* and the ⋯ menu on its right, and the document.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import FmDocumentActions from '@/components/fm/FmDocumentActions.vue';
import FmDocumentDetail from '@/components/fm/FmDocumentDetail.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { documentSubtitle } from '@/lib/documents';
import { usePageMeta } from '@/composables/usePageMeta';

const { document } = defineProps<{
    /** The document. */
    document: App.Data.DocumentDetailData;
}>();

const subtitle = computed(() => documentSubtitle(document));

const { title, head } = usePageMeta({
    title: () => document.title,
    parent: 'documents',
});
</script>

<template>
    <Head :title="head" />

    <div class="flex max-w-[1120px] flex-col gap-6">
        <FmTitle :title="title" :subtitle="subtitle || undefined">
            <template #actions>
                <FmDocumentActions :document="document" />
            </template>
        </FmTitle>
        <FmDocumentDetail :document="document" />
    </div>
</template>
