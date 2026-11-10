<script setup lang="ts">
/**
 * *To sort*: the files waiting for their document, whoever sent them (the
 * upload, the mail, the phone), newest first, as the board *Da smistare-pc*
 * draws it. Under the title, how many files wait. On the computer the filters
 * (*All*, *Arrived by mail*, *Uploaded from the phone*, *Uploaded manually*) are in
 * the box *Where they come from* beside the list; on the tablet and the phone
 * they are tabs above it. Files dropped in the zone above the list stay here.
 * The bar at the top of the list chooses all the files (a copy is left out),
 * counts the ones chosen and has *Read and review* read them, then opens the
 * import, where the person checks and changes what was read before importing.
 * The link to *Received mail* is an icon beside the title on the phone and the tablet, and a row under the
 * filters on the computer. While a file is queued or read the page asks for its state every 2 seconds.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    Inbox,
    Mail,
    ScanLine,
    Smartphone,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ToSortFileRow from '@/components/documents/ToSortFileRow.vue';
import ToSortSources from '@/components/documents/ToSortSources.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmLinkPhoneSheet from '@/components/fm/FmLinkPhoneSheet.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { Tab } from '@/components/fm/FmTabs.vue';
import FmTabs from '@/components/fm/FmTabs.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmTooltip from '@/components/fm/FmTooltip.vue';
import { useFileUpload } from '@/composables/useFileUpload';
import { useReadingPoll } from '@/composables/useReadingPoll';
import { desktopSystem, isPhone } from '@/lib/devices';
import { anyFileReading } from '@/lib/reading';
import { scan, scanner, toSort } from '@/routes';
import { index as receivedMail } from '@/routes/mail';
import { read } from '@/routes/to-sort';

const props = defineProps<{
    /** The files of the filter chosen, newest first. */
    files: App.Data.PendingFileData[];
    /** The filter chosen, `null` for *All*. */
    source: App.Enums.FileSource | null;
    /** The filters in order, with their names and how many files each has. */
    filters: App.Data.ToSortPageData['filters'];
}>();

/** How many files wait in all, whatever the filter: the count of *All*. */
const waiting = computed(
    (): number =>
        props.filters.find((filter) => filter.value === null)?.count ?? 0,
);

/** The filters as tabs, for the tablet and the phone: a filter with no files cannot be chosen. */
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

// The files dropped here stay among those to sort, instead of opening Import
const upload = useFileUpload(() => undefined, { stay: true });

/** On the phone the camera comes first: *Take a photo* opens the scan, as the board *Da smistare*. */
const phone = isPhone();

/** The scanner of the house is driven from a Mac or a Windows PC, the one it is plugged into: only there is it offered. */
const scannerHere = desktopSystem() !== 'other';

/** The panel *Link the phone* is open. */
const linkPhone = ref(false);

/** The ids of the files chosen. */
const chosen = ref<number[]>([]);

/** The files that can be chosen: a copy is left out. */
const selectable = computed((): App.Data.PendingFileData[] =>
    props.files.filter((file) => file.copy_of === null),
);

/** The files chosen that are still in the list, so a filter or a deletion drops the ones that went away. */
const chosenIds = computed<number[]>(() =>
    selectable.value
        .filter((file) => chosen.value.includes(file.id))
        .map((file) => file.id),
);

/** Every file that can be chosen is chosen. */
const allChosen = computed(
    (): boolean =>
        selectable.value.length > 0 &&
        chosenIds.value.length === selectable.value.length,
);

/** Whether any file waiting is being read, which keeps the page asking. */
const anyReading = computed(() => anyFileReading(props.files));

// Asks only while there is a file to wait for; the poll stops by itself on unmount
useReadingPoll(() => anyReading.value, ['files', 'filters']);

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

/** Chooses every file that can be chosen, or lets them all go when they already are. */
function chooseAll(): void {
    chosen.value = allChosen.value
        ? []
        : selectable.value.map((file) => file.id);
}

/** Has the files chosen read, then opens the import. */
function readChosen(): void {
    router.post(read.url(), { ids: chosenIds.value });
}
</script>

<template>
    <Head :title="$t('To sort')" />

    <div class="flex flex-col gap-4 lg:gap-6">
        <FmTitle
            :title="$t('To sort')"
            :subtitle="
                waiting > 0
                    ? $tChoice(
                          '{1} :count file waiting to be imported|[2,*] :count files waiting to be imported',
                          waiting,
                          { count: String(waiting) },
                      )
                    : undefined
            "
        >
            <!-- On the computer the same link is the row under the filters -->
            <template #actions>
                <!-- Linking the phone is done from the computer: the phone itself has no use for it -->
                <FmButton
                    class="hidden lg:flex"
                    variant="outline"
                    :icon="Smartphone"
                    @click="linkPhone = true"
                    >{{ $t('Link the phone') }}</FmButton
                >
                <FmButton
                    v-if="scannerHere"
                    variant="outline"
                    :icon="ScanLine"
                    :href="scanner().url"
                    >{{ $t('Scan the sheets') }}</FmButton
                >
                <FmButton
                    class="lg:hidden"
                    :icon="Mail"
                    :label="$t('Received mail')"
                    :href="receivedMail().url"
                />
            </template>
        </FmTitle>

        <FmTabs
            class="lg:hidden"
            :tabs="tabs"
            :label="$t('Files to sort')"
            variant="pill"
        />

        <div
            class="flex flex-col gap-4 lg:grid lg:grid-cols-[290px_minmax(0,1fr)] lg:items-start lg:gap-7"
        >
            <ToSortSources
                class="hidden lg:flex"
                :filters="filters"
                :source="source"
            />

            <div class="flex min-w-0 flex-col gap-4">
                <FmButton v-if="phone" wide :icon="Camera" :href="scan().url">{{
                    $t('Take a photo')
                }}</FmButton>
                <FmFileDrop
                    compact
                    :label="
                        $t(
                            'Drag the files here, or choose them from the computer',
                        )
                    "
                    :processing="upload.processing.value"
                    @files="upload.send"
                />
                <FmNotice v-if="upload.errors.value.length > 0" tone="danger">
                    {{ upload.errors.value.join(' ') }}
                </FmNotice>

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

                <div v-else class="overflow-hidden rounded-card-lg bg-card">
                    <div
                        class="flex flex-wrap items-center gap-3 border-b border-divider px-4 py-3"
                    >
                        <input
                            type="checkbox"
                            :aria-label="$t('Choose all')"
                            :checked="allChosen"
                            :disabled="selectable.length === 0"
                            class="size-5 shrink-0 accent-action"
                            @change="chooseAll"
                        />
                        <span class="grow text-row">{{
                            $tChoice(
                                '{0} :count files chosen of :total|{1} :count file chosen of :total|[2,*] :count files chosen of :total',
                                chosenIds.length,
                                {
                                    count: String(chosenIds.length),
                                    total: String(files.length),
                                },
                            )
                        }}</span>
                        <FmTooltip
                            :text="
                                $t(
                                    'Nothing is saved yet: you see what was read, and you can read it again or change it before importing.',
                                )
                            "
                        >
                            <FmButton
                                :icon="Sparkles"
                                :disabled="chosenIds.length === 0"
                                @click="readChosen"
                            >
                                {{
                                    $tChoice(
                                        '{0} Read and review files|{1} Read and review :count file|[2,*] Read and review :count files',
                                        chosenIds.length,
                                        { count: String(chosenIds.length) },
                                    )
                                }}
                            </FmButton>
                        </FmTooltip>
                    </div>
                    <ul
                        v-auto-animate
                        class="m-0 flex list-none flex-col divide-y divide-divider p-0"
                    >
                        <ToSortFileRow
                            v-for="file in files"
                            :key="file.id"
                            :file="file"
                            :chosen="chosenIds.includes(file.id)"
                            @choose="choose(file.id, $event)"
                        />
                    </ul>
                </div>
            </div>
        </div>

        <FmLinkPhoneSheet v-model:open="linkPhone" />
    </div>
</template>
