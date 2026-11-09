<script setup lang="ts">
/**
 * The photo of the scan with the four corners of the sheet to move: the outside of the sheet is dimmed, the
 * sheet has a blue outline and a white handle on each corner. While a handle is held a lens enlarges three
 * times what is under it, in a corner of the photo that the finger is not covering. With `editing` off it
 * shows the cut out sheet without handles, and holding it for 0.3 seconds asks for the original photo
 * (`peek`) for as long as the finger stays. The default slot lays things on the photo itself (a badge such as
 * *Cropped*), and `fit` keeps the photo inside the height of its box as well as its width.
 *
 * @group files
 */
import { cn } from '@/lib/utils';
import type { Corners, Point, Turns } from '@/lib/scan/crop';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

/** The side of the lens on the screen, in pixels, and how many times it enlarges. */
const LENS_SIDE = 88;
const LENS_ZOOM = 3;
/** The corners of the photo the lens can be in, the first being the one it starts in. */
const LENS_SPOTS = [
    'top-right',
    'top-left',
    'bottom-right',
    'bottom-left',
] as const;
/** How long the finger stays on the cut out sheet before the original shows, in milliseconds. */
const PEEK_MS = 300;
/** A finger that moves more than this, in pixels, before then is not holding. */
const PEEK_SLACK = 10;
/** The step of an arrow key, as a part of the photo. */
const KEY_STEP = 0.01;

type LensSpot = (typeof LENS_SPOTS)[number];

const props = defineProps<{
    /** The photo (or, without `editing`, the cut out sheet). */
    image: string;
    /** The four corners of the sheet on the photo as it is shown (already turned), from 0 to 1. */
    corners: Corners;
    /** Quarters of a turn the photo is shown with, clockwise. */
    turns: Turns;
    /** The handles are there. Off: the cut out sheet is shown and holding it shows the original. */
    editing: boolean;
    /** The photo fits inside the height of the box too, not only its width (a full screen like *Crop*). */
    fit?: boolean;
}>();

defineSlots<{
    /** Laid on the photo, inside its box: a badge placed with `absolute`. */
    default?: () => unknown;
}>();

const emit = defineEmits<{
    /** A handle was moved: all four corners. */
    'update:corners': [corners: Corners];
    /** The original is wanted (true) while the cut out sheet is held, and the sheet again (false) at the end. */
    peek: [on: boolean];
}>();

const stage = ref<HTMLElement | null>(null);
/** The shape of the photo as shown, width over height, known when it has loaded. */
const shape = ref(3 / 4);
/** The box of the photo: its shape, which `fit` also reads to keep it inside the height of the box. */
const stageStyle = computed((): Record<string, string> => ({
    aspectRatio: String(shape.value),
    '--stage-shape': String(shape.value),
}));
/** The size of the stage when the lens was last drawn, in pixels. */
const size = ref({ width: 0, height: 0 });
/** The handle being dragged. */
const active = ref<number | null>(null);
const spot = ref<LensSpot>('top-right');
const peeking = ref(false);

let peekHold: {
    pointer: number;
    x: number;
    y: number;
    timer: ReturnType<typeof setTimeout>;
} | null = null;

const names = computed(() => [
    trans('Top left corner'),
    trans('Top right corner'),
    trans('Bottom right corner'),
    trans('Bottom left corner'),
]);

/** The photo laid out in a box of the shape it is shown with: turned a quarter, it is swapped inside. */
const photoStyle = computed(() => {
    const sideways = props.turns % 2 === 1;

    return {
        width: sideways ? `${100 / shape.value}%` : '100%',
        height: sideways ? `${100 * shape.value}%` : '100%',
        transform: `translate(-50%, -50%) rotate(${props.turns * 90}deg)`,
    };
});

/** The corners as the polygon of the drawing, in parts of the photo. */
const polygon = computed(() =>
    props.corners.map((corner) => `${corner.x},${corner.y}`).join(' '),
);

/** The same, with the outer frame, to dim what is outside the sheet. */
const outside = computed(
    () =>
        `M0,0H1V1H0Z M${props.corners.map((corner) => `${corner.x},${corner.y}`).join(' L')}Z`,
);

/**
 * Reads the shape of the photo as shown, once it has loaded.
 *
 * @param event - the load event of the picture
 */
function onLoad(event: Event): void {
    const image = event.target as HTMLImageElement;

    if (!image.naturalWidth || !image.naturalHeight) return;

    shape.value =
        props.turns % 2 === 1
            ? image.naturalHeight / image.naturalWidth
            : image.naturalWidth / image.naturalHeight;
}

/** Reads the size of the stage. */
function measure(): DOMRect | null {
    const box = stage.value?.getBoundingClientRect() ?? null;

    if (box) size.value = { width: box.width, height: box.height };

    return box;
}

/** Where to put the lens: it stays in its corner until the handle comes under it, then goes to the next free one. */
function lensSpot(point: Point): LensSpot {
    const x = point.x * size.value.width;
    const y = point.y * size.value.height;
    // the lens with its margin, and the finger around the handle
    const reach = LENS_SIDE + 12 + 34;
    const clear = (candidate: LensSpot): boolean =>
        (candidate.endsWith('left') ? x : size.value.width - x) > reach ||
        (candidate.startsWith('top') ? y : size.value.height - y) > reach;

    return clear(spot.value)
        ? spot.value
        : (LENS_SPOTS.find(clear) ?? spot.value);
}

/** Moves the lens next to the corner held. */
function placeLens(): void {
    if (active.value === null) return;

    spot.value = lensSpot(props.corners[active.value]);
}

/** Style of the enlarged photo in the lens: three times the stage, the corner in the middle. */
const lensStyle = computed(() => {
    const point = props.corners[active.value ?? 0];
    const width = size.value.width * LENS_ZOOM;
    const height = size.value.height * LENS_ZOOM;

    return {
        width: `${width}px`,
        height: `${height}px`,
        left: `${LENS_SIDE / 2 - point.x * width}px`,
        top: `${LENS_SIDE / 2 - point.y * height}px`,
    };
});

/** The corners with one changed. */
function withCorner(index: number, point: Point): Corners {
    return props.corners.map((corner, at) =>
        at === index ? point : corner,
    ) as Corners;
}

/** A coordinate kept inside the photo. */
const within = (value: number): number => Math.min(1, Math.max(0, value));

/**
 * A pointer goes down on a handle: it is followed from now on, even outside the handle.
 *
 * @param event - the pointer event
 * @param index - the corner
 */
function onHandleDown(event: PointerEvent, index: number): void {
    event.preventDefault();

    try {
        (event.currentTarget as HTMLElement).setPointerCapture?.(
            event.pointerId,
        );
    } catch {
        // the finger has already left
    }

    active.value = index;
    measure();
    placeLens();
}

/**
 * The pointer moves with a handle held: the corner goes under it, within the photo.
 *
 * @param event - the pointer event
 */
function onHandleMove(event: PointerEvent): void {
    const index = active.value;
    const box = measure();

    if (index === null || !box?.width || !box.height) return;

    const point = {
        x: within((event.clientX - box.left) / box.width),
        y: within((event.clientY - box.top) / box.height),
    };

    emit('update:corners', withCorner(index, point));
    // the lens looks at where the corner is going, before the page sends the new corners back
    spot.value = lensSpot(point);
}

/** The handle is let go. */
function onHandleUp(): void {
    active.value = null;
}

/**
 * An arrow key moves the focused handle by a hundredth of the photo.
 *
 * @param event - the key event
 * @param index - the corner
 */
function onKey(event: KeyboardEvent, index: number): void {
    const step = event.shiftKey ? KEY_STEP * 5 : KEY_STEP;
    const move: Record<string, [number, number]> = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };
    const delta = move[event.key];

    if (!delta) return;

    event.preventDefault();

    const point = props.corners[index];

    emit(
        'update:corners',
        withCorner(index, {
            x: within(point.x + delta[0]),
            y: within(point.y + delta[1]),
        }),
    );
}

/** Stops waiting for the original, and puts the sheet back if the original was shown. */
function endPeek(): void {
    if (peekHold) clearTimeout(peekHold.timer);

    peekHold = null;

    if (peeking.value) {
        peeking.value = false;
        emit('peek', false);
    }
}

/**
 * A pointer goes down on the cut out sheet: after 0.3 seconds the original shows.
 *
 * @param event - the pointer event
 */
function onStageDown(event: PointerEvent): void {
    if (props.editing || peekHold) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;

    peekHold = {
        pointer: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        timer: setTimeout(() => {
            peeking.value = true;
            emit('peek', true);
        }, PEEK_MS),
    };
}

/**
 * The pointer moves on the sheet: a finger that moves before the original shows is not holding.
 *
 * @param event - the pointer event
 */
function onStageMove(event: PointerEvent): void {
    if (
        peekHold &&
        !peeking.value &&
        event.pointerId === peekHold.pointer &&
        Math.hypot(event.clientX - peekHold.x, event.clientY - peekHold.y) >
            PEEK_SLACK
    ) {
        endPeek();
    }
}

/**
 * The pointer goes up, is taken away or leaves the sheet.
 *
 * @param event - the pointer event
 */
function onStageEnd(event: PointerEvent): void {
    if (peekHold && event.pointerId === peekHold.pointer) endPeek();
}

// the handles come back: a finger still waiting on the sheet is not holding it any more
watch(
    () => props.editing,
    (editing) => {
        if (editing) endPeek();
    },
);

// the page gets the sheet back even when the stage goes away under the finger
onBeforeUnmount(endPeek);
</script>

<template>
    <div
        class="flex flex-col items-center gap-4"
        :class="fit && 'h-full min-h-0'"
    >
        <div
            data-fit-box
            class="flex w-full items-center justify-center"
            :class="fit && 'min-h-0 grow'"
            :style="fit ? { containerType: 'size' } : undefined"
        >
            <div
                ref="stage"
                data-stage
                class="relative overflow-hidden rounded-lg bg-camera-bg select-none"
                :class="
                    fit
                        ? 'w-[min(100cqw,calc(100cqh*var(--stage-shape)))] shrink-0'
                        : 'w-full'
                "
                :style="stageStyle"
                @pointerdown="onStageDown"
                @pointermove="onStageMove"
                @pointerup="onStageEnd"
                @pointercancel="onStageEnd"
                @pointerleave="onStageEnd"
                @contextmenu.prevent
            >
                <img
                    :src="image"
                    alt=""
                    draggable="false"
                    class="pointer-events-none absolute top-1/2 left-1/2 max-w-none"
                    :style="photoStyle"
                    @load="onLoad"
                />
                <template v-if="editing">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-0 size-full"
                        viewBox="0 0 1 1"
                        preserveAspectRatio="none"
                    >
                        <path
                            :d="outside"
                            class="fill-camera-bg"
                            fill-opacity="0.6"
                            fill-rule="evenodd"
                        />
                        <polygon
                            :points="polygon"
                            fill="none"
                            class="stroke-action"
                            stroke-width="3"
                            vector-effect="non-scaling-stroke"
                        />
                    </svg>
                    <button
                        v-for="(corner, index) in corners"
                        :key="index"
                        type="button"
                        :aria-label="names[index]"
                        class="absolute flex size-11 -translate-x-1/2 -translate-y-1/2 touch-none items-center justify-center rounded-full border-0 bg-transparent p-0 focus-visible:outline-none"
                        :style="{
                            left: `${corner.x * 100}%`,
                            top: `${corner.y * 100}%`,
                        }"
                        @pointerdown="onHandleDown($event, index)"
                        @pointermove="onHandleMove"
                        @pointerup="onHandleUp"
                        @pointercancel="onHandleUp"
                        @lostpointercapture="onHandleUp"
                        @keydown="onKey($event, index)"
                    >
                        <span
                            :class="
                                cn(
                                    'size-[26px] rounded-full border-4 border-action bg-on-action',
                                    'in-focus-visible:shadow-focus',
                                )
                            "
                        />
                    </button>
                    <div
                        v-if="active !== null"
                        data-testid="lens"
                        :data-spot="spot"
                        aria-hidden="true"
                        :class="
                            cn(
                                'pointer-events-none absolute size-[88px] overflow-hidden rounded-lg border-2 border-on-action bg-camera-bg shadow-float',
                                spot.startsWith('top') ? 'top-3' : 'bottom-3',
                                spot.endsWith('left') ? 'left-3' : 'right-3',
                            )
                        "
                    >
                        <div data-zoom class="absolute" :style="lensStyle">
                            <img
                                :src="image"
                                alt=""
                                draggable="false"
                                class="absolute top-1/2 left-1/2 max-w-none"
                                :style="photoStyle"
                            />
                        </div>
                        <span
                            class="absolute top-1/2 left-1/2 h-[14px] w-px -translate-x-1/2 -translate-y-1/2 bg-on-action shadow-float"
                        />
                        <span
                            class="absolute top-1/2 left-1/2 h-px w-[14px] -translate-x-1/2 -translate-y-1/2 bg-on-action shadow-float"
                        />
                    </div>
                </template>
                <span
                    v-if="peeking"
                    class="dark pointer-events-none absolute top-3 left-3 rounded-tag bg-camera-bg px-2.5 py-1 text-caption text-ink"
                    >{{ $t('Original') }}</span
                >
                <slot />
            </div>
        </div>
        <!-- on the dark of the camera in both themes: the tokens of the dark theme give it a light grey -->
        <p v-if="editing" class="dark text-center text-body-sm text-ink-soft">
            {{ $t('Drag the corners to the edges of the sheet') }}
        </p>
    </div>
</template>
