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
import { Head, router, usePoll } from '@inertiajs/vue3';
import { Sparkles, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmConfirmDelete from '@/components/fm/FmConfirmDelete.vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFilePanel from '@/components/fm/FmFilePanel.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmReadingProgress from '@/components/fm/FmReadingProgress.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { DocumentFormOptions } from '@/lib/documents';
import { documentValues, proposalValues } from '@/lib/documents';
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

/** How often the page asks for the reading while it runs, in milliseconds. */
const POLL_INTERVAL = 2000;

/** The person asked for the reading on this page: an old reading is never written into the form. */
const asked = ref(false);

/** The file is waiting in the queue or being read. */
const isReading = computed(
    () =>
        props.reading.status === 'queued' || props.reading.status === 'reading',
);

/** What the reading found, as form values: only once it is done and the person asked for it. */
const proposal = computed(() =>
    asked.value && props.reading.status === 'done' && props.reading.proposal
        ? proposalValues(props.reading.proposal)
        : undefined,
);

/** Why the reading did not give a proposal, after the person asked. */
const notRead = computed(() =>
    asked.value &&
    (props.reading.status === 'failed' ||
        props.reading.status === 'off' ||
        props.reading.status === 'skipped')
        ? props.reading.error
        : null,
);

// Only the reading is asked for, so the form the person is writing is not reloaded
const poll = usePoll(
    POLL_INTERVAL,
    { only: ['reading'], preserveUrl: true },
    { autoStart: false },
);

watch(isReading, (running) => (running ? poll.start() : poll.stop()), {
    immediate: true,
});

/** Asks the server to read the file again. */
function fillFromFile(): void {
    asked.value = true;
    router.post(read.url(props.reading.file_id), {}, { preserveScroll: true });
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
                class="flex flex-col gap-4 rounded-3xl bg-white p-5 lg:p-7"
            >
                <div
                    v-if="reading.can_read && !isReading"
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
                    v-else-if="reading.unavailable"
                    class="m-0 text-sm text-ink-soft"
                >
                    {{ reading.unavailable }}
                </p>
                <FmReadingProgress
                    v-if="isReading"
                    :name="document.file.name"
                />
                <FmNotice
                    v-if="proposal && reading.proposal"
                    :title="reading.proposal.headline"
                >
                    {{ $t('Check it, change what is wrong and save.') }}
                </FmNotice>
                <FmNotice v-if="notRead" tone="warning">
                    {{ notRead }}
                </FmNotice>
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
