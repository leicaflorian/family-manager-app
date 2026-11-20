<script setup lang="ts">
/**
 * *Crop* of the scan, dark, in two steps as the boards `Ritaglia`, `Ritaglia-2`, `Ritaglia-pc` and `Ritaglia-2-pc`
 * draw them. *Crop* (1 of 2): the photo with the four corners to move, *Whole photo*, *Rotate* and *Find the edges*
 * (the only way it asks the server, at a tap); *Next* hands the corners to the page, which cuts the sheet out.
 * *Improve* (2 of 2): the sheet as it is cut out and saved, with *Straighten*, *Black and white* and the strength of
 * *Improve* from 0 to 100; each change goes to the page, which shows it on the sheet. *Back* goes back to the corners,
 * *Done* back to the pages. On the phone the choices sit under the photo; on the computer they are in the column on
 * the right. *Straighten* lays the cut sheet flat (off at the start, and disabled with its reason where the phone
 * cannot run it); while it works a veil with a spinner covers the sheet, and *Cancel* or *Back* stops it.
 */
import { LoaderCircle, Maximize, RotateCw, ScanSearch } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCropStage from '@/components/fm/FmCropStage.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import ScannerSlider from '@/components/scan/ScannerSlider.vue';
import { SEARCH_LONG_SIDE, reduceToJpeg } from '@/lib/scan/canvas';
import type { Corners, Turns } from '@/lib/scan/crop';
import { WHOLE, isSheet, turnCorners } from '@/lib/scan/crop';
import { isDewarpAvailable } from '@/lib/scan/dewarp';
import { CORNERS_TIMEOUT, findCorners } from '@/lib/scan/server';

/** What *Next* of *Crop* hands to the page: where the sheet is. */
export type CropResult = {
    corners: Corners;
    turns: Turns;
};

/** *Improve* from 0 to 100, *Black and white* and *Straighten*, as the photo has them. */
export type CropImprove = { enhance: number; grey: boolean; dewarp: boolean };

/** The step open: the corners, or the improvements on the cut sheet. */
export type CropStep = 'crop' | 'improve';

const props = defineProps<{
    /** The address of the photo, as it was taken (the stage turns it). */
    photo: string;
    /** The same photo, for the small copy *Find the edges* sends. */
    source: Blob;
    /** The corners kept for the photo, on the photo turned by `turns`; null when none were found or set. */
    corners: Corners | null;
    /** Quarters of a turn the photo was given. */
    turns: Turns;
    /** The improvements, as they are for the photo. */
    improve: CropImprove;
    /** The address of the sheet cut out, with its improvements, shown in *Improve*. */
    sheet: string | null;
    /** The step open. */
    step: CropStep;
    /** The layout of the computer, with the column on the right. */
    wide: boolean;
    /** The page is cutting the sheet out: *Next* and *Done* wait. */
    busy: boolean;
    /** What went wrong while cutting, from the page. */
    problem: string | null;
}>();

const emit = defineEmits<{
    /** *Next* of *Crop*: the sheet to cut out of this photo. */
    next: [result: CropResult];
    /** A change in *Improve*, to show on the sheet. */
    improve: [value: CropImprove];
    /** *Back* of *Improve*: back to the corners. */
    back: [];
    /** *Done* of *Improve*: back to the pages. */
    done: [];
    /** *Cancel* of *Crop*: the photo stays as it was. */
    cancel: [];
}>();

const corners = ref<Corners>(props.corners ?? WHOLE);
const turns = ref<Turns>(props.turns);
/** Whether this phone can run *Straighten* (a worker and WebAssembly). */
const canStraighten = isDewarpAvailable();
const enhance = ref(props.improve.enhance);
const grey = ref(props.improve.grey);
const dewarp = ref(props.improve.dewarp && canStraighten);
/** A handle was moved, or *Whole photo* chosen: corners that arrive late do not replace them. */
const touched = ref(false);
const searching = ref(false);
const message = ref<string | null>(null);

/** The corners the page finds by itself, arriving while this photo is open, before a handle is moved. */
watch(
    () => props.corners,
    (found) => {
        if (found && !touched.value && turns.value === props.turns) {
            corners.value = found;
        }
    },
);

/** *Improve* opens on what the photo has: a *Straighten* stopped with *Back* is not kept. */
watch(
    () => props.step,
    (step) => {
        if (step !== 'improve') return;

        enhance.value = props.improve.enhance;
        grey.value = props.improve.grey;
        dewarp.value = props.improve.dewarp && canStraighten;
    },
);

const cropping = computed((): boolean => props.step === 'crop');
const title = computed((): string =>
    cropping.value ? trans('Crop') : trans('Improve'),
);
const stepText = computed((): string =>
    trans(':number of :count', {
        number: cropping.value ? '1' : '2',
        count: '2',
    }),
);
const note = computed((): string | null =>
    cropping.value ? (props.problem ?? message.value) : props.problem,
);

/**
 * A handle was moved.
 *
 * @param moved - all four corners
 */
function move(moved: Corners): void {
    // the sheet is being cut with these corners: they stay as they are
    if (props.busy) return;

    corners.value = moved;
    touched.value = true;
}

/** *Whole photo*: the corners on the edges of the photo. */
function whole(): void {
    move(WHOLE);
}

/** *Rotate*: a quarter clockwise, the corners going round with the photo; the server is not asked. */
function rotate(): void {
    turns.value = ((turns.value + 1) % 4) as Turns;
    corners.value = turnCorners(corners.value, 1);
}

/** Stops a search still running when *Crop* closes or moves to the next photo. */
const away = new AbortController();

onBeforeUnmount(() => away.abort());

/** *Find the edges*: asks the server where the sheet is, a call that costs. */
async function find(): Promise<void> {
    if (searching.value) return;

    searching.value = true;
    touched.value = false;
    message.value = trans('Looking for the edges of the sheet…');

    try {
        const small = await reduceToJpeg(props.source, SEARCH_LONG_SIDE, 0.8);

        if (away.signal.aborted) return;

        const found = await findCorners(small, CORNERS_TIMEOUT, away.signal);

        // the server saw the photo as it was taken: its corners go round as the photo has
        if (found && !touched.value) {
            corners.value = turnCorners(found, turns.value);
        }

        message.value = found
            ? trans(
                  'Check the edges of the sheet and, if needed, drag the corners.',
              )
            : trans(
                  'I did not find the edges of the sheet: drag the corners by hand.',
              );
    } catch (caught) {
        // *Crop* was closed: nobody reads the answer
        if (away.signal.aborted) return;

        message.value = trans(':reason Drag the corners by hand.', {
            reason:
                caught instanceof Error
                    ? caught.message
                    : trans('The edges of the sheet could not be looked for.'),
        });
    } finally {
        searching.value = false;
    }
}

/** *Next*: the corners must make a sheet. */
function next(): void {
    if (props.busy) return;

    if (!isSheet(corners.value)) {
        message.value = trans(
            'The corners cross: put them back on the four corners of the sheet.',
        );

        return;
    }

    emit('next', { corners: corners.value, turns: turns.value });
}

/**
 * A choice of *Improve* changed: it goes to the page when it is really new (FormKit gives the value back when it mounts).
 *
 * @param change - the choice and its value
 */
function choose(change: Partial<CropImprove>): void {
    const wanted: CropImprove = {
        enhance: change.enhance ?? enhance.value,
        grey: change.grey ?? grey.value,
        dewarp: change.dewarp ?? dewarp.value,
    };

    if (
        wanted.enhance === enhance.value &&
        wanted.grey === grey.value &&
        wanted.dewarp === dewarp.value
    ) {
        return;
    }

    enhance.value = wanted.enhance;
    grey.value = wanted.grey;
    dewarp.value = wanted.dewarp;
    emit('improve', wanted);
}

/** *Cancel* in *Crop*, *Back* in *Improve*. */
function leave(): void {
    if (cropping.value) emit('cancel');
    else emit('back');
}

/** *Next* in *Crop*, *Done* in *Improve*. */
function forward(): void {
    if (cropping.value) next();
    else if (!props.busy) emit('done');
}
</script>

<template>
    <!-- Dark in both themes: the tokens of the dark theme give the text and the switches their colours -->
    <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="crop-title"
        class="dark fixed inset-0 z-50 flex bg-camera-bg text-ink"
        :class="wide ? 'flex-row' : 'flex-col'"
    >
        <header
            v-if="!wide"
            class="grid h-[52px] shrink-0 grid-cols-[1fr_auto_1fr] items-center px-5 pt-3"
        >
            <button
                type="button"
                class="justify-self-start border-0 bg-transparent p-0 text-body-strong text-ink"
                @click="leave"
            >
                {{ cropping ? $t('Cancel') : $t('Back') }}
            </button>
            <h1 class="m-0 flex flex-col items-center text-section">
                <span id="crop-title">{{ title }}</span>
                <span class="text-caption text-ink-soft">{{ stepText }}</span>
            </h1>
            <button
                type="button"
                class="justify-self-end border-0 bg-transparent p-0 text-amount text-action disabled:text-ink-soft"
                :disabled="busy"
                @click="forward"
            >
                {{ cropping ? $t('Next') : $t('Done') }}
            </button>
        </header>

        <div
            class="relative flex min-h-0 min-w-0 grow flex-col items-center justify-center gap-4"
            :class="wide ? 'p-10' : 'px-5 py-3'"
        >
            <!-- The veil: the model works on the cut sheet; Cancel or Back (outside the veil) stops it -->
            <div
                v-if="busy && dewarp"
                role="status"
                class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-camera-bg/80 text-body-strong text-ink"
            >
                <FmIcon :icon="LoaderCircle" :size="32" class="animate-spin" />
                {{ $t('Straightening…') }}
            </div>
            <FmCropStage
                v-if="cropping"
                class="w-full"
                :class="wide ? 'max-w-[560px]' : 'max-w-[350px]'"
                fit
                :image="photo"
                :corners="corners"
                :turns="turns"
                editing
                @update:corners="move"
            />
            <img
                v-else-if="sheet"
                :src="sheet"
                :alt="$t('The cut sheet, as it will be saved')"
                class="block max-h-full w-full rounded-sm bg-card object-contain shadow-raised"
                :class="wide ? 'max-w-[440px]' : 'max-w-[300px]'"
            />
        </div>

        <component
            :is="wide ? 'aside' : 'div'"
            class="flex shrink-0 flex-col gap-[18px] bg-camera-surface"
            :class="
                wide
                    ? 'w-[400px] min-w-[320px] px-7 py-8'
                    : 'px-5 pt-3 pb-[max(28px,env(safe-area-inset-bottom))]'
            "
        >
            <template v-if="wide">
                <div class="flex flex-col gap-1">
                    <h1 id="crop-title" class="m-0 text-stat-lg">
                        {{ title }}
                    </h1>
                    <span class="text-body-sm text-ink-soft">{{
                        stepText
                    }}</span>
                </div>
                <span
                    v-if="cropping"
                    class="text-meta-strong text-ink-soft uppercase"
                    >{{ $t('Cropping') }}</span
                >
            </template>

            <p
                v-if="note"
                role="status"
                class="m-0 text-center text-body-sm text-ink-soft"
                :class="wide && 'text-left'"
            >
                {{ note }}
            </p>

            <div
                v-if="cropping"
                class="flex gap-2.5"
                :class="wide ? 'flex-wrap' : 'justify-center gap-6'"
            >
                <button
                    v-for="tool in [
                        {
                            label: $t('Whole photo'),
                            icon: Maximize,
                            run: whole,
                        },
                        { label: $t('Rotate'), icon: RotateCw, run: rotate },
                        {
                            label: $t('Find the edges'),
                            icon: ScanSearch,
                            run: find,
                        },
                    ]"
                    :key="tool.label"
                    type="button"
                    :disabled="busy || (tool.run === find && searching)"
                    :class="
                        wide
                            ? 'flex h-12 flex-[1_1_0] items-center justify-center gap-2 rounded-lg border-0 bg-camera-raised px-3 text-row whitespace-nowrap text-ink disabled:opacity-60'
                            : 'flex h-14 min-w-[88px] flex-col items-center justify-center gap-1 border-0 bg-transparent text-meta-strong text-ink disabled:opacity-60'
                    "
                    @click="tool.run"
                >
                    <FmIcon :icon="tool.icon" :size="22" />{{ tool.label }}
                </button>
            </div>

            <div v-else class="flex flex-col gap-1.5">
                <FormKit
                    type="toggle"
                    name="dewarp"
                    :model-value="dewarp"
                    :disabled="busy || !canStraighten"
                    :label="$t('Straighten')"
                    :help="
                        canStraighten
                            ? $t('Lays a folded sheet flat')
                            : $t('Straighten is not available on this phone')
                    "
                    @update:model-value="
                        (value: unknown) => choose({ dewarp: value === true })
                    "
                />
                <FormKit
                    type="toggle"
                    name="grey"
                    :model-value="grey"
                    :disabled="busy"
                    :label="$t('Black and white')"
                    :help="$t('Takes the colours away')"
                    @update:model-value="
                        (value: unknown) => choose({ grey: value === true })
                    "
                />
                <div class="flex flex-col gap-1.5 py-1.5">
                    <ScannerSlider
                        :model-value="enhance"
                        :label="$t('Improve')"
                        :value-text="`${enhance} %`"
                        :min="0"
                        :max="100"
                        :step="5"
                        :disabled="busy"
                        @update:model-value="choose({ enhance: $event })"
                    />
                    <span class="text-meta text-ink-soft">{{
                        $t(
                            'Lightens the paper, darkens the text. All the way left the photo stays as it is; past halfway, more contrast.',
                        )
                    }}</span>
                </div>
            </div>

            <template v-if="wide">
                <span class="grow" />
                <div class="flex justify-end gap-2.5">
                    <FmButton variant="text" @click="leave">{{
                        cropping ? $t('Cancel') : $t('Back')
                    }}</FmButton>
                    <FmButton :disabled="busy" @click="forward">{{
                        cropping ? $t('Next') : $t('Done')
                    }}</FmButton>
                </div>
            </template>
        </component>
    </section>
</template>
