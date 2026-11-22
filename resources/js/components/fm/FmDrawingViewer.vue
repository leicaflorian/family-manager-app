<script setup lang="ts">
/**
 * A drawing open: on the phone (`screen`) it covers the page on the dark
 * background of the camera, with the close button, the place in the gallery
 * and the heart on top, the arrows on the sides, the dots under the picture
 * and the details in a sheet at the bottom, and it moves to the next one with
 * the arrow keys or a drag to the side; on the computer (`panel`) it is the
 * column beside the grid, on the card background.
 *
 * It shows the title, who drew it with the age and the date, the notes, the
 * link to the PDF when the file has more pages, and *Share* (only when the
 * browser can share a file), *Download* and *Open as a document*. The page
 * decides what the buttons do.
 *
 * @group panels
 */
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    Download,
    FileText,
    Heart,
    Share2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useFormat } from '@/composables/useFormat';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const { drawing, position, total, variant } = defineProps<{
    /** The drawing open. */
    drawing: App.Data.DrawingData;
    /** Its place in the gallery, from 1. */
    position: number;
    /** How many drawings the gallery has. */
    total: number;
    /** `screen` covers the phone, `panel` is the column of the computer. */
    variant: 'screen' | 'panel';
}>();

const emit = defineEmits<{
    /** The close button. */
    close: [];
    /** The previous drawing (arrow, key or drag to the right). */
    previous: [];
    /** The next drawing (arrow, key or drag to the left). */
    next: [];
    /** The heart: the value the favourite should take. */
    favorite: [value: boolean];
}>();

const { date } = useFormat();

/** True on the phone. */
const isScreen = computed(() => variant === 'screen');

/** Who drew it, with the age and when it was uploaded, in a line. */
const byline = computed(() => {
    const on = date(drawing.uploaded_on);

    if (!drawing.person_name) {
        return trans('Uploaded on :date', { date: on });
    }

    return drawing.age === null
        ? trans(':name · uploaded on :date', {
              name: drawing.person_name,
              date: on,
          })
        : trans(':name, :age years · uploaded on :date', {
              name: drawing.person_name,
              age: String(drawing.age),
              date: on,
          });
});

/** The file has more than one page: the PDF is the way to see them. */
const manyPages = computed(() => drawing.pages !== null && drawing.pages > 1);

/** The browser can share a file; known after the page is on screen. */
const canShareFile = ref(false);

/**
 * Asks the browser if it shares files, with an empty file as a probe.
 */
function probeShare(): void {
    canShareFile.value =
        typeof navigator !== 'undefined' &&
        typeof navigator.share === 'function' &&
        navigator.canShare?.({ files: [new File([], 'drawing')] }) === true;
}

/**
 * Takes the file and gives it to the share sheet of the browser; a sheet that
 * is closed by the person is not an error.
 */
async function share(): Promise<void> {
    try {
        const blob = await (await fetch(drawing.download_url)).blob();
        const file = new File([blob], drawing.title, { type: blob.type });

        if (navigator.canShare?.({ files: [file] })) {
            await navigator.share({ files: [file], title: drawing.title });
        }
    } catch {
        // AbortError when the person closes the sheet; nothing to say
    }
}

/**
 * The arrow keys move through the drawings, Escape closes, on the phone only
 * (the panel sits beside a page that has its own keys).
 *
 * @param event The key pressed.
 */
function onKey(event: KeyboardEvent): void {
    if (!isScreen.value) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        emit('previous');
    } else if (event.key === 'ArrowRight') {
        emit('next');
    } else if (event.key === 'Escape') {
        emit('close');
    }
}

/** Where a drag to the side started. */
let dragFrom: number | null = null;

/**
 * Ends a drag: a long enough move to the side changes the drawing.
 *
 * @param x Where the finger was lifted.
 */
function endDrag(x: number): void {
    if (dragFrom === null) {
        return;
    }

    const moved = x - dragFrom;
    dragFrom = null;

    if (moved > 60) {
        emit('previous');
    } else if (moved < -60) {
        emit('next');
    }
}

onMounted(() => {
    probeShare();
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

/** The round buttons on the dark background. */
const roundButton =
    'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full border-0 bg-camera-raised p-0 text-on-hero';

/** The square action buttons of the phone. */
const actionTile = cn(
    'flex min-h-[60px] flex-1 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-0 bg-camera-raised text-meta-strong text-on-hero no-underline',
);

/** The buttons of the panel. */
const panelButton = cn(
    'inline-flex h-control items-center justify-center gap-2 rounded-control border border-edge-soft bg-card px-5 text-body text-ink no-underline',
);
</script>

<template>
    <div
        :role="isScreen ? 'dialog' : undefined"
        :aria-label="drawing.title"
        :class="
            cn(
                isScreen
                    ? 'fixed inset-0 z-50 flex flex-col overflow-hidden bg-camera-bg text-on-hero'
                    : 'flex flex-col gap-3.5 rounded-lg border border-edge-soft bg-card p-3.5 text-ink',
            )
        "
    >
        <div
            :class="
                cn(
                    'flex items-center justify-between gap-2',
                    isScreen && 'px-4 py-3',
                )
            "
        >
            <button
                v-if="isScreen"
                type="button"
                :aria-label="$t('Close')"
                :class="roundButton"
                @click="emit('close')"
            >
                <FmIcon :icon="X" :size="22" />
            </button>
            <span
                :class="
                    isScreen
                        ? 'text-label text-on-hero-soft'
                        : 'text-meta text-ink-soft'
                "
                >{{
                    $t(':position of :total', {
                        position: String(position),
                        total: String(total),
                    })
                }}</span
            >
            <button
                v-if="isScreen && drawing.can_favorite"
                type="button"
                :aria-label="
                    drawing.is_favorite
                        ? $t('Remove from favourites')
                        : $t('Add to favourites')
                "
                :class="cn(roundButton, drawing.is_favorite && 'text-overdue')"
                @click="emit('favorite', !drawing.is_favorite)"
            >
                <Heart
                    :size="22"
                    :stroke-width="2"
                    :fill="drawing.is_favorite ? 'currentColor' : 'none'"
                    aria-hidden="true"
                />
            </button>
            <span v-if="!isScreen" class="flex gap-1">
                <button
                    type="button"
                    :aria-label="$t('Previous drawing')"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-control border border-edge-soft bg-card p-0 text-ink"
                    @click="emit('previous')"
                >
                    <FmIcon :icon="ChevronLeft" :size="18" />
                </button>
                <button
                    type="button"
                    :aria-label="$t('Next drawing')"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-control border border-edge-soft bg-card p-0 text-ink"
                    @click="emit('next')"
                >
                    <FmIcon :icon="ChevronRight" :size="18" />
                </button>
                <button
                    type="button"
                    :aria-label="$t('Close')"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-control border-0 bg-transparent p-0 text-ink-soft"
                    @click="emit('close')"
                >
                    <FmIcon :icon="X" :size="18" />
                </button>
            </span>
        </div>

        <div
            :class="
                cn(
                    'relative',
                    isScreen &&
                        'flex min-h-0 grow touch-pan-y items-center justify-center px-4 py-1',
                )
            "
            @pointerdown="dragFrom = $event.clientX"
            @pointerup="endDrag($event.clientX)"
            @pointercancel="dragFrom = null"
        >
            <img
                :src="drawing.image_url"
                :alt="drawing.title"
                draggable="false"
                :class="
                    cn(
                        'rounded-tag object-contain select-none',
                        isScreen
                            ? 'max-h-full max-w-full'
                            : 'aspect-square w-full bg-canvas',
                    )
                "
            />
            <template v-if="isScreen">
                <button
                    type="button"
                    :aria-label="$t('Previous drawing')"
                    :class="
                        cn(roundButton, 'absolute top-1/2 left-6 -mt-[22px]')
                    "
                    @click="emit('previous')"
                >
                    <FmIcon :icon="ChevronLeft" :size="22" />
                </button>
                <button
                    type="button"
                    :aria-label="$t('Next drawing')"
                    :class="
                        cn(roundButton, 'absolute top-1/2 right-6 -mt-[22px]')
                    "
                    @click="emit('next')"
                >
                    <FmIcon :icon="ChevronRight" :size="22" />
                </button>
            </template>
            <button
                v-if="!isScreen && drawing.can_favorite"
                type="button"
                :aria-label="
                    drawing.is_favorite
                        ? $t('Remove from favourites')
                        : $t('Add to favourites')
                "
                :class="
                    cn(
                        'absolute top-2.5 right-2.5 flex size-10 cursor-pointer items-center justify-center rounded-full border-0 bg-card p-0 text-ink shadow-raised',
                        drawing.is_favorite && 'text-overdue',
                    )
                "
                @click="emit('favorite', !drawing.is_favorite)"
            >
                <Heart
                    :size="20"
                    :stroke-width="2"
                    :fill="drawing.is_favorite ? 'currentColor' : 'none'"
                    aria-hidden="true"
                />
            </button>
        </div>

        <div
            :class="
                cn(
                    'flex flex-col gap-2.5',
                    isScreen &&
                        'rounded-t-sheet bg-camera-surface px-5 pt-5 pb-6',
                )
            "
        >
            <h2 class="m-0 text-sheet-title">
                {{ drawing.title }}
            </h2>
            <span
                :class="
                    cn(
                        'text-body-sm',
                        isScreen ? 'text-on-hero-soft' : 'text-ink-soft',
                    )
                "
                >{{ byline }}</span
            >
            <p v-if="drawing.notes" class="m-0 text-body">
                {{ drawing.notes }}
            </p>
            <a
                v-if="manyPages"
                :href="drawing.file_url"
                target="_blank"
                rel="noopener"
                :class="
                    cn(
                        'inline-flex min-h-11 items-center gap-2 self-start text-label underline',
                        isScreen ? 'text-on-hero' : 'text-action no-underline',
                    )
                "
            >
                <FmIcon :icon="Copy" :size="18" />{{
                    $tChoice(
                        '{1} :count page · open the PDF to see it|[2,*] :count pages · open the PDF to see them',
                        drawing.pages ?? 0,
                    )
                }}
            </a>

            <div
                :class="
                    cn(
                        'flex gap-2.5',
                        isScreen
                            ? 'mt-1'
                            : 'flex-wrap gap-2 border-t border-divider pt-3.5',
                    )
                "
            >
                <button
                    v-if="canShareFile"
                    type="button"
                    :class="isScreen ? actionTile : panelButton"
                    @click="share"
                >
                    <FmIcon :icon="Share2" :size="isScreen ? 22 : 20" />{{
                        $t('Share')
                    }}
                </button>
                <a
                    :href="drawing.download_url"
                    :class="
                        isScreen
                            ? actionTile
                            : cn(
                                  panelButton,
                                  'border-0 bg-action-soft text-action-dark',
                              )
                    "
                >
                    <FmIcon :icon="Download" :size="isScreen ? 22 : 20" />{{
                        $t('Download')
                    }}
                </a>
                <a
                    :href="drawing.document_url"
                    :class="
                        isScreen
                            ? actionTile
                            : cn(
                                  panelButton,
                                  'border-0 bg-transparent px-3 text-action',
                              )
                    "
                >
                    <FmIcon :icon="FileText" :size="isScreen ? 22 : 20" />{{
                        $t('Open as a document')
                    }}
                </a>
            </div>
        </div>
    </div>
</template>
