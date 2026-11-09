<script setup lang="ts">
/**
 * *Edit a document*, as the board *Modifica documento*: the file on grey and,
 * beside it, the form in a white card, with *Delete the document* at the
 * bottom for an `admin`. *Cancel* goes back to the document. *Fill in from the
 * file* reads the file again and writes what it finds only into the empty
 * fields; while it reads the page asks for the reading every 2 seconds. It is
 * missing, with a sentence saying why, when the reading is off or the month's
 * readings are used up.
 */
import { Head, router } from '@inertiajs/vue3';
import { Sparkles, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmConfirmDelete from '@/components/fm/FmConfirmDelete.vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFilePanel from '@/components/fm/FmFilePanel.vue';
import FmReadingNotice from '@/components/fm/FmReadingNotice.vue';
import FmReadingProgress from '@/components/fm/FmReadingProgress.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useReadingPoll } from '@/composables/useReadingPoll';
import type { DocumentFormOptions } from '@/lib/documents';
import { documentValues, proposalOf } from '@/lib/documents';
import { isReading } from '@/lib/reading';
import { destroy, file, show, update } from '@/routes/documents';
import { read } from '@/routes/files';

const props = defineProps<{
    /** The document as it is. */
    document: App.Data.DocumentDetailData;
    /** The choices of the form. */
    options: DocumentFormOptions;
    /** Where the reading of the file stands, and whether it can be asked. */
    reading: App.Data.DocumentReadingData;
}>();

/** The person asked for the reading on this page: an old reading is never written into the form. */
const asked = ref(false);

/** The file is waiting in the queue or being read. */
const running = computed(() => isReading(props.reading.status));

/** What the reading found, as form values: only once it is done and the person asked for it. */
const proposal = computed(() =>
    asked.value
        ? proposalOf(props.reading.status, props.reading.proposal)
        : undefined,
);

// Only the reading is asked for, so the form the person is writing is not reloaded
useReadingPoll(() => running.value, ['reading']);

/** Asks the server to read the file again. */
function fillFromFile(): void {
    router.post(
        read.url(props.reading.file_id),
        {},
        {
            preserveScroll: true,
            // Only once the request landed: an old proposal is never written because of a request that failed
            onSuccess: () => {
                asked.value = true;
            },
        },
    );
}

/** The panel that asks before deleting. */
const confirmingDelete = ref(false);
</script>

<template>
    <Head :title="$t('Edit the document')" />

    <div class="flex max-w-[1120px] flex-col gap-6">
        <FmTitle
            :title="$t('Edit the document')"
            :back="show.url(document.id)"
            :back-label="document.title"
        />
        <div
            class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] lg:items-start"
        >
            <FmFilePanel
                :file="document.file"
                :change-url="file.url(document.id)"
            />
            <section
                class="flex flex-col gap-4 rounded-sheet bg-card p-5 lg:p-7"
            >
                <div
                    v-if="reading.can_read && !running"
                    class="flex justify-start"
                >
                    <FmButton
                        variant="outline"
                        :icon="Sparkles"
                        @click="fillFromFile"
                    >
                        {{ $t('Fill in from the file') }}
                    </FmButton>
                </div>
                <p
                    v-else-if="reading.unavailable && !running"
                    class="m-0 text-body-sm text-ink-soft"
                >
                    {{ reading.unavailable }}
                </p>
                <FmReadingProgress v-if="running" :name="document.file.name" />
                <FmReadingNotice
                    v-if="asked"
                    :status="reading.status"
                    :headline="reading.proposal?.headline"
                    :error="reading.error"
                />
                <FmDocumentForm
                    :action="update(document.id)"
                    :initial="documentValues(document)"
                    :options="options"
                    :proposal="proposal"
                    fill="empty"
                    :submit-label="$t('Save')"
                    :cancel-label="$t('Cancel')"
                    @cancel="router.visit(show.url(document.id))"
                >
                    <template v-if="document.can_delete" #start>
                        <FmButton
                            variant="danger"
                            :icon="Trash2"
                            @click="confirmingDelete = true"
                        >
                            {{ $t('Delete the document') }}
                        </FmButton>
                    </template>
                </FmDocumentForm>
            </section>
        </div>
        <FmConfirmDelete
            v-if="document.can_delete"
            v-model:open="confirmingDelete"
            :url="destroy.url(document.id)"
            :question="$t('Delete this document?')"
            :text="$t('The file goes too, and it cannot be recovered.')"
            :confirm-label="$t('Delete the document')"
        />
    </div>
</template>
