<script setup lang="ts">
/**
 * *Upload a document*: one or more files (PDF, JPG, PNG, HEIC, up to 25 MB
 * each) are saved and wait; each in turn gets the form of the board
 * *Proposta*, with the title taken from its name. Saving files it, discarding
 * deletes it.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FolderOpen, Upload as UploadIcon, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFilePreview from '@/components/fm/FmFilePreview.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { DocumentFormOptions } from '@/lib/documents';
import { newDocument } from '@/lib/documents';
import { listErrors } from '@/lib/errors';
import { DOCUMENT_FILE_TYPES } from '@/lib/files';
import { dashboard } from '@/routes';
import { store as storeDocument } from '@/routes/documents';
import { destroy, store } from '@/routes/upload';

const props = defineProps<
    {
        /** The files waiting for their document, oldest first. */
        pending: App.Data.PendingFileData[];
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

/** The file whose form is open: the first one waiting. */
const current = computed(() => props.pending[0]);

const upload = useForm<{ files: File[] }>({ files: [] });

/** The messages of the files refused (`files.0`, …), all in one notice. */
const uploadErrors = computed(() => listErrors(upload.errors, 'files'));

/** A file is being dragged over the zone. */
const dragging = ref(false);

/**
 * Sends the chosen or dropped files.
 *
 * @param files - the files, from the picker or from the drop
 */
function send(files: FileList | null | undefined): void {
    if (!files || files.length === 0) {
        return;
    }

    upload.files = Array.from(files);
    upload.submit(store(), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => upload.reset(),
    });
}

/**
 * Sends the files of the picker.
 *
 * @param event - the change of the input
 */
function chosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    send(input.files);
    input.value = '';
}

/**
 * Sends the dropped files.
 *
 * @param event - the drop on the zone
 */
function dropped(event: DragEvent): void {
    dragging.value = false;
    send(event.dataTransfer?.files);
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
                    :href="dashboard().url"
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
                    :action="storeDocument()"
                    :initial="newDocument(current.title)"
                    :extra="{ file_id: current.id }"
                    :options="options"
                    :submit-label="$t('Save')"
                    :cancel-label="$t('Discard this file')"
                    @cancel="discard(current)"
                />
            </section>
        </div>

        <label
            for="upload-files"
            class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-[20px] border-2 border-dashed bg-white text-center text-ink-soft transition-colors"
            :class="[
                dragging ? 'border-action bg-action-soft' : 'border-edge',
                current ? 'p-5' : 'min-h-[320px] p-8',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="dropped"
        >
            <FmIcon
                :icon="current ? FolderOpen : UploadIcon"
                :size="current ? 22 : 40"
                class="text-action"
            />
            <span class="text-base font-semibold text-ink">
                {{
                    current
                        ? $t('Other files? Drag them here, or choose them')
                        : $t('Drag the files here, or choose them')
                }}
            </span>
            <span class="text-sm">{{
                $t('PDF, JPG, PNG or HEIC, up to 25 MB each')
            }}</span>
            <span
                v-if="upload.processing"
                class="text-sm font-semibold text-action"
                >{{ $t('Uploading…') }}</span
            >
            <input
                id="upload-files"
                type="file"
                multiple
                :accept="DOCUMENT_FILE_TYPES"
                :aria-label="$t('Choose the files')"
                class="sr-only"
                @change="chosen"
            />
        </label>
    </div>
</template>
