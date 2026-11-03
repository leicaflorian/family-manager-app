<script setup lang="ts">
/**
 * *Import more files*: the files of *To sort* that were read, checked one by one and filed as documents
 * together. On the phone one file at a time, with *Previous* and *Next*; on the computer the list of the
 * files on the left with their state (*Checked*, *You are looking at it*, *To check*), the file in the
 * middle and its form on the right, filled with what the reading found. A file can be left out
 * (*Do not import this file*) and stays in *To sort*. A file still being read makes the page ask for its
 * state every 2 seconds, and the import waits for it. What does not save stays on the page, its row saying
 * why.
 */
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFilePreview from '@/components/fm/FmFilePreview.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmReadingProgress from '@/components/fm/FmReadingProgress.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { DocumentFormOptions, DocumentFormValues } from '@/lib/documents';
import { newDocument, proposalValues, toPayload } from '@/lib/documents';
import { toSort } from '@/routes';
import { store } from '@/routes/import';

const props = defineProps<
    {
        /** The files to import, in the order of the address. */
        files: App.Data.PendingFileData[];
        /** Why a file did not save the last time, by the id of the file. */
        failures: Record<number, string>;
    } & DocumentFormOptions
>();

/** How often the page asks for the state of the files being read, in milliseconds. */
const POLL_INTERVAL = 2000;

/** The state of a file in the list. */
type FileState = 'looking' | 'checked' | 'to-check' | 'left-out';

const options = computed<DocumentFormOptions>(() => ({
    categories: props.categories,
    people: props.people,
    organizations: props.organizations,
    links: props.links,
    statuses: props.statuses,
    tags: props.tags,
}));

/** Which file is open, by its place in the list. */
const position = ref(0);

/** The files the person has already looked at, by id. */
const checked = reactive(new Set<number>());

/** The files left out, by id. */
const leftOut = reactive(new Set<number>());

/** The values of each form, as the form tells them. */
const values = reactive<Record<number, DocumentFormValues>>({});

/** The errors of the last import, by the name of the field (`files.1.document.title`). */
const errors = ref<Record<string, string>>({});

/** Whether the documents are being sent. */
const processing = ref(false);

/** The file open. */
const current = computed(() => props.files[position.value]);

/** The files that will be imported. */
const included = computed(() =>
    props.files.filter((file) => !leftOut.has(file.id)),
);

/** Whether a file is being read: waiting in the queue or running. */
const isReading = (file: App.Data.PendingFileData): boolean =>
    file.reading_status === 'queued' || file.reading_status === 'reading';

/** Whether a file that will be imported is still being read. */
const anyReading = computed(() => included.value.some(isReading));

const poll = usePoll(
    POLL_INTERVAL,
    { only: ['files'], preserveUrl: true },
    { autoStart: false },
);

// Asks only while there is a file to wait for; the poll stops by itself on unmount
watch(anyReading, (reading) => (reading ? poll.start() : poll.stop()), {
    immediate: true,
});

/**
 * The form of a file, before the reading and the person write in it.
 *
 * @param file - the file
 * @returns the values the form starts from
 */
function initialValues(file: App.Data.PendingFileData): DocumentFormValues {
    return { ...newDocument(file.title), links: file.links };
}

/**
 * What the reading found for a file, as the values of its form.
 *
 * @param file - the file
 * @returns the values, nothing before the reading is done
 */
function proposalOf(
    file: App.Data.PendingFileData,
): Partial<DocumentFormValues> | undefined {
    return file.reading_status === 'done' && file.proposal
        ? proposalValues(file.proposal)
        : undefined;
}

/**
 * The reason a file did not save: what the server answered for its fields, or why it failed last time.
 *
 * @param file - the file
 * @returns the message, if there is one
 */
function problemOf(file: App.Data.PendingFileData): string | undefined {
    const prefix = `files.${included.value.indexOf(file)}.`;
    const own = Object.entries(errors.value).find(([key]) =>
        key.startsWith(prefix),
    );

    return own ? own[1] : props.failures[file.id];
}

/**
 * Where a file stands in the list.
 *
 * @param file - the file
 * @returns its state
 */
function stateOf(file: App.Data.PendingFileData): FileState {
    if (leftOut.has(file.id)) {
        return 'left-out';
    }

    if (file.id === current.value?.id) {
        return 'looking';
    }

    return checked.has(file.id) ? 'checked' : 'to-check';
}

/**
 * Opens a file; the one left behind counts as checked.
 *
 * @param index - the place of the file in the list
 */
function open(index: number): void {
    if (current.value) {
        checked.add(current.value.id);
    }

    position.value = Math.min(Math.max(index, 0), props.files.length - 1);
}

/** Leaves the open file out of the import, or takes it back. */
function toggleLeftOut(): void {
    const file = current.value;

    if (!file) {
        return;
    }

    if (leftOut.has(file.id)) {
        leftOut.delete(file.id);
    } else {
        leftOut.add(file.id);
    }
}

/** Files the documents of the files that are not left out. */
function save(): void {
    errors.value = {};
    router.post(
        store().url,
        {
            files: included.value.map((file) => ({
                file_id: file.id,
                document: toPayload(values[file.id] ?? initialValues(file)),
            })),
        },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (received) => {
                errors.value = received;
                const first = props.files.findIndex(
                    (file) => problemOf(file) !== undefined,
                );

                if (first >= 0) {
                    open(first);
                }
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Import more files')" />

    <div class="flex flex-col gap-6">
        <FmTitle :title="$t('Import more files')">
            <template #actions>
                <Link
                    :href="toSort().url"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-xl border border-edge bg-white text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>

        <div
            v-if="current"
            class="grid items-start gap-6 lg:grid-cols-[260px_minmax(0,1fr)_minmax(0,1.2fr)]"
        >
            <ul
                class="m-0 hidden list-none flex-col gap-2 p-0 lg:flex"
                :aria-label="$t('Files to import')"
            >
                <li v-for="(file, index) in files" :key="file.id">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer flex-col gap-0.5 rounded-2xl border border-edge bg-white p-3 text-left"
                        :class="{
                            'border-action': stateOf(file) === 'looking',
                            'opacity-60': stateOf(file) === 'left-out',
                        }"
                        @click="open(index)"
                    >
                        <span class="text-[15px] font-semibold break-all">{{
                            file.name
                        }}</span>
                        <span class="text-sm text-ink-soft">
                            <template v-if="stateOf(file) === 'looking'">{{
                                $t('You are looking at it')
                            }}</template>
                            <template v-else-if="stateOf(file) === 'checked'">{{
                                $t('Checked')
                            }}</template>
                            <template
                                v-else-if="stateOf(file) === 'left-out'"
                                >{{ $t('Left out') }}</template
                            >
                            <template v-else>{{ $t('To check') }}</template>
                        </span>
                        <span
                            v-if="problemOf(file)"
                            class="text-sm text-overdue"
                            >{{ problemOf(file) }}</span
                        >
                    </button>
                </li>
            </ul>

            <div
                class="flex min-w-0 flex-col items-center gap-3.5 rounded-3xl bg-divider p-5 lg:p-8"
            >
                <div
                    class="flex w-full items-center justify-between gap-2 lg:hidden"
                >
                    <FmButton
                        variant="outline"
                        :disabled="position === 0"
                        @click="open(position - 1)"
                    >
                        {{ $t('Previous') }}
                    </FmButton>
                    <span class="text-sm font-semibold text-ink-soft">{{
                        $t(':current of :total', {
                            current: String(position + 1),
                            total: String(files.length),
                        })
                    }}</span>
                    <FmButton
                        variant="outline"
                        :disabled="position === files.length - 1"
                        @click="open(position + 1)"
                    >
                        {{ $t('Next') }}
                    </FmButton>
                </div>
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
                class="flex min-w-0 flex-col gap-3.5 rounded-3xl bg-white p-5 lg:p-7"
            >
                <FmNotice v-if="problemOf(current)" tone="warning">
                    {{ problemOf(current) }}
                </FmNotice>
                <FmNotice
                    v-if="current.reading_status === 'done' && current.proposal"
                    :title="current.proposal.headline"
                >
                    {{ $t('Check it, change what is wrong and save.') }}
                </FmNotice>
                <FmNotice
                    v-else-if="
                        current.reading_status === 'failed' ||
                        current.reading_status === 'off' ||
                        current.reading_status === 'skipped'
                    "
                    :tone="
                        current.reading_status === 'failed' ? 'warning' : 'info'
                    "
                >
                    {{ current.reading_error }}
                </FmNotice>
                <FmReadingProgress
                    v-if="isReading(current)"
                    :name="current.name"
                    :pages="current.pages"
                />
                <FmNotice v-if="leftOut.has(current.id)">
                    {{ $t('This file stays in the files to sort.') }}
                </FmNotice>

                <!-- Every form stays in the page, so what is written in one is kept while the others are open -->
                <FmDocumentForm
                    v-for="file in files"
                    v-show="file.id === current.id && !leftOut.has(file.id)"
                    :key="file.id"
                    :initial="initialValues(file)"
                    :options="options"
                    :proposal="proposalOf(file)"
                    @change="values[file.id] = $event"
                />

                <div
                    class="mt-1 flex flex-wrap items-center justify-between gap-2.5"
                >
                    <FmButton variant="text" @click="toggleLeftOut">
                        {{
                            leftOut.has(current.id)
                                ? $t('Import this file')
                                : $t('Do not import this file')
                        }}
                    </FmButton>
                    <FmButton
                        :disabled="
                            included.length === 0 || anyReading || processing
                        "
                        @click="save"
                    >
                        {{
                            $tChoice(
                                '{1} Import the file|[2,*] Import the :count files',
                                included.length || 1,
                            )
                        }}
                    </FmButton>
                </div>
            </section>
        </div>
    </div>
</template>
