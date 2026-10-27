<script setup lang="ts">
/**
 * *Edit a document*: every field and the links, with the file it belongs to.
 * *Cancel* goes back to the document.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, X } from '@lucide/vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { DocumentFormOptions } from '@/lib/documents';
import { documentValues } from '@/lib/documents';
import { show, update } from '@/routes/documents';

const { document, options } = defineProps<{
    /** The document as it is. */
    document: App.Data.DocumentDetailData;
    /** The choices of the form. */
    options: DocumentFormOptions;
}>();
</script>

<template>
    <Head :title="$t('Edit the document')" />

    <div class="flex max-w-[640px] flex-col gap-4">
        <FmTitle :title="$t('Edit the document')">
            <template #actions>
                <Link
                    :href="show.url(document.id)"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-xl border border-edge bg-white text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>
        <a
            :href="document.file.url"
            target="_blank"
            rel="noopener"
            class="flex items-center gap-3 rounded-[14px] bg-white px-3 py-2.5 text-inherit no-underline"
        >
            <FmIcon :icon="FileText" :size="22" class="text-ink-soft" />
            <span class="min-w-0 grow text-[15px] font-semibold break-all">{{
                document.file.name
            }}</span>
        </a>
        <section class="rounded-3xl bg-white p-5 lg:p-7">
            <FmDocumentForm
                :action="update(document.id)"
                :initial="documentValues(document)"
                :options="options"
                :submit-label="$t('Save')"
                :cancel-label="$t('Cancel')"
                @cancel="router.visit(show.url(document.id))"
            />
        </section>
    </div>
</template>
