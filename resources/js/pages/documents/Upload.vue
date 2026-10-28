<script setup lang="ts">
/**
 * *Upload a document*: one or more files (PDF, JPG, PNG, HEIC, up to 25 MB
 * each) are saved and wait; each in turn gets the form of the board
 * *Proposta*, with the title taken from its name and the card it was uploaded
 * from among the links. Saving files it, discarding deletes it. The × goes
 * back to the card the upload comes from.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmFilePreview from '@/components/fm/FmFilePreview.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFileUpload } from '@/composables/useFileUpload';
import type { DocumentFormOptions, DocumentLink } from '@/lib/documents';
import { linkQuery, newDocument } from '@/lib/documents';
import { dashboard } from '@/routes';
import { show as showCard } from '@/routes/cards';
import { store as storeDocument } from '@/routes/documents';
import { destroy } from '@/routes/upload';

const props = defineProps<
    {
        /** The files waiting for their document, oldest first. */
        pending: App.Data.PendingFileData[];
        /** The card the person comes from, kept for the next files and the ×. */
        presetLinks: DocumentLink[];
    } & DocumentFormOptions
>();

const options = computed<DocumentFormOptions>(() => ({
    categories: props.categories,
    people: props.people,
    organizations: props.organizations,
    links: props.links,
    statuses: props.statuses,
    tags: props.tags,
}));

/** The card the person comes from, kept for the next files and the ×. */
const preset = computed(() => props.presetLinks[0]);

/** Where the × goes: the card the person comes from, or the home page. */
const closeUrl = computed(() =>
    preset.value ? showCard.url(preset.value) : dashboard().url,
);

/** The file whose form is open: the first one waiting. */
const current = computed(() => props.pending[0]);

const {
    send,
    processing,
    errors: uploadErrors,
} = useFileUpload(() => preset.value);

/**
 * Discards a file waiting: it is deleted.
 *
 * @param file - the file
 */
function discard(file: App.Data.PendingFileData): void {
    router.delete(destroy.url(file.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Upload a document')" />

    <div class="flex flex-col gap-6">
        <FmTitle :title="$t('Upload a document')">
            <template #actions>
                <Link
                    :href="closeUrl"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-xl border border-edge bg-white text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>

        <FmNotice v-if="uploadErrors.length > 0" tone="warning">
            <p v-for="message in uploadErrors" :key="message" class="m-0">
                {{ message }}
            </p>
        </FmNotice>

        <div v-if="current" class="flex flex-wrap items-start gap-6">
            <div
                class="hidden min-w-0 flex-[1_1_380px] flex-col items-center gap-3.5 rounded-3xl bg-divider p-8 lg:flex"
            >
                <FmFilePreview
                    :url="current.url"
                    :mime-type="current.mime_type"
                    :name="current.name"
                    :preview-url="current.preview_url"
                />
                <span class="text-sm break-all text-ink-soft">{{
                    current.name
                }}</span>
            </div>
            <section
                class="flex min-w-0 flex-[1_1_420px] flex-col gap-3.5 rounded-3xl bg-white p-5 lg:p-7"
            >
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold text-ink-soft">
                        {{
                            $t(':current of :total', {
                                current: '1',
                                total: String(pending.length),
                            })
                        }}
                    </span>
                    <h2 class="m-0 text-xl font-extrabold break-all">
                        {{ current.name }}
                    </h2>
                    <p class="m-0 text-[15px] text-ink-soft">
                        {{
                            $t(
                                'Fill in what you know: you can change it later.',
                            )
                        }}
                    </p>
                </div>
                <FmDocumentForm
                    :key="current.id"
                    :action="storeDocument(linkQuery(preset))"
                    :initial="{
                        ...newDocument(current.title),
                        links: current.links,
                    }"
                    :extra="{ file_id: current.id }"
                    :options="options"
                    :submit-label="$t('Save')"
                    :cancel-label="$t('Discard this file')"
                    @cancel="discard(current)"
                />
            </section>
        </div>

        <FmFileDrop
            :label="
                current
                    ? $t('Other files? Drag them here, or choose them')
                    : undefined
            "
            :compact="current !== undefined"
            :processing="processing"
            @files="send"
        />
    </div>
</template>
