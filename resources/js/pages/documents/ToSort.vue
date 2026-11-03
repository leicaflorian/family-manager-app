<script setup lang="ts">
/**
 * *To sort*: the files waiting for their document, whoever sent them (the
 * upload, the mail, the phone), newest first. Each row has the name, the day,
 * the size or the pages, where it came from, how its reading stands, the note
 * of how it arrived and, for a copy of an older file, a line that says so (and
 * on the computer which file it is the same as). The filters at the top (*All*,
 * *By mail*, *From the phone*, *Uploaded here*) show how many files each has,
 * and one with none cannot be chosen. The files are chosen with a box (a copy
 * is left out) and *Read and import* has them read, then opens the import. While
 * a file is queued or read the page asks for its state every 2 seconds.
 */
import { Head, router, usePoll } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmRow from '@/components/fm/FmRow.vue';
import type { Tab } from '@/components/fm/FmTabs.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { formatSize } from '@/lib/format';
import { toSort } from '@/routes';
import { read } from '@/routes/to-sort';
import { destroy } from '@/routes/upload';

const props = defineProps<{
    /** The files of the filter chosen, newest first. */
    files: App.Data.PendingFileData[];
    /** The filter chosen, `null` for *All*. */
    source: App.Enums.FileSource | null;
    /** The filters in order, with their names and how many files each has. */
    filters: App.Data.ToSortPageData['filters'];
}>();

const { date } = useFormat();

/** How often the page asks for the state of the files being read, in milliseconds. */
const POLL_INTERVAL = 2000;

/** The filters as tabs: a filter with no files cannot be chosen. */
const tabs = computed<Tab[]>(() =>
    props.filters.map((filter) => ({
        label: filter.label,
        href:
            filter.value === null
                ? toSort().url
                : toSort({ query: { source: filter.value } }).url,
        count: filter.count,
        current: props.source === filter.value,
        disabled: filter.count === 0 && filter.value !== null,
    })),
);

/** The ids of the files chosen. */
const chosen = ref<number[]>([]);

/** Whether a file can be chosen: a copy is left out. */
const selectable = (file: App.Data.PendingFileData): boolean =>
    file.copy_of === null;

/** The files chosen that are still in the list, so a filter or a deletion drops the ones that went away. */
const chosenIds = computed<number[]>(() =>
    props.files
        .filter((file) => selectable(file) && chosen.value.includes(file.id))
        .map((file) => file.id),
);

/** Whether any file waiting is being read, which keeps the page asking. */
const anyReading = computed(() =>
    props.files.some(
        (file) =>
            file.reading_status === 'queued' ||
            file.reading_status === 'reading',
    ),
);

const poll = usePoll(
    POLL_INTERVAL,
    { only: ['files', 'filters'], preserveUrl: true },
    { autoStart: false },
);

// Asks only while there is a file to wait for; the poll stops by itself on unmount
watch(anyReading, (reading) => (reading ? poll.start() : poll.stop()), {
    immediate: true,
});

/**
 * Chooses a file or lets it go.
 *
 * @param id - the id of the file
 * @param on - whether the box is ticked
 */
function choose(id: number, on: boolean): void {
    chosen.value = on
        ? [...chosen.value, id]
        : chosen.value.filter((chosenId) => chosenId !== id);
}

/**
 * How big the file is, or how many pages it has when that is known.
 *
 * @param file - the file
 * @returns the pages or the size, as text
 */
function measure(file: App.Data.PendingFileData): string {
    return file.pages === null
        ? formatSize(file.size)
        : transChoice('{1} :count page|[2,*] :count pages', file.pages, {
              count: String(file.pages),
          });
}

/**
 * The second line of a row: the day, the size or pages and where it came from.
 *
 * @param file - the file
 */
function subtitle(file: App.Data.PendingFileData): string {
    return [date(file.created_on), measure(file), file.source_label].join(
        ' · ',
    );
}

/**
 * How the reading of a file stands, in words.
 *
 * @param file - the file
 * @returns the text, empty when the file was never queued
 */
function readingText(file: App.Data.PendingFileData): string {
    switch (file.reading_status) {
        case 'queued':
            return trans('Waiting to be read');
        case 'reading':
            return trans('Reading the document…');
        case 'done':
            return trans('Read');
        case 'failed':
        case 'off':
        case 'skipped':
            return file.reading_error ?? '';
        default:
            return '';
    }
}

/** Has the files chosen read, then opens the import. */
function readChosen(): void {
    router.post(read.url(), { ids: chosenIds.value });
}
</script>

<template>
    <Head :title="$t('To sort')" />

    <div class="flex flex-col gap-4">
        <FmTitle :title="$t('To sort')" />

        <FmTabs :tabs="tabs" :label="$t('Files to sort')" variant="pill" />

        <FmEmpty
            v-if="files.length === 0"
            :icon="Inbox"
            :title="$t('Nothing to sort')"
            :text="
                $t(
                    'The files you upload, or that arrive by mail or from the phone, wait here until they become a document.',
                )
            "
        />

        <template v-else>
            <ul
                v-auto-animate
                class="m-0 flex list-none flex-col divide-y divide-divider rounded-[20px] bg-white p-0"
            >
                <li v-for="file in files" :key="file.id" class="py-1">
                    <div class="flex items-center gap-1 pr-2 pl-4">
                        <input
                            type="checkbox"
                            :aria-label="file.name"
                            :checked="chosenIds.includes(file.id)"
                            :disabled="!selectable(file)"
                            class="size-5 shrink-0 accent-action"
                            @change="
                                choose(
                                    file.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <FmRow
                            :title="file.name"
                            :subtitle="subtitle(file)"
                            :href="file.url"
                            external
                            :arrow="false"
                        />
                        <FmDeleteButton
                            v-if="file.can_delete"
                            compact
                            :label="
                                file.copy_of !== null
                                    ? $t('Delete the copy')
                                    : $t('Delete')
                            "
                            :url="destroy.url(file.id)"
                            :question="
                                file.copy_of !== null
                                    ? $t('Delete this copy?')
                                    : $t('Delete this file?')
                            "
                            :text="$t('The file is deleted for good.')"
                            :confirm-label="
                                file.copy_of !== null
                                    ? $t('Delete the copy')
                                    : $t('Delete the file')
                            "
                        />
                    </div>
                    <div
                        class="flex flex-col gap-0.5 pb-2 pl-[3.25rem] text-[13px] text-ink-soft"
                    >
                        <span v-if="readingText(file)">{{
                            readingText(file)
                        }}</span>
                        <span v-if="file.note">{{ file.note }}</span>
                        <template v-if="file.copy_of !== null">
                            <span
                                class="font-semibold text-warning lg:hidden"
                                >{{
                                    $t('It is a copy: you can delete it')
                                }}</span
                            >
                            <span
                                class="hidden font-semibold text-warning lg:inline"
                                >{{
                                    $t('Same as :name', { name: file.copy_of })
                                }}</span
                            >
                        </template>
                    </div>
                </li>
            </ul>

            <FmButton :disabled="chosenIds.length === 0" @click="readChosen">
                {{
                    $tChoice(
                        '{0} Read and import files|{1} Read and import :count file|[2,*] Read and import :count files',
                        chosenIds.length,
                        { count: String(chosenIds.length) },
                    )
                }}
            </FmButton>
        </template>
    </div>
</template>
