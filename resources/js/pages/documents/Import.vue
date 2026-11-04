<script setup lang="ts">
/**
 * *Import more files*: the files just uploaded, or chosen in *To sort*, checked one by one and filed as
 * documents together; one file is the same page. On the computer, as the board *Importa (pc)*: the files on top
 * as badges with their state (*Checked*, *You are looking at it*, *To check*), the file and its form side by
 * side, *Next file* in the form and the import button by the title. On the phone, as *Importa*: one file at a
 * time, the bar of the files on top and *Previous file* / *Next file* at the bottom, the import button in
 * place of *Next file* on the last file. A file can be left out (*Do not import this file*) and stays in
 * *To sort*. A file still being read makes the page ask for its state every 2 seconds, and the import waits
 * for it. What does not save stays on the page, its badge saying why; an error about the list as a whole (too
 * many files) shows on top of the page. Coming from a card (`presetLinks`), × and the import go back to it.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, FileText, X } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFilePreview from '@/components/fm/FmFilePreview.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmReadingNotice from '@/components/fm/FmReadingNotice.vue';
import FmReadingProgress from '@/components/fm/FmReadingProgress.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useReadingPoll } from '@/composables/useReadingPoll';
import type {
    DocumentFormOptions,
    DocumentFormValues,
    DocumentLink,
} from '@/lib/documents';
import {
    linkQuery,
    newDocument,
    proposalOf,
    toPayload,
} from '@/lib/documents';
import { cn } from '@/lib/utils';
import { anyFileReading, isReading, readAgain } from '@/lib/reading';
import { toSort } from '@/routes';
import { show as showCard } from '@/routes/cards';
import { store } from '@/routes/import';

const props = defineProps<
    {
        /** The files to import, in the order of the address. */
        files: App.Data.PendingFileData[];
        /** Why a file did not save the last time, by the id of the file. */
        failures: Record<number, string>;
        /** The card the files come from: × and the import go back to it. */
        presetLinks: DocumentLink[];
    } & DocumentFormOptions
>();

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

/** The computer: the badges of the files on top and the import button by the title. */
const desktop = useMediaQuery('(min-width: 1024px)');

/** The card the files come from. */
const preset = computed(() => props.presetLinks[0]);

/** Where × goes: the card the files come from, or *To sort*, where the files stay. */
const closeUrl = computed(() =>
    preset.value ? showCard.url(preset.value) : toSort().url,
);

/** Whether the open file is the last one. */
const last = computed(() => position.value === props.files.length - 1);

/** Which file is open, by its place in the list. */
const position = ref(0);

/** The files the person has already looked at, by id. */
const checked = reactive(new Set<number>());

/** The files left out, by id. */
const leftOut = reactive(new Set<number>());

/** The values of each form, as the form tells them. */
const values = reactive<Record<number, DocumentFormValues>>({});

/** The errors of the last import, by the id of the file and then the field of its document (`title`, `tags.0`…). */
const errors = ref<Record<number, Record<string, string>>>({});

/** An error of the server about the list of files as a whole (how many there are), not about one of them. */
const listError = ref<string | undefined>();

/** Whether the documents are being sent. */
const processing = ref(false);

/** The file open. */
const current = computed(() => props.files[position.value]);

/** The files that will be imported. */
const included = computed(() =>
    props.files.filter((file) => !leftOut.has(file.id)),
);

/** Whether a file that will be imported is still being read. */
const anyReading = computed(() => anyFileReading(included.value));

// Asks only while there is a file to wait for; the poll stops by itself on unmount
useReadingPoll(() => anyReading.value, ['files']);

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
 * The reason a file did not save: the first error the server found in it, or why it failed last time.
 *
 * @param file - the file
 * @returns the message, if there is one
 */
function problemOf(file: App.Data.PendingFileData): string | undefined {
    return (
        Object.values(errors.value[file.id] ?? {})[0] ?? props.failures[file.id]
    );
}

/**
 * The errors of the fields of a file's document, for its form (the one on the file itself is not a field).
 *
 * @param file - the file
 * @returns the messages by field
 */
function fieldErrorsOf(file: App.Data.PendingFileData): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors.value[file.id] ?? {}).filter(
            ([field]) => field !== 'file_id',
        ),
    );
}

/**
 * Groups the errors of the server by file. The server numbers the files as they were sent.
 *
 * @param received - the errors by name (`files.1.document.title`)
 * @param sent - the files in the order they were sent
 * @returns the messages by file id and field
 */
function errorsByFile(
    received: Record<string, string>,
    sent: App.Data.PendingFileData[],
): Record<number, Record<string, string>> {
    const byFile: Record<number, Record<string, string>> = {};

    for (const [key, message] of Object.entries(received)) {
        const found = /^files\.(\d+)\.(?:document\.)?(.+)$/.exec(key);
        const file = found ? sent[Number(found[1])] : undefined;

        if (found && file) {
            (byFile[file.id] ??= {})[found[2]!] = message;
        }
    }

    return byFile;
}

// When the files change (the page came back after a partial failure, or a file went), stay on the file open,
// or go to the first that failed, or to the first. A refresh of the readings changes neither the files nor the
// failures, so the person stays where they are
watch(
    (): [number[], string[]] => [
        props.files.map((file) => file.id),
        Object.keys(props.failures),
    ],
    ([ids, failed], [idsBefore, failedBefore]) => {
        if (
            ids.join() === idsBefore.join() &&
            failed.join() === failedBefore.join()
        ) {
            return;
        }

        const stays = ids.indexOf(idsBefore[position.value] ?? -1);
        const first = props.files.findIndex(
            (file) => problemOf(file) !== undefined,
        );

        position.value =
            stays >= 0 && failed.length === 0 ? stays : Math.max(first, 0);
    },
);

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

/**
 * The colour of a file on the bar of the phone: red when it needs a look, blue when open, green when checked.
 *
 * @param file - the file
 * @returns the classes of its stretch of the bar
 */
function barOf(file: App.Data.PendingFileData): string {
    if (problemOf(file)) {
        return cn('bg-overdue');
    }

    return {
        looking: cn('bg-action'),
        checked: cn('bg-paid'),
        'to-check': cn('bg-edge'),
        'left-out': cn('bg-edge'),
    }[stateOf(file)];
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
    listError.value = undefined;
    const sent = included.value;
    router.post(
        store(linkQuery(preset.value)).url,
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
                errors.value = errorsByFile(received, sent);
                listError.value = received.files;
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
        <template v-if="desktop">
            <FmBackLink
                v-if="!preset"
                :href="closeUrl"
                :label="$t('To sort')"
                data-discard-changes
            />
            <FmTitle
                :title="
                    $tChoice(
                        '{1} Import :count file|[2,*] Import :count files',
                        files.length,
                    )
                "
                :subtitle="
                    $tChoice(
                        '{1} Check it, then import it.|[2,*] Check them one by one, then import them together.',
                        files.length,
                    )
                "
            >
                <template #actions>
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
                    <Link
                        v-if="preset"
                        :href="closeUrl"
                        data-discard-changes
                        :aria-label="$t('Close')"
                        class="flex size-11 items-center justify-center rounded-xl border border-edge bg-white text-ink"
                    >
                        <FmIcon :icon="X" :size="20" />
                    </Link>
                </template>
            </FmTitle>
        </template>
        <div v-else-if="current" class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <Link
                    :href="closeUrl"
                    data-discard-changes
                    :aria-label="$t('Close')"
                    class="-ml-2.5 flex size-11 items-center justify-center text-ink"
                >
                    <FmIcon :icon="X" :size="24" />
                </Link>
                <h1 class="m-0 grow text-lg font-bold">
                    {{
                        $tChoice(
                            '{1} Import :count file|[2,*] Import :count files',
                            files.length,
                        )
                    }}
                </h1>
                <span class="text-sm font-semibold text-ink-soft">{{
                    $t('File :current of :total', {
                        current: String(position + 1),
                        total: String(files.length),
                    })
                }}</span>
            </div>
            <div
                class="grid gap-1.5"
                :style="{
                    gridTemplateColumns: `repeat(${files.length}, minmax(0, 1fr))`,
                }"
            >
                <span
                    v-for="file in files"
                    :key="file.id"
                    class="h-1.5 rounded-full"
                    :class="barOf(file)"
                >
                    <span v-if="problemOf(file)" class="sr-only">{{
                        $t(':name needs a look', { name: file.name })
                    }}</span>
                </span>
            </div>
        </div>

        <FmNotice v-if="listError" tone="warning">{{ listError }}</FmNotice>

        <template v-if="current">
            <ul
                v-if="desktop"
                class="m-0 flex list-none flex-wrap gap-2.5 p-0"
                :aria-label="$t('Files to import')"
            >
                <li
                    v-for="(file, index) in files"
                    :key="file.id"
                    class="min-w-0 flex-[1_1_240px]"
                >
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-3 rounded-2xl border border-edge bg-white px-3.5 py-2.5 text-left"
                        :class="{
                            'border-action': stateOf(file) === 'looking',
                            'opacity-60': stateOf(file) === 'left-out',
                        }"
                        @click="open(index)"
                    >
                        <span
                            class="shrink-0 text-sm font-extrabold text-ink-soft"
                            >{{ index + 1 }}</span
                        >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-[10px] border border-edge bg-canvas text-ink-soft"
                        >
                            <FmIcon :icon="FileText" :size="18" />
                        </span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span
                                class="truncate text-sm font-semibold"
                                :title="file.name"
                                >{{ file.name }}</span
                            >
                            <span
                                class="flex items-center gap-1 text-[13px] font-semibold"
                                :class="
                                    stateOf(file) === 'looking'
                                        ? 'text-action'
                                        : stateOf(file) === 'checked'
                                          ? 'text-paid'
                                          : 'text-ink-soft'
                                "
                            >
                                <FmIcon
                                    v-if="stateOf(file) === 'checked'"
                                    :icon="CircleCheck"
                                    :size="14"
                                />
                                <template v-if="stateOf(file) === 'looking'">{{
                                    $t('You are looking at it')
                                }}</template>
                                <template
                                    v-else-if="stateOf(file) === 'checked'"
                                    >{{ $t('Checked') }}</template
                                >
                                <template
                                    v-else-if="stateOf(file) === 'left-out'"
                                    >{{ $t('Left out') }}</template
                                >
                                <template v-else>{{ $t('To check') }}</template>
                            </span>
                            <span
                                v-if="problemOf(file)"
                                class="truncate text-[13px] text-overdue"
                                >{{ problemOf(file) }}</span
                            >
                        </span>
                    </button>
                </li>
            </ul>

            <div class="flex flex-wrap items-start gap-6">
                <div
                    class="flex min-w-0 flex-[1_1_400px] flex-col items-center gap-3.5 rounded-3xl bg-[#E9EDF3] p-5 lg:sticky lg:top-0 lg:p-8"
                >
                    <FmFilePreview
                        :url="current.url"
                        :mime-type="current.mime_type"
                        :name="current.name"
                        :preview-url="current.preview_url"
                    />
                    <span
                        class="text-center text-sm break-all text-ink-soft"
                        >{{ current.note ?? current.name }}</span
                    >
                </div>

                <section
                    class="flex min-w-0 flex-[1_1_460px] flex-col gap-3.5 rounded-3xl bg-white p-5 lg:p-7"
                >
                    <FmNotice v-if="problemOf(current)" tone="warning">
                        {{ problemOf(current) }}
                    </FmNotice>
                    <FmReadingNotice
                        :status="current.reading_status"
                        :headline="current.proposal?.headline"
                        :error="current.reading_error"
                        again
                        @again="readAgain(current.id)"
                    />
                    <FmReadingProgress
                        v-if="isReading(current.reading_status)"
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
                        :proposal="proposalOf(file.reading_status, file.proposal)"
                        :errors="fieldErrorsOf(file)"
                        @change="values[file.id] = $event"
                    />

                    <div
                        class="mt-1.5 flex flex-wrap items-center justify-between gap-2.5"
                    >
                        <label
                            class="flex cursor-pointer items-center gap-2.5 text-[15px] text-ink-soft"
                        >
                            <input
                                type="checkbox"
                                class="m-0 size-5 accent-action"
                                :checked="leftOut.has(current.id)"
                                @change="toggleLeftOut"
                            />
                            {{ $t('Do not import this file') }}
                        </label>
                        <FmButton
                            v-if="desktop && !last"
                            variant="outline"
                            @click="open(position + 1)"
                        >
                            {{ $t('Next file') }}
                        </FmButton>
                    </div>
                </section>
            </div>

            <div
                v-if="!desktop"
                class="sticky bottom-0 -mx-5 grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-2.5 border-t border-edge bg-white px-5 pt-3 pb-4"
            >
                <FmButton
                    variant="outline"
                    :disabled="position === 0"
                    @click="open(position - 1)"
                >
                    {{ $t('Previous file') }}
                </FmButton>
                <FmButton
                    v-if="last"
                    :disabled="included.length === 0 || anyReading || processing"
                    @click="save"
                >
                    {{
                        $tChoice(
                            '{1} Import the file|[2,*] Import the :count files',
                            included.length || 1,
                        )
                    }}
                </FmButton>
                <FmButton v-else @click="open(position + 1)">
                    {{ $t('Next file') }}
                </FmButton>
            </div>
        </template>
    </div>
</template>
