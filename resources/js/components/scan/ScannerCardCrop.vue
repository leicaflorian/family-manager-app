<script setup lang="ts">
/**
 * *Cut* of *Scan the sheets*: the cut of one card of a composed page, corrected by hand on the side of the scanner it
 * comes from. The four corners of the box are dragged onto the edge of the card, the slider turns the side under the
 * box when the card lay crooked, *Find it by itself* puts back what the page found. It always starts from the side
 * of the scanner, so doing it again loses nothing. *Done* gives the cut to the page; closing does not.
 */
import { Grid3x3 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import ScannerSlider from '@/components/scan/ScannerSlider.vue';
import type { Box, Cut } from '@/lib/scan/card';
import { MOST_CUT_ANGLE } from '@/lib/scan/card';
import { CARD_CORNER } from '@/lib/scan/crop';
import { angleText } from '@/lib/scan/improve';
import { cn } from '@/lib/utils';

const { image, width, height, millimetre, found, cut, round } = defineProps<{
    /** The JPEG of the side of the scanner the card is cut from. */
    image: Blob;
    /** Its size in dots. */
    width: number;
    height: number;
    /** How many of its dots make a millimetre. */
    millimetre: number;
    /** Where the page found the card, for *Find it by itself*; null when it found none. */
    found: Cut | null;
    /** The cut as it is now. */
    cut: Cut;
    /** The corners of the card are rounded: the box shows them so. */
    round: boolean;
}>();

const emit = defineEmits<{
    /** *Done*: the cut corrected. */
    done: [cut: Cut];
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });

/** The least a side of the box can be made, in millimetres. */
const LEAST_CUT = 10;

const box = ref<Box>({ ...cut.box });
const angle = ref(cut.angle);
const grid = ref(false);
const frame = ref<HTMLElement | null>(null);
const url = ref('');
/** The corner being dragged: 1 top left, then clockwise. */
let grabbed: number | null = null;

// every time it opens it starts from the cut of the page, on a picture of its own
watch(
    () => open.value,
    (now) => {
        if (url.value) URL.revokeObjectURL(url.value);

        url.value = now ? URL.createObjectURL(image) : '';

        if (now) {
            box.value = { ...cut.box };
            angle.value = cut.angle;
            grid.value = false;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (url.value) URL.revokeObjectURL(url.value);
});

/**
 * The square the cut is corrected in, in dots of the side: as large as the side from corner to corner, so the side
 * fits in it however it is turned, with the side in the middle.
 */
const stage = computed(() => {
    const size = Math.hypot(width, height);

    return { size, left: (size - width) / 2, top: (size - height) / 2 };
});

/** A length in dots of the side as a share of the square. */
const share = (value: number): string => `${(value / stage.value.size) * 100}%`;

const pictureStyle = computed(() => ({
    left: '50%',
    top: '50%',
    width: share(width),
    height: share(height),
    transform: `translate(-50%, -50%) rotate(${angle.value}deg)`,
}));

const boxStyle = computed(() => {
    // with the corners rounded the box shows them as they will be cut
    const corner = Math.min(
        CARD_CORNER * millimetre,
        box.value.width / 2,
        box.value.height / 2,
    );

    return {
        left: share(box.value.x + stage.value.left),
        top: share(box.value.y + stage.value.top),
        width: share(box.value.width),
        height: share(box.value.height),
        borderRadius: round
            ? `${(corner / box.value.width) * 100}% / ${(corner / box.value.height) * 100}%`
            : '0',
    };
});

/** The size of the box in millimetres, in both languages: «85,6 × 54,0 mm». */
const sizeText = computed((): string => {
    const text = (dots: number): string =>
        (dots / millimetre).toFixed(1).replace('.', ',');

    return `${text(box.value.width)} × ${text(box.value.height)} mm`;
});

/** The four corners, for the handles: where each is and its name. */
const corners = [
    { corner: 1, place: cn('top-0 left-0 -translate-x-1/2 -translate-y-1/2') },
    { corner: 2, place: cn('top-0 right-0 translate-x-1/2 -translate-y-1/2') },
    {
        corner: 3,
        place: cn('right-0 bottom-0 translate-x-1/2 translate-y-1/2'),
    },
    {
        corner: 4,
        place: cn('bottom-0 left-0 -translate-x-1/2 translate-y-1/2'),
    },
];

/**
 * Takes a corner under the pointer.
 *
 * @param event - the pointer pressed
 * @param corner - which corner
 */
function grab(event: PointerEvent, corner: number): void {
    (event.target as HTMLElement).setPointerCapture?.(event.pointerId);
    grabbed = corner;
}

/**
 * The corner dragged follows the pointer, within the square; the opposite one stays where it is.
 *
 * @param event - the pointer moving
 */
function drag(event: PointerEvent): void {
    if (grabbed === null || !frame.value) return;

    const bounds = frame.value.getBoundingClientRect();
    const { size, left: offsetX, top: offsetY } = stage.value;
    const x =
        Math.min(1, Math.max(0, (event.clientX - bounds.left) / bounds.width)) *
            size -
        offsetX;
    const y =
        Math.min(1, Math.max(0, (event.clientY - bounds.top) / bounds.height)) *
            size -
        offsetY;
    const least = LEAST_CUT * millimetre;
    const now = box.value;
    let [left, top, right, bottom] = [
        now.x,
        now.y,
        now.x + now.width,
        now.y + now.height,
    ];

    if (grabbed === 1 || grabbed === 4) left = Math.min(x, right - least);
    else right = Math.max(x, left + least);

    if (grabbed === 1 || grabbed === 2) top = Math.min(y, bottom - least);
    else bottom = Math.max(y, top + least);

    box.value = { x: left, y: top, width: right - left, height: bottom - top };
}

/** The corner is let go. */
function drop(): void {
    grabbed = null;
}

/**
 * The side under the box turned to some degrees, within what a cut may turn it.
 *
 * @param value - the degrees
 */
function setAngle(value: number): void {
    angle.value =
        Math.min(
            MOST_CUT_ANGLE,
            Math.max(-MOST_CUT_ANGLE, Math.round(value * 10) / 10),
        ) || 0;
}

/** Back to the box and the angle the page found by itself. */
function findAgain(): void {
    if (!found) return;

    box.value = { ...found.box };
    angle.value = found.angle;
}

/** *Done*: the cut goes to the page. */
function done(): void {
    emit('done', { box: { ...box.value }, angle: angle.value });
    open.value = false;
}
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="$t('Cut')" size="lg">
        <div class="flex flex-col gap-4">
            <div
                ref="frame"
                class="relative mx-auto aspect-square w-full max-w-[420px] overflow-hidden rounded-card bg-sketch"
            >
                <img
                    v-if="url"
                    :src="url"
                    :alt="$t('The side of the scanner')"
                    class="absolute max-w-none"
                    :style="pictureStyle"
                />
                <div
                    v-if="grid"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--color-action)_1px,transparent_1px),linear-gradient(to_bottom,var(--color-action)_1px,transparent_1px)] bg-[size:10%_10%] opacity-40"
                />
                <div
                    data-crop-box
                    class="absolute border-2 border-action"
                    :style="boxStyle"
                >
                    <span
                        v-for="{ corner, place } in corners"
                        :key="corner"
                        :class="
                            cn(
                                'absolute size-5 cursor-grab touch-none rounded-full border-2 border-card bg-action',
                                place,
                            )
                        "
                        @pointerdown.prevent="grab($event, corner)"
                        @pointermove="drag"
                        @pointerup="drop"
                        @pointercancel="drop"
                    />
                </div>
            </div>

            <p class="m-0 text-body-sm text-ink-soft">
                {{
                    $t(
                        'Drag the corners onto the edge of the card. It always starts from the side of the scanner: doing it again does not spoil it.',
                    )
                }}
            </p>

            <div class="flex items-baseline justify-between gap-3 text-body-sm">
                <span class="font-bold">{{ $t('Size') }}</span>
                <span class="tabular-nums" data-crop-size>{{ sizeText }}</span>
            </div>
            <span class="-mt-3 text-body-sm text-ink-soft">{{
                $t('A card is 85,6 × 54,0 mm')
            }}</span>

            <ScannerSlider
                :model-value="angle"
                :label="$t('Straighten')"
                :value-text="angleText(angle)"
                :min="-MOST_CUT_ANGLE"
                :max="MOST_CUT_ANGLE"
                :step="0.5"
                :nudge="0.1"
                @update:model-value="setAngle"
            />
            <span class="-mt-2 text-body-sm text-ink-soft">{{
                $t('Turns the side under the box, if the card lay crooked')
            }}</span>

            <div class="flex flex-wrap items-center gap-2">
                <FmButton
                    variant="outline"
                    :disabled="!found"
                    @click="findAgain"
                    >{{ $t('Find it by itself') }}</FmButton
                >
                <span class="grow" />
                <FmButton
                    :variant="grid ? 'soft' : 'outline'"
                    small
                    :icon="Grid3x3"
                    :label="$t('Reference lines')"
                    :aria-pressed="grid"
                    @click="grid = !grid"
                />
            </div>

            <div class="flex justify-end gap-2.5">
                <FmButton variant="outline" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton @click="done">{{ $t('Done') }}</FmButton>
            </div>
        </div>
    </FmBottomSheet>
</template>
