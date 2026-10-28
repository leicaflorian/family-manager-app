<script setup lang="ts">
/**
 * *Upload a document*: one or more files (PDF, JPG, PNG, HEIC, up to 25 MB
 * each) are saved and wait; each in turn gets the form of the board
 * *Proposta*, with the title taken from its name. Saving files it, discarding
 * deletes it. The × goes back to the card the upload comes from.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmFilePreview from '@/components/fm/FmFilePreview.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { DocumentFormOptions, DocumentLink } from '@/lib/documents';
import { newDocument } from '@/lib/documents';
import { listErrors } from '@/lib/errors';
import { dashboard } from '@/routes';
import { show as showCard } from '@/routes/cards';
import { store as storeDocument } from '@/routes/documents';
import { destroy, store } from '@/routes/upload';

const props = defineProps<
    {
        /** The files waiting for their document, oldest first. */
        pending: App.Data.PendingFileData[];
        /** The card the person comes from, linked from the start. */
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

/**
 * The query that keeps the card the person comes from on the next address
 * (`link=<type>:<id>`), so every file of the upload is linked to it.
 */
const linkQuery = computed(() => {
    const preset = props.presetLinks[0];

    return preset ? { query: { link: `${preset.type}:${preset.id}` } } : {};
});

/** Where the × goes: the card the person comes from, or the home page. */
const closeUrl = computed(() => {
    const preset = props.presetLinks[0];

    return preset ? showCard.url(preset) : dashboard().url;
});

/** The file whose form is open: the first one waiting. */
const current = computed(() => props.pending[0]);

const upload = useForm<{ files: File[] }>({ files: [] });

/** The messages of the files refused (`files.0`, …), all in one notice. */
const uploadErrors = computed(() => listErrors(upload.errors, 'files'));

/**
 * Sends the chosen or dropped files.
 *
 * @param files - the files given by the zone
 */
function send(files: File[]): void {
    upload.files = files;
    upload.submit(store(linkQuery.value), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => upload.reset(),
    });
}

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
                    :action="storeDocument(linkQuery)"
                    :initial="{
                        ...newDocument(current.title),
                        links: presetLinks,
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
            :processing="upload.processing"
            @files="send"
        />
    </div>
</template>
