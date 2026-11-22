<script setup lang="ts">
/**
 * A drawing open: on the phone (`screen`) it covers the page on the dark
 * background of the camera, with the close button, the place in the gallery
 * and the heart on top, the arrows on the sides, a window of dots (at most
 * five, for decoration) under the picture and the details in a sheet at the bottom, and it moves to the next one with
 * the arrow keys or a drag to the side (the arrow at an end of the gallery is dimmed and does nothing); on the computer (`panel`) it is the
 * column beside the grid, on the card background, with ‹ › and the place on
 * the left and the × on the right, as the column of a document, and the
 * picture opens the file in a new tab.
 *
 * It shows the title, who drew it with the age and the date, the notes, the
 * link to the PDF when the file has more pages, and *Download*, *Share* (only
 * when the browser can share a file) and *Open as a document*: buttons at the
 * bottom on the phone, a ⋮ menu beside the × in the panel. The page decides
 * what the buttons do.
 *
 * @group panels
 */
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    Download,
    EllipsisVertical,
    ExternalLink,
    Heart,
    Info,
    Share2,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useFormat } from '@/composables/useFormat';
import { extensionOf } from '@/lib/files';
import { detailStep } from '@/lib/detail';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

const { drawing, position, total, variant } = defineProps<{
    /** The drawing open. */
    drawing: App.Data.DrawingData;
    /** Its place in the gallery, from 1; 0 when it is not among the listed ones (no place, no arrows). */
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

/** There is a drawing before this one. */
const hasPrevious = computed(() => position > 1 && position <= total);

/** There is a drawing after this one. */
const hasNext = computed(() => position >= 1 && position < total);

/** The place is known: the drawing is among the listed ones. */
const placed = computed(() => position >= 1);

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

/** The dots under the picture: at most five around the place, from 0. */
const dots = computed(() => {
    if (!placed.value) {
        return [];
    }

    const shown = Math.min(5, total);
    const first = Math.max(
        0,
        Math.min(position - 1 - Math.floor(shown / 2), total - shown),
    );

    return Array.from({ length: shown }, (_, index) => ({
        key: first + index,
        current: first + index === position - 1,
    }));
});

/** The close button of the phone, where the focus goes. */
const closeButton = ref<HTMLButtonElement | null>(null);

/** What had the focus before the viewer opened. */
let opener: HTMLElement | null = null;

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

/** The file of the drawing open, fetched when it opens so that *Share* opens the sheet within the tap. */
const shareFile = ref<File | null>(null);

/**
 * Fetches the file of the drawing open and names it after the title with the
 * extension of its type; a file that cannot be fetched or shared has no *Share*.
 */
async function prepareShare(): Promise<void> {
    shareFile.value = null;

    if (!canShareFile.value) {
        return;
    }

    const id = drawing.id;

    try {
        const blob = await (await fetch(drawing.download_url)).blob();
        const file = new File([blob], drawing.title + extensionOf(blob.type), {
            type: blob.type,
        });

        // Another drawing opened while this one was coming
        if (id === drawing.id && navigator.canShare?.({ files: [file] })) {
            shareFile.value = file;
        }
    } catch {
        // No file, no Share
    }
}

/**
 * Gives the file to the share sheet of the browser; a sheet that is closed by
 * the person is not an error.
 */
async function share(): Promise<void> {
    if (shareFile.value === null) {
        return;
    }

    try {
        await navigator.share({
            files: [shareFile.value],
            title: drawing.title,
        });
    } catch {
        // AbortError when the person closes the sheet; nothing to say
    }
}

watch(
    () => drawing.id,
    () => void prepareShare(),
);

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

    if (event.key === 'ArrowLeft' && hasPrevious.value) {
        emit('previous');
    } else if (event.key === 'ArrowRight' && hasNext.value) {
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

    if (moved > 60 && hasPrevious.value) {
        emit('previous');
    } else if (moved < -60 && hasNext.value) {
        emit('next');
    }
}

onMounted(() => {
    probeShare();
    void prepareShare();
    if (isScreen.value) {
        opener = document.activeElement as HTMLElement | null;
        void nextTick(() => closeButton.value?.focus());
    }
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    opener?.focus?.();
});

/** The round buttons on the dark background. */
const roundButton =
    'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full border-0 bg-camera-raised p-0 text-on-hero';

/** The square action buttons of the phone. */
const actionTile = cn(
    'flex min-h-[60px] flex-1 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-0 bg-camera-raised text-meta-strong text-on-hero no-underline',
);
</script>

<template>
    <div
        :role="isScreen ? 'dialog' : undefined"
        :aria-modal="isScreen ? 'true' : undefined"
        :aria-label="drawing.title"
        :class="
            cn(
                isScreen
                    ? 'fixed inset-0 z-50 flex flex-col overflow-hidden bg-camera-bg text-on-hero'
                    : 'flex w-[400px] max-w-full flex-col gap-3.5 rounded-card bg-card p-5 text-ink shadow-card',
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
                ref="closeButton"
                type="button"
                :aria-label="$t('Close')"
                :class="roundButton"
                @click="emit('close')"
            >
                <FmIcon :icon="X" :size="22" />
            </button>
            <span v-if="isScreen && placed" class="text-row text-on-hero">{{
                $t(':position of :total', {
                    position: String(position),
                    total: String(total),
                })
            }}</span>
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
            <template v-if="!isScreen">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :class="detailStep"
                        :aria-label="$t('Previous drawing')"
                        :disabled="!hasPrevious"
                        @click="emit('previous')"
                    >
                        <FmIcon :icon="ChevronLeft" :size="20" />
                    </button>
                    <button
                        type="button"
                        :class="detailStep"
                        :aria-label="$t('Next drawing')"
                        :disabled="!hasNext"
                        @click="emit('next')"
                    >
                        <FmIcon :icon="ChevronRight" :size="20" />
                    </button>
                    <span v-if="placed" class="text-body-sm text-ink-soft">{{
                        $t(':position of :total', {
                            position: String(position),
                            total: String(total),
                        })
                    }}</span>
                </div>
                <div class="-mr-2 flex shrink-0 items-center">
                    <FmMenu plain :icon="EllipsisVertical">
                        <FmMenuItem
                            :icon="Download"
                            :href="drawing.download_url"
                            download
                        >
                            {{ $t('Download') }}
                        </FmMenuItem>
                        <FmMenuItem
                            v-if="shareFile !== null"
                            :icon="Share2"
                            @select="share"
                        >
                            {{ $t('Share') }}
                        </FmMenuItem>
                        <FmMenuItem
                            :icon="ExternalLink"
                            :href="drawing.document_url"
                        >
                            {{ $t('Open as a document') }}
                        </FmMenuItem>
                    </FmMenu>
                    <button
                        type="button"
                        :aria-label="$t('Close')"
                        class="flex size-touch shrink-0 cursor-pointer items-center justify-center border-0 bg-transparent text-ink-soft"
                        @click="emit('close')"
                    >
                        <FmIcon :icon="X" :size="22" />
                    </button>
                </div>
            </template>
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
            <component
                :is="isScreen ? 'div' : 'a'"
                :href="isScreen ? undefined : drawing.file_url"
                :target="isScreen ? undefined : '_blank'"
                :rel="isScreen ? undefined : 'noopener'"
                class="contents"
            >
                <img
                    :src="drawing.image_url"
                    :alt="drawing.title"
                    draggable="false"
                    :class="
                        cn(
                            'object-contain select-none',
                            isScreen ? 'rounded-tag' : 'rounded-lg',
                            isScreen
                                ? 'max-h-full max-w-full'
                                : 'aspect-square w-full bg-canvas',
                        )
                    "
                />
            </component>
            <template v-if="isScreen">
                <button
                    type="button"
                    :aria-label="$t('Previous drawing')"
                    :disabled="!hasPrevious"
                    :class="
                        cn(
                            roundButton,
                            'absolute top-1/2 left-6 -mt-[22px]',
                            !hasPrevious && 'cursor-default text-ink-faint',
                        )
                    "
                    @click="emit('previous')"
                >
                    <FmIcon :icon="ChevronLeft" :size="22" />
                </button>
                <button
                    type="button"
                    :aria-label="$t('Next drawing')"
                    :disabled="!hasNext"
                    :class="
                        cn(
                            roundButton,
                            'absolute top-1/2 right-6 -mt-[22px]',
                            !hasNext && 'cursor-default text-ink-faint',
                        )
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
            v-if="isScreen"
            aria-hidden="true"
            class="flex justify-center gap-1.5 py-2.5"
        >
            <span
                v-for="dot in dots"
                :key="dot.key"
                :class="
                    cn(
                        'h-1.5 rounded-full',
                        dot.current
                            ? 'w-[18px] bg-on-hero'
                            : 'w-1.5 bg-on-hero-soft',
                    )
                "
            />
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
            <div :class="cn('flex flex-col', !isScreen && 'gap-1')">
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
            </div>
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

            <div v-if="isScreen" class="mt-1 flex gap-2.5">
                <a :href="drawing.download_url" :class="actionTile">
                    <FmIcon :icon="Download" :size="22" />{{ $t('Download') }}
                </a>
                <button
                    v-if="shareFile !== null"
                    type="button"
                    :class="actionTile"
                    @click="share"
                >
                    <FmIcon :icon="Share2" :size="22" />{{ $t('Share') }}
                </button>
                <a :href="drawing.document_url" :class="actionTile">
                    <FmIcon :icon="Info" :size="22" />{{ $t('Document') }}
                </a>
            </div>
        </div>
    </div>
</template>
