<script setup lang="ts">
/**
 * *To sort*: the files waiting for their document, whoever sent them (the
 * upload, the mail, the phone), newest first, as the boards *Da smistare*,
 * *Da smistare-tablet* and *Da smistare-pc* draw it; `useLayoutMode()` decides
 * what each layout shows.
 * - Phone: the short count under the title, *Take a photo* and *Choose a file*
 *   side by side, the filters as chips, rows touched to choose them and, once a
 *   file is chosen, the bar at the bottom with the bin and *Check N files*.
 * - Tablet: the long count, *Choose the files* and *Take a photo* beside the
 *   title, the chips, and the list with its bar at the top.
 * - Computer: *Link the phone* and *Scan* (only where the scanner of the house
 *   is driven) beside the title, the box *Where they come from* beside the
 *   list, and the zone where files are dropped above it: they stay here.
 * The bar of the list chooses all the files (a copy is left out), counts the
 * chosen ones, deletes them (only who can delete many files, after asking) and
 * has *Check N files* read them, then opens the import. While a file is queued
 * or read the page asks for its state every 2 seconds.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    FolderOpen,
    Inbox,
    ScanLine,
    Smartphone,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ToSortFileRow from '@/components/documents/ToSortFileRow.vue';
import ToSortSelectionBar from '@/components/documents/ToSortSelectionBar.vue';
import ToSortSources from '@/components/documents/ToSortSources.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmChip from '@/components/fm/FmChip.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmLinkPhoneSheet from '@/components/fm/FmLinkPhoneSheet.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFileUpload } from '@/composables/useFileUpload';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { useReadingPoll } from '@/composables/useReadingPoll';
import { desktopSystem } from '@/lib/devices';
import { anyFileReading } from '@/lib/reading';
import { filterLinks } from '@/lib/toSort';
import type { FilterLink } from '@/lib/toSort';
import { scan, scanner, upload as uploadPage } from '@/routes';
import { destroy, read } from '@/routes/to-sort';

const props = defineProps<{
    /** The files of the filter chosen, newest first. */
    files: App.Data.PendingFileData[];
    /** The filter chosen, `null` for *All*. */
    source: App.Enums.FileSource | null;
    /** The filters in order, with their names and how many files each has. */
    filters: App.Data.ToSortPageData['filters'];
    /** Whether the person can delete the chosen files together (only an admin). */
    can_delete_many: boolean;
}>();

/** The layout of the window: it decides the header, the filters, the rows and the bars. */
const mode = useLayoutMode();

/** The phone layout. */
const phone = computed((): boolean => mode.value === 'phone');

/** How many files wait in all, whatever the filter: the count of *All*. */
const waiting = computed(
    (): number =>
        props.filters.find((filter) => filter.value === null)?.count ?? 0,
);

/** The filters as chips, for the tablet and the phone: a filter with no files cannot be chosen. */
const chips = computed((): FilterLink[] =>
    filterLinks(props.filters, props.source),
);

// The files dropped here stay among those to sort, instead of opening Import
const upload = useFileUpload(() => undefined, { stay: true });

/** The scanner of the house is driven from a Mac or a Windows PC, the one it is plugged into: only there is it offered. */
const scannerHere = desktopSystem() !== 'other';

/** The panel *Link the phone* is open. */
const linkPhone = ref(false);

/** The panel that asks before deleting the chosen files is open. */
const confirmingDelete = ref(false);

/** The chosen files are being deleted. */
const deleting = ref(false);

/** How many files the question to delete names, kept from when it opens so it does not change while the panel closes. */
const deletingCount = ref(0);

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

/** Asks before deleting the files chosen: they cannot be recovered. */
function deleteChosen(): void {
    deletingCount.value = chosenIds.value.length;
    confirmingDelete.value = true;
}

/** Deletes the files chosen, once the person confirmed, and stays on the same filter. */
function confirmDelete(): void {
    // A second tap on Delete, while the first one is on its way, would send it again
    if (deleting.value) {
        return;
    }

    deleting.value = true;
    router.delete(destroy.url(), {
        data: { ids: chosenIds.value },
        preserveScroll: true,
        onSuccess: () => {
            chosen.value = [];
        },
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
        },
    });
}
</script>

<template>
    <Head :title="$t('To sort')" />

    <div class="flex flex-col gap-4 lg:gap-6">
        <FmTitle
            :title="$t('To sort')"
            :subtitle="
                waiting === 0
                    ? undefined
                    : phone
                      ? $tChoice(
                            '{1} :count file waiting|[2,*] :count files waiting',
                            waiting,
                            { count: String(waiting) },
                        )
                      : $tChoice(
                            '{1} :count file waiting to be imported|[2,*] :count files waiting to be imported',
                            waiting,
                            { count: String(waiting) },
                        )
            "
        >
            <template v-if="mode === 'tablet'" #actions>
                <FmButton
                    variant="neutral"
                    :icon="FolderOpen"
                    :href="uploadPage().url"
                    >{{ $t('Choose the files') }}</FmButton
                >
                <FmButton :icon="Camera" :href="scan().url">{{
                    $t('Take a photo')
                }}</FmButton>
            </template>
            <template v-else-if="mode === 'computer'" #actions>
                <!-- Linking the phone is done from the computer: the phone itself has no use for it -->
                <FmButton
                    variant="neutral"
                    :icon="Smartphone"
                    @click="linkPhone = true"
                    >{{ $t('Link the phone') }}</FmButton
                >
                <FmButton
                    v-if="scannerHere"
                    variant="neutral"
                    :icon="ScanLine"
                    :href="scanner().url"
                    >{{ $t('Scan the sheets') }}</FmButton
                >
            </template>
        </FmTitle>

        <!-- On the phone the camera comes first, as the board Da smistare -->
        <div v-if="phone" class="grid grid-cols-2 gap-3">
            <FmButton wide :icon="Camera" :href="scan().url">{{
                $t('Take a photo')
            }}</FmButton>
            <FmButton
                wide
                variant="neutral"
                :icon="FolderOpen"
                :href="uploadPage().url"
                >{{ $t('Choose a file') }}</FmButton
            >
        </div>

        <nav
            v-if="mode !== 'computer'"
            :aria-label="$t('Files to sort')"
            class="-mr-(--main-gutter) flex gap-2 overflow-x-auto pr-(--main-gutter)"
        >
            <FmChip
                v-for="chip in chips"
                :key="chip.value ?? 'all'"
                :label="chip.label"
                :count="chip.count"
                :href="chip.href"
                :pressed="chip.current"
                :disabled="chip.disabled"
                variant="solid"
            />
        </nav>

        <div
            :class="
                mode === 'computer'
                    ? 'grid grid-cols-[290px_minmax(0,1fr)] items-start gap-7'
                    : 'flex flex-col gap-4'
            "
        >
            <ToSortSources
                v-if="mode === 'computer'"
                :filters="filters"
                :source="source"
            />

            <div class="flex min-w-0 flex-col gap-4">
                <FmFileDrop
                    v-if="mode === 'computer'"
                    compact
                    :label="$t('Drag the files here, or')"
                    :action="$t('choose from the computer')"
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

                <div v-else class="overflow-hidden rounded-card bg-card">
                    <!-- On the phone the rows are touched to choose them, and the bar is at the bottom -->
                    <div
                        v-if="!phone"
                        class="flex flex-wrap items-center gap-3 border-b border-edge-soft px-5 py-3"
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
                        <FmButton
                            v-if="can_delete_many"
                            variant="danger"
                            :icon="Trash2"
                            :disabled="chosenIds.length === 0 || deleting"
                            @click="deleteChosen"
                            >{{ $t('Delete the chosen') }}</FmButton
                        >
                        <FmButton
                            :icon="Sparkles"
                            :disabled="chosenIds.length === 0 || deleting"
                            @click="readChosen"
                        >
                            {{
                                $tChoice(
                                    '{0} Check files|{1} Check :count file|[2,*] Check :count files',
                                    chosenIds.length,
                                    { count: String(chosenIds.length) },
                                )
                            }}
                        </FmButton>
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
                            :touch="phone"
                            @choose="choose(file.id, $event)"
                        />
                    </ul>
                </div>
            </div>
        </div>

        <!--
            In the flow after the list, so it takes its own room and never covers the last row;
            held 84 px from the bottom, just above the navigation bar of the phone
        -->
        <div
            v-if="phone && chosenIds.length > 0"
            class="sticky bottom-21 z-20 -mx-(--main-gutter)"
        >
            <ToSortSelectionBar
                :count="chosenIds.length"
                :can-delete="can_delete_many"
                :processing="deleting"
                @check="readChosen"
                @delete="deleteChosen"
            />
        </div>

        <FmConfirm
            v-model:open="confirmingDelete"
            :question="
                $tChoice(
                    '{1} Delete the chosen file?|[2,*] Delete :count files?',
                    deletingCount,
                    { count: String(deletingCount) },
                )
            "
            :text="$t('Deleted files cannot be recovered.')"
            :confirm-label="$t('Delete')"
            @confirm="confirmDelete"
        />

        <FmLinkPhoneSheet v-if="mode === 'computer'" v-model:open="linkPhone" />
    </div>
</template>
