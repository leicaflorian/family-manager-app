<script setup lang="ts">
/**
 * *Scan*: the pages of a document photographed with the phone (or the webcam of a computer) and uploaded to *To
 * sort*, as the boards `Scansione` and `Scansione-pc` draw it, without the bar of the sections. Each photo shows at
 * once with *Preparing the photo…*, is brought to 2000 px, and a 1000 px copy goes to the server for the corners
 * of the sheet: when they come back the photo shows cut out, with *Cropped*. *Crop* opens the photos one after the
 * other («1 of 2»). *Upload* asks first, sends the files with the progress and empties the page for the next
 * document; a session that has ended is entered again with the linked phone without losing the pages. Pages not
 * uploaded yet are changes not saved: the app asks before leaving them. A linked phone, which sees only this page,
 * can sign out to *Sign in with the password*.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Camera,
    Crop as CropIcon,
    FileText,
    FolderOpen,
    Images,
    Trash2,
} from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import FmCropStage from '@/components/fm/FmCropStage.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import type { StripPage } from '@/components/fm/FmPageStrip.vue';
import FmPageStrip from '@/components/fm/FmPageStrip.vue';
import type { CropResult } from '@/components/scan/ScanCrop.vue';
import ScanCrop from '@/components/scan/ScanCrop.vue';
import ScanStatus from '@/components/scan/ScanStatus.vue';
import ScanWebcam from '@/components/scan/ScanWebcam.vue';
import { useScanUpload } from '@/composables/useScanUpload';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { useWebcam } from '@/composables/useWebcam';
import { useWideLayout } from '@/composables/useWideLayout';
import { isPhone } from '@/lib/devices';
import { SEARCH_LONG_SIDE, cutOut, reduceToJpeg } from '@/lib/scan/canvas';
import type { Corners, Turns } from '@/lib/scan/crop';
import { LONG_SIDE, WHOLE } from '@/lib/scan/crop';
import { IMPROVE_DEFAULTS } from '@/lib/scan/improve';
import type { ScanDocument, ScanPage } from '@/lib/scan/pages';
import {
    addPages,
    movePage,
    oneSheetState,
    pageCount,
    removePage,
    selectPage,
    sendSummary,
} from '@/lib/scan/pages';
import { ScanRequestError, findCorners } from '@/lib/scan/server';
import { cn } from '@/lib/utils';
import { logout, toSort } from '@/routes';

/** The quality of the photo the page keeps (2000 px): the one of the old *Family Scan*. */
const PHOTO_QUALITY = 0.85;
/** The quality of the small copy sent for the corners. */
const SEARCH_QUALITY = 0.8;

/** A page of the scan with what the page needs to show it. */
type Page = ScanPage & {
    /** The photo brought to 2000 px, cropped and sent for the corners; null while it is prepared. */
    photo: Blob | null;
    /** The address of what is shown before the sheet is cut out: the file of the phone, then the reduced photo. */
    photoUrl: string | null;
    /** The addresses of the sheet cut out, finished and before the improvements. */
    sheetUrl: string | null;
    plainUrl: string | null;
    /** The photo is being reduced. */
    preparing: boolean;
};

const page = usePage();
const wide = useWideLayout();
const phone = isPhone();
const webcam = useWebcam();
/** A phone linked with a code: it sees only this page. */
const linked = computed((): boolean => page.props.linked_phone === true);

const doc = ref<ScanDocument<Page>>({ pages: [], chosenId: null });
const note = ref('');
const oneSheet = ref(false);
/** Why some files were not taken, or why the corners are set by hand today. */
const notice = ref<string | null>(null);
/** The original is shown while the cut out sheet is held. */
const peeking = ref(false);
/** *What it is* is being written on the phone: the bar at the bottom is put away from the keyboard. */
const typing = ref(false);
const discardAsking = ref(false);
const webcamOpen = ref(false);
/** *Crop*: the photos it goes through, the one open, and the cutting under way. */
const crop = ref<{
    ids: string[];
    index: number;
    busy: boolean;
    problem: string | null;
} | null>(null);

const filesInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);

const pages = computed((): Page[] => doc.value.pages);
const chosen = computed(
    (): Page | null =>
        pages.value.find((one) => one.id === doc.value.chosenId) ?? null,
);
const empty = computed((): boolean => pages.value.length === 0);
const count = computed((): string => pageCount(pages.value));
const summary = computed((): string =>
    sendSummary(pages.value, oneSheet.value),
);
const sheetState = computed(() => oneSheetState(pages.value));
const preparing = computed((): boolean =>
    pages.value.some((one) => one.preparing),
);
const stripPages = computed((): StripPage[] =>
    pages.value.map((one) => ({
        id: one.id,
        url: one.kind === 'photo' ? (one.sheetUrl ?? one.photoUrl) : null,
    })),
);
/** The uncut photos *On a single page* is waiting for. */
const uncut = computed(
    (): number =>
        pages.value.filter((one) => one.kind === 'photo' && !one.cut).length,
);
const sheetHint = computed((): string => {
    const photos = pages.value.filter((one) => one.kind === 'photo').length;

    if (uncut.value === 0)
        return trans('Front and back of a card, on one sheet');

    return uncut.value === photos
        ? trans('Crop the :count photos to use it', { count: String(photos) })
        : transChoice(
              '{1} The crop of :count photo is missing|[2,*] The crop of :count photos is missing',
              uncut.value,
              { count: String(uncut.value) },
          );
});

const upload = useScanUpload({
    pages: () => pages.value,
    oneSheet: () => oneSheet.value,
    note: () => note.value,
    onArrived: (ids) => {
        for (const id of ids) drop(id);
    },
    onFinished: () => {
        note.value = '';
        oneSheet.value = false;
    },
});

/** What `ScanStatus` shows, the same on the phone and on the computer. */
const status = computed(() => ({
    notice: notice.value,
    error: upload.error.value,
    canRetry: upload.canRetry.value,
    done: upload.done.value,
    sending: upload.sending.value,
    progress: upload.progress.value,
    progressText: upload.progressText.value,
}));

// Pages not uploaded yet are lost if the page changes: the app asks first
useUnsavedChanges(() => ({
    pages: pages.value.map((one) => one.id),
    note: note.value,
}));

/** A new id for a page. */
let lastId = 0;
const newId = (): string => `page-${++lastId}`;

/**
 * The page with an id, as it is now (it may have been removed while a photo was worked on).
 *
 * @param id - the page
 * @returns the page, or undefined
 */
const find = (id: string): Page | undefined =>
    pages.value.find((one) => one.id === id);

/**
 * Changes some fields of a page.
 *
 * @param id - the page
 * @param patch - the fields that change
 */
function update(id: string, patch: Partial<Page>): void {
    doc.value = {
        ...doc.value,
        pages: doc.value.pages.map((one) =>
            one.id === id ? { ...one, ...patch } : one,
        ),
    };
}

/**
 * Gives back the memory of the pictures of a page.
 *
 * @param one - the page
 * @param keep - an address that stays
 */
function forget(one: Page, keep: string | null = null): void {
    for (const url of [one.photoUrl, one.sheetUrl, one.plainUrl]) {
        if (url && url !== keep) URL.revokeObjectURL(url);
    }
}

/**
 * Takes a page out of the document.
 *
 * @param id - the page
 */
function drop(id: string): void {
    const one = find(id);

    if (one) forget(one);

    doc.value = removePage(doc.value, id);
}

/**
 * Whether a file is a PDF.
 *
 * @param file - a file chosen
 * @returns true for a PDF
 */
const isPdf = (file: File): boolean =>
    file.type === 'application/pdf' || /\.pdf$/i.test(file.name);

/** The photos are worked on one after the other: a phone has little memory to spare. */
let work: Promise<void> = Promise.resolve();

/**
 * Adds the photos and PDF files chosen, or the photo taken.
 *
 * @param files - the files
 */
function addFiles(files: File[]): void {
    notice.value = null;
    upload.reset();
    const added: Page[] = [];
    const refused: string[] = [];

    for (const file of files) {
        const photo = !isPdf(file);

        if (photo && file.type !== '' && !file.type.startsWith('image/')) {
            refused.push(
                trans(':name is neither a photo nor a PDF.', {
                    name: file.name,
                }),
            );
            continue;
        }

        added.push({
            id: newId(),
            file,
            kind: photo ? 'photo' : 'pdf',
            corners: null,
            turns: 0,
            improve: { ...IMPROVE_DEFAULTS },
            cut: false,
            jpeg: null,
            photo: null,
            // shown at once, from the file as the phone gave it: reducing it takes seconds on a slow phone
            photoUrl: photo ? URL.createObjectURL(file) : null,
            sheetUrl: null,
            plainUrl: null,
            preparing: photo,
        });
    }

    if (refused.length > 0) notice.value = refused.join(' ');

    doc.value = addPages(doc.value, added);

    for (const one of added) {
        if (one.kind === 'photo') {
            work = work.then(() => prepare(one.id));
        }
    }
}

/**
 * Reduces a photo, then asks the server where the sheet is and cuts it out when it answers.
 *
 * @param id - the page
 */
async function prepare(id: string): Promise<void> {
    const one = find(id);

    if (!one) return;

    let photo: Blob;

    try {
        photo = await reduceToJpeg(one.file, LONG_SIDE, PHOTO_QUALITY);
    } catch {
        // the browser cannot draw it (a HEIC on a computer): it is not cropped and leaves as it is
        if (find(id)) {
            forget(one);
            update(id, { kind: 'pdf', photoUrl: null, preparing: false });
        }

        return;
    }

    if (!find(id)) return;

    forget(one);
    update(id, {
        photo,
        photoUrl: URL.createObjectURL(photo),
        preparing: false,
    });

    const found = await searchCorners(photo);
    const now = find(id);

    // cropped by hand in the meantime: the person wins
    if (found && now && !now.cut) {
        await cut(id, found, 0, {
            enhance: IMPROVE_DEFAULTS.enhance,
            grey: IMPROVE_DEFAULTS.grey,
        });
    }
}

/**
 * Asks the server where the sheet is, from the 1000 px copy.
 *
 * @param photo - the reduced photo
 * @returns the corners, or null when none came (no sheet, no answer in time, the limit of the day)
 */
async function searchCorners(photo: Blob): Promise<Corners | null> {
    try {
        return await findCorners(
            await reduceToJpeg(photo, SEARCH_LONG_SIDE, SEARCH_QUALITY),
        );
    } catch (caught) {
        // the limit of the day, or the reading off: the person sets the edges by hand
        if (
            caught instanceof ScanRequestError &&
            (caught.status === 429 || caught.status === 503)
        ) {
            notice.value = caught.message;
        }

        return null;
    }
}

/**
 * Cuts the sheet out of a photo and keeps it as the page that leaves.
 *
 * @param id - the page
 * @param corners - the corners, on the photo turned by `turns`
 * @param turns - quarters of a turn
 * @param improve - *Improve* and *Black and white*
 * @throws if the browser cannot cut the photo
 */
async function cut(
    id: string,
    corners: Corners,
    turns: Turns,
    improve: { enhance: boolean; grey: boolean },
): Promise<void> {
    const one = find(id);

    if (!one) return;

    const { jpeg, plain } = await cutOut(one.file, corners, turns, improve);
    const now = find(id);

    if (!now) return;

    for (const url of [now.sheetUrl, now.plainUrl]) {
        if (url) URL.revokeObjectURL(url);
    }

    update(id, {
        corners,
        turns,
        improve: { ...improve, dewarp: false },
        cut: true,
        jpeg,
        sheetUrl: URL.createObjectURL(jpeg),
        plainUrl: plain === jpeg ? null : URL.createObjectURL(plain),
    });
}

/**
 * The files chosen in one of the two fields: emptied at once, so the same photo can be chosen again.
 *
 * @param event - the change of the field
 */
function onFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    if (files.length > 0) addFiles(files);
}

/** *Take a picture*: the camera of the phone, or the webcam of a computer (a file without one). */
async function takePicture(): Promise<void> {
    if (phone || !webcam.available.value) {
        cameraInput.value?.click();

        return;
    }

    webcamOpen.value = true;

    if (!(await webcam.open())) {
        webcamOpen.value = false;
        notice.value = webcam.error.value;
    }
}

/**
 * The frame of the webcam becomes a page.
 *
 * @param video - the video of the webcam
 */
async function shoot(video: HTMLVideoElement): Promise<void> {
    const photo = await webcam.shoot(video);
    webcamOpen.value = false;

    if (photo) addFiles([photo]);
}

/** Closes the webcam. */
function closeWebcam(): void {
    webcam.close();
    webcamOpen.value = false;
}

/** *Cancel* of the document: one page goes at once, more ask first; with none the page is left. */
function cancel(): void {
    if (upload.sending.value) return;

    if (empty.value) {
        // a linked phone has nowhere else to go
        if (!linked.value) router.visit(toSort().url);

        return;
    }

    if (pages.value.length < 2) {
        discardAll();

        return;
    }

    discardAsking.value = true;
}

/** Every page goes, with *What it is*. */
function discardAll(): void {
    discardAsking.value = false;

    for (const one of pages.value) forget(one);

    doc.value = { pages: [], chosenId: null };
    note.value = '';
    oneSheet.value = false;
    notice.value = null;
    upload.reset();
}

/**
 * Opens *Crop* on a photo; «1 of N» counts the photos of the document that are ready.
 *
 * @param id - the photo to start from
 */
function openCrop(id: string): void {
    const ids = pages.value
        .filter((one) => one.kind === 'photo' && one.photo !== null)
        .map((one) => one.id);
    const index = ids.indexOf(id);

    if (index >= 0) crop.value = { ids, index, busy: false, problem: null };
}

/** The photo open in *Crop*. */
const cropping = computed((): Page | null => {
    const open = crop.value;

    return open ? (find(open.ids[open.index] ?? '') ?? null) : null;
});

/**
 * Whether a crop asks for what the page already has: the sheet is not cut again.
 *
 * @param one - the page
 * @param result - what *Next* asks for
 * @returns true when nothing changed
 */
function sameCrop(one: Page, result: CropResult): boolean {
    return (
        one.cut &&
        one.turns === result.turns &&
        one.improve.enhance === result.improve.enhance &&
        one.improve.grey === result.improve.grey &&
        JSON.stringify(one.corners) === JSON.stringify(result.corners)
    );
}

/**
 * *Next* in *Crop*: cuts the sheet out, then opens the next photo, or goes back to the scan after the last.
 *
 * @param result - the corners, the turns and the improvements
 */
async function cropNext(result: CropResult): Promise<void> {
    const open = crop.value;
    const one = cropping.value;

    if (!open || !one || open.busy) return;

    if (!sameCrop(one, result)) {
        open.busy = true;
        open.problem = null;

        try {
            await cut(one.id, result.corners, result.turns, result.improve);
        } catch {
            open.problem = trans('I could not crop this photo.');

            return;
        } finally {
            open.busy = false;
        }
    }

    if (crop.value !== open) return;

    if (open.index + 1 < open.ids.length) {
        open.index++;
        doc.value = selectPage(doc.value, open.ids[open.index] ?? '');
    } else {
        crop.value = null;
    }
}

/**
 * *On a single page* switched: while a photo is not cut out it stays off and opens *Crop* on that photo.
 *
 * @param value - the new state, as the toggle gives it
 */
function switchOneSheet(value: unknown): void {
    const on = value === true;

    if (on && sheetState.value === 'blocked') {
        oneSheet.value = false;
        const first = pages.value.find(
            (one) => one.kind === 'photo' && !one.cut,
        );

        if (first) openCrop(first.id);

        return;
    }

    oneSheet.value = on;
}

/** *Sign in with the password*: the phone signs out to the sign-in, where its cookie does not bring it back. */
function signInWithPassword(): void {
    router.post(logout().url);
}

/**
 * The key Enter of the keyboard closes it, and the bar at the bottom comes back.
 *
 * @param event - the key
 */
function onNoteKey(event: KeyboardEvent): void {
    if (event.key === 'Enter') (event.target as HTMLElement).blur();
}

// the original shown while holding belongs to one page
watch(
    () => doc.value.chosenId,
    () => {
        peeking.value = false;
    },
);

onBeforeUnmount(() => {
    for (const one of pages.value) forget(one);
});
</script>

<template>
    <Head :title="$t('Scan')" />

    <ScanCrop
        v-if="crop && cropping && cropping.photo"
        :key="cropping.id"
        :photo="cropping.photoUrl ?? ''"
        :source="cropping.photo"
        :corners="cropping.corners"
        :turns="cropping.turns"
        :improve="cropping.improve"
        :position="crop.index + 1"
        :count="crop.ids.length"
        :wide="wide"
        :busy="crop.busy"
        :problem="crop.problem"
        @next="cropNext"
        @cancel="crop = null"
    />

    <ScanWebcam
        v-if="webcamOpen"
        :stream="webcam.stream.value"
        @shoot="shoot"
        @cancel="closeWebcam"
    />

    <input
        ref="filesInput"
        data-testid="files-input"
        type="file"
        accept="image/*,application/pdf"
        multiple
        hidden
        @change="onFiles"
    />
    <input
        ref="cameraInput"
        data-testid="camera-input"
        type="file"
        accept="image/*"
        capture="environment"
        hidden
        @change="onFiles"
    />

    <header
        :class="
            cn(
                'grid shrink-0 grid-cols-[1fr_auto_1fr] items-center',
                wide
                    ? 'h-16 border-b border-edge-soft bg-card px-8'
                    : 'h-[52px] px-5 pt-3',
            )
        "
    >
        <button
            type="button"
            class="justify-self-start border-0 bg-transparent p-0 text-base font-semibold text-action disabled:text-ink-soft"
            :disabled="upload.sending.value"
            @click="cancel"
        >
            {{ $t('Cancel') }}
        </button>
        <h1 class="m-0 font-bold" :class="wide ? 'text-lg' : 'text-[17px]'">
            {{ $t('New document') }}
        </h1>
        <span class="justify-self-end text-[15px] text-ink-soft">{{
            count
        }}</span>
    </header>

    <div
        :class="
            cn(
                'flex min-h-0 grow',
                wide ? 'flex-row items-stretch' : 'flex-col',
            )
        "
    >
        <FmPageStrip
            v-if="wide && !empty"
            class="w-[150px] shrink-0 py-6"
            orientation="column"
            :pages="stripPages"
            :selected="doc.chosenId"
            @select="(id) => (doc = selectPage(doc, id))"
            @move="(from, to) => (doc = movePage(doc, from, to))"
            @add="takePicture"
        />

        <main
            :class="
                cn(
                    'flex min-w-0 grow flex-col items-center gap-3.5',
                    wide ? 'justify-center p-8' : 'px-5 pt-3 pb-4',
                )
            "
        >
            <template v-if="empty">
                <div
                    :class="
                        cn(
                            'flex shrink-0 flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed border-edge p-6 text-center',
                            wide
                                ? 'h-[580px] w-[420px]'
                                : 'h-[340px] w-[250px]',
                        )
                    "
                >
                    <FmIcon :icon="FileText" :size="36" class="text-ink-soft" />
                    <span class="text-base font-bold">{{
                        $t('No pages')
                    }}</span>
                    <span class="text-sm text-ink-soft">{{
                        wide
                            ? $t(
                                  'Take a photo of the document with the webcam, or choose photos and PDFs from the computer.',
                              )
                            : $t(
                                  'Take a photo of the document, or choose photos and PDFs from the phone.',
                              )
                    }}</span>
                </div>
                <FmButton
                    v-if="linked"
                    variant="text"
                    @click="signInWithPassword"
                    >{{ $t('Sign in with the password') }}</FmButton
                >
            </template>

            <template v-else-if="chosen">
                <div
                    data-preview
                    :class="
                        cn(
                            'relative flex shrink-0 items-center justify-center',
                            wide
                                ? 'h-[580px] w-[420px]'
                                : 'h-[340px] w-[250px]',
                        )
                    "
                >
                    <FmCropStage
                        v-if="
                            chosen.kind === 'photo' &&
                            chosen.cut &&
                            chosen.sheetUrl
                        "
                        class="max-h-full w-full shadow-float"
                        :image="
                            peeking && chosen.plainUrl
                                ? chosen.plainUrl
                                : chosen.sheetUrl
                        "
                        :corners="WHOLE"
                        :turns="0"
                        :editing="false"
                        @peek="(on) => (peeking = on)"
                    />
                    <img
                        v-else-if="chosen.kind === 'photo' && chosen.photoUrl"
                        :src="chosen.photoUrl"
                        :alt="
                            $t('Page :number', {
                                number: String(pages.indexOf(chosen) + 1),
                            })
                        "
                        class="max-h-full max-w-full rounded-card border border-edge-soft bg-card object-contain shadow-float"
                    />
                    <div
                        v-else
                        class="flex size-full flex-col items-center justify-center gap-2 rounded-card border border-edge-soft bg-card p-6 text-center shadow-float"
                    >
                        <FmIcon
                            :icon="FileText"
                            :size="40"
                            class="text-action"
                        />
                        <span class="text-base font-bold">{{
                            isPdf(chosen.file) ? 'PDF' : $t('File')
                        }}</span>
                        <span class="text-sm break-all text-ink-soft">{{
                            chosen.file.name
                        }}</span>
                    </div>
                    <span
                        v-if="chosen.preparing"
                        class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-tag bg-camera-bg px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-on-action"
                        >{{ $t('Preparing the photo…') }}</span
                    >
                    <span
                        v-else-if="chosen.cut && !peeking"
                        class="absolute top-3 right-3 rounded-tag bg-paid-soft px-2.5 py-1 text-xs font-semibold text-paid"
                        >{{ $t('Cropped') }}</span
                    >
                </div>

                <div class="flex gap-2.5">
                    <FmButton
                        v-if="chosen.kind === 'photo'"
                        variant="outline"
                        :icon="CropIcon"
                        :disabled="
                            chosen.preparing ||
                            chosen.photo === null ||
                            upload.sending.value
                        "
                        @click="openCrop(chosen.id)"
                        >{{
                            wide ? $t('Crop and improve') : $t('Crop')
                        }}</FmButton
                    >
                    <FmButton
                        variant="outline"
                        :icon="Trash2"
                        :label="$t('Remove this page')"
                        :disabled="upload.sending.value"
                        @click="drop(chosen.id)"
                    />
                </div>

                <FmPageStrip
                    v-if="!wide"
                    class="justify-center self-stretch"
                    :pages="stripPages"
                    :selected="doc.chosenId"
                    @select="(id) => (doc = selectPage(doc, id))"
                    @move="(from, to) => (doc = movePage(doc, from, to))"
                    @add="takePicture"
                />
            </template>

            <template v-if="!wide && !empty">
                <span v-if="summary" class="text-[13px] text-ink-soft">{{
                    summary
                }}</span>
                <div
                    v-if="sheetState !== 'hidden'"
                    class="self-stretch"
                    :class="sheetState === 'blocked' && 'opacity-60'"
                >
                    <FormKit
                        type="toggle"
                        name="one_sheet"
                        :model-value="oneSheet"
                        :label="$t('On a single page')"
                        :help="sheetHint"
                        @update:model-value="switchOneSheet"
                    />
                </div>
                <div
                    class="self-stretch"
                    @focusin="typing = phone"
                    @focusout="typing = false"
                    @keydown="onNoteKey"
                >
                    <FormKit
                        v-model="note"
                        type="text"
                        name="note"
                        :label="$t('What it is')"
                        :placeholder="$t('E.g. electricity bill for September')"
                        maxlength="200"
                    />
                </div>
            </template>

            <ScanStatus v-if="!wide" v-bind="status" @retry="upload.send" />
        </main>

        <aside
            v-if="wide"
            class="flex w-[380px] min-w-[300px] shrink flex-col gap-[18px] border-l border-edge-soft bg-card p-7"
        >
            <FormKit
                v-model="note"
                type="text"
                name="note"
                :label="$t('What it is')"
                :placeholder="$t('E.g. electricity bill for September')"
                maxlength="200"
            />
            <span v-if="summary" class="text-sm text-ink-soft">{{
                summary
            }}</span>
            <FormKit
                v-if="sheetState !== 'hidden'"
                type="toggle"
                name="one_sheet"
                :model-value="oneSheet"
                :label="$t('On a single page')"
                :help="sheetHint"
                @update:model-value="switchOneSheet"
            />
            <span class="grow" />
            <div class="flex flex-col items-center gap-4">
                <button
                    type="button"
                    :aria-label="$t('Take a photo with the webcam')"
                    class="flex size-[76px] items-center justify-center rounded-full border-4 border-action-soft bg-action text-on-action disabled:bg-edge"
                    :disabled="upload.sending.value"
                    @click="takePicture"
                >
                    <FmIcon :icon="Camera" :size="30" />
                </button>
                <FmButton
                    variant="outline"
                    :icon="FolderOpen"
                    :disabled="upload.sending.value"
                    @click="filesInput?.click()"
                    >{{ $t('From the computer') }}</FmButton
                >
            </div>
            <span class="grow" />
            <ScanStatus v-bind="status" @retry="upload.send" />
            <FmButton
                wide
                :disabled="empty || preparing || upload.sending.value"
                @click="upload.ask"
                >{{ $t('Upload') }}</FmButton
            >
        </aside>
    </div>

    <footer
        v-if="!wide && !typing"
        class="sticky bottom-0 grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-edge-soft bg-card px-5 pt-3 pb-[max(28px,env(safe-area-inset-bottom))]"
    >
        <button
            type="button"
            class="flex h-12 flex-col items-center justify-center gap-0.5 justify-self-start border-0 bg-transparent text-sm font-semibold text-action disabled:text-ink-soft"
            :disabled="upload.sending.value"
            @click="filesInput?.click()"
        >
            <FmIcon :icon="Images" :size="22" />{{ $t('From the phone') }}
        </button>
        <button
            type="button"
            :aria-label="$t('Take a picture')"
            class="flex size-[68px] items-center justify-center rounded-full border-4 border-action-soft bg-action text-on-action disabled:bg-edge"
            :disabled="upload.sending.value"
            @click="takePicture"
        >
            <FmIcon :icon="Camera" :size="28" />
        </button>
        <FmButton
            class="justify-self-end"
            :disabled="empty || preparing || upload.sending.value"
            @click="upload.ask"
            >{{ $t('Upload') }}</FmButton
        >
    </footer>

    <FmConfirm
        :open="upload.asking.value"
        :question="upload.question.value"
        :text="upload.details.value"
        :confirm-label="$t('Upload')"
        :cancel-label="$t('Not yet')"
        confirm-variant="solid"
        @update:open="(open) => (upload.asking.value = open)"
        @confirm="upload.send"
    />
    <FmConfirm
        v-model:open="discardAsking"
        :question="$t('Throw away :what?', { what: count })"
        :text="$t('What was not uploaded is lost.')"
        :confirm-label="$t('Throw away')"
        :cancel-label="$t('Keep')"
        @confirm="discardAll"
    />
</template>
