<script setup lang="ts">
/**
 * *Upload a document*: one or more files (PDF, JPG, PNG, HEIC, up to 25 MB
 * each) are saved and wait; each in turn gets the form of the board
 * *Proposta*, with the title taken from its name and the card it was uploaded
 * from among the links. The file is read by itself: while it is read the page
 * asks for its state every 2 seconds and shows the wait; when it is done the
 * sentence of what was found and the form filled with it (only the fields the
 * person has not touched); when it fails or is off, the reason and the form
 * empty. Saving files the document, cancelling leaves the file in *To sort*.
 * The × goes back to the card the upload comes from.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmFilePreview from '@/components/fm/FmFilePreview.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmReadingNotice from '@/components/fm/FmReadingNotice.vue';
import FmReadingProgress from '@/components/fm/FmReadingProgress.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmToSortLink from '@/components/fm/FmToSortLink.vue';
import { useFileUpload } from '@/composables/useFileUpload';
import { useReadingPoll } from '@/composables/useReadingPoll';
import { useToSortCount } from '@/composables/useToSortCount';
import type { DocumentFormOptions, DocumentLink } from '@/lib/documents';
import { linkQuery, newDocument, proposalValues } from '@/lib/documents';
import { isReading } from '@/lib/reading';
import { dashboard, toSort } from '@/routes';
import { show as showCard } from '@/routes/cards';
import { store as storeDocument } from '@/routes/documents';
import { read } from '@/routes/files';

const props = defineProps<
    {
        /** The files waiting for their document, oldest first. */
        pending: App.Data.PendingFileData[];
        /** The card the person comes from, kept for the next files and the ×. */
        presetLinks: DocumentLink[];
    } & DocumentFormOptions
>();

/** The files waiting in *To sort*, for the link beside the title. */
const toSortCount = useToSortCount();

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

/** Whether any file waiting is being read, which keeps the page asking. */
const anyReading = computed(() =>
    props.pending.some((file) => isReading(file.reading_status)),
);

/** The file in front is being read: the wait takes the place of the form. */
const waiting = computed(
    () =>
        current.value !== undefined && isReading(current.value.reading_status),
);

/** What the reading found, as the values of the form; nothing before it is done. */
const proposal = computed(() =>
    current.value?.reading_status === 'done' && current.value.proposal
        ? proposalValues(current.value.proposal)
        : undefined,
);

// Asks only while there is a file to wait for; the poll stops by itself on unmount
useReadingPoll(() => anyReading.value, ['pending']);

/**
 * Reads the file again after it failed.
 *
 * @param file - the file
 */
function readAgain(file: App.Data.PendingFileData): void {
    router.post(read.url(file.id), {}, { preserveScroll: true });
}

/** Leaves the file where it is, in *To sort*. */
function cancel(): void {
    router.visit(toSort().url);
}
</script>

<template>
    <Head :title="$t('Upload a document')" />

    <div class="flex flex-col gap-6">
        <FmTitle :title="$t('Upload a document')">
            <template #actions>
                <FmToSortLink :count="toSortCount" />
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

        <FmReadingNotice
            v-if="current"
            :status="current.reading_status"
            :headline="current.proposal?.headline"
            :error="current.reading_error"
        >
            <template v-if="current.reading_status === 'failed'" #action>
                <button
                    type="button"
                    class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-inherit underline"
                    @click="readAgain(current)"
                >
                    {{ $t('Read again') }}
                </button>
            </template>
        </FmReadingNotice>

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
                <FmReadingProgress
                    v-if="waiting"
                    :name="current.name"
                    :pages="current.pages"
                />
                <!-- Kept while the file is read, so what the person writes stays -->
                <FmDocumentForm
                    v-show="!waiting"
                    :key="current.id"
                    :action="storeDocument(linkQuery(preset))"
                    :initial="{
                        ...newDocument(current.title),
                        links: current.links,
                    }"
                    :extra="{ file_id: current.id }"
                    :options="options"
                    :proposal="proposal"
                    :submit-label="proposal ? $t('Okay, save') : $t('Save')"
                    :cancel-label="$t('Cancel')"
                    @cancel="cancel"
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
