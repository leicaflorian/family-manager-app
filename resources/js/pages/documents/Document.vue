<script setup lang="ts">
/**
 * *A document*: the way back to *Documents*, the title, and the document.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { computed } from 'vue';
import FmDocumentDetail from '@/components/fm/FmDocumentDetail.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { documentSubtitle } from '@/lib/documents';
import { index } from '@/routes/documents';

const { document } = defineProps<{
    /** The document. */
    document: App.Data.DocumentDetailData;
}>();

const subtitle = computed(() => documentSubtitle(document));
</script>

<template>
    <Head :title="document.title" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <Link
            :href="index().url"
            class="flex items-center gap-1.5 self-start text-[15px] font-semibold no-underline"
        >
            <FmIcon :icon="ChevronLeft" :size="18" />
            {{ $t('Documents') }}
        </Link>
        <FmTitle :title="document.title" :subtitle="subtitle || undefined" />
        <FmDocumentDetail :document="document" />
    </div>
</template>
