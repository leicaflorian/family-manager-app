<script setup lang="ts">
/**
 * *Crop* of the scan, dark as the boards `Ritaglia` and `Ritaglia-pc`: the photo with the four corners to move,
 * *Whole photo*, *Rotate*, *Find the edges* (the only way it asks the server, at a tap) and the choices of
 * *Improve*. On the phone the choices sit under the tools (decision 13 of the plan); on the computer they are in
 * the column on the right with *Cancel* and *Next*. «1 of 2» counts the photos of the document: *Next* goes on
 * to the next one, the last one goes back to the scan (decision 4). *Straighten* stays off until it arrives.
 */
import { Maximize, RotateCw, ScanSearch } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCropStage from '@/components/fm/FmCropStage.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { SEARCH_LONG_SIDE, reduceToJpeg } from '@/lib/scan/canvas';
import type { Corners, Turns } from '@/lib/scan/crop';
import { WHOLE, isSheet, turnCorners } from '@/lib/scan/crop';
import { CORNERS_TIMEOUT, findCorners } from '@/lib/scan/server';

/** What *Next* hands to the page: the sheet to cut out. */
export type CropResult = {
    corners: Corners;
    turns: Turns;
    improve: { enhance: boolean; grey: boolean };
};

const props = defineProps<{
    /** The address of the photo, as it was taken (the stage turns it). */
    photo: string;
    /** The same photo, for the small copy *Find the edges* sends. */
    source: Blob;
    /** The corners kept for the photo, on the photo turned by `turns`; null when none were found or set. */
    corners: Corners | null;
    /** Quarters of a turn the photo was given. */
    turns: Turns;
    /** *Improve* and *Black and white*, as they are for the photo. */
    improve: { enhance: boolean; grey: boolean };
    /** Which photo of the document this is, from 1. */
    position: number;
    /** How many photos the document has. */
    count: number;
    /** The layout of the computer, with the column on the right. */
    wide: boolean;
    /** The page is cutting the sheet out: *Next* waits. */
    busy: boolean;
    /** What went wrong while cutting, from the page. */
    problem: string | null;
}>();

const emit = defineEmits<{
    /** *Next* (or *Done*): the sheet to cut out of this photo. */
    next: [result: CropResult];
    /** *Cancel*: the photo stays as it was. */
    cancel: [];
}>();

const corners = ref<Corners>(props.corners ?? WHOLE);
const turns = ref<Turns>(props.turns);
const enhance = ref(props.improve.enhance);
const grey = ref(props.improve.grey);
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

const last = computed((): boolean => props.position >= props.count);
const title = computed((): string =>
    props.wide ? trans('Crop and improve') : trans('Crop'),
);
/** On the computer the last photo ends with *Done*, as the board; on the phone it is always *Next*. */
const nextLabel = computed((): string =>
    props.wide && last.value ? trans('Done') : trans('Next'),
);
const note = computed((): string | null => props.problem ?? message.value);

/**
 * A handle was moved.
 *
 * @param moved - all four corners
 */
function move(moved: Corners): void {
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

    emit('next', {
        corners: corners.value,
        turns: turns.value,
        improve: { enhance: enhance.value, grey: grey.value },
    });
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
                class="justify-self-start border-0 bg-transparent p-0 text-base font-semibold text-ink"
                @click="emit('cancel')"
            >
                {{ $t('Cancel') }}
            </button>
            <h1 class="m-0 flex flex-col items-center text-[17px] font-bold">
                <span id="crop-title">{{ title }}</span>
                <span class="text-xs font-medium text-ink-soft">{{
                    $t(':number of :count', {
                        number: String(position),
                        count: String(count),
                    })
                }}</span>
            </h1>
            <button
                type="button"
                class="justify-self-end border-0 bg-transparent p-0 text-base font-bold text-action disabled:text-ink-soft"
                :disabled="busy"
                @click="next"
            >
                {{ nextLabel }}
            </button>
        </header>

        <div
            class="flex min-h-0 min-w-0 grow flex-col items-center justify-center gap-4"
            :class="wide ? 'p-10' : 'px-5 py-3'"
        >
            <FmCropStage
                class="w-full"
                :class="wide ? 'max-w-[560px]' : 'max-w-[350px]'"
                fit
                :image="photo"
                :corners="corners"
                :turns="turns"
                editing
                @update:corners="move"
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
                    <h1 id="crop-title" class="m-0 text-[22px] font-extrabold">
                        {{ title }}
                    </h1>
                    <span
                        v-if="count > 1"
                        class="text-sm font-medium text-ink-soft"
                        >{{
                            $t(':number of :count', {
                                number: String(position),
                                count: String(count),
                            })
                        }}</span
                    >
                </div>
                <span
                    class="text-[13px] font-bold tracking-[0.3px] text-ink-soft uppercase"
                    >{{ $t('Cropping') }}</span
                >
            </template>

            <p
                v-if="note"
                role="status"
                class="m-0 text-center text-sm text-ink-soft"
                :class="wide && 'text-left'"
            >
                {{ note }}
            </p>

            <div
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
                    :disabled="tool.run === find && searching"
                    :class="
                        wide
                            ? 'flex h-12 flex-[1_1_0] items-center justify-center gap-2 rounded-lg border-0 bg-camera-raised px-3 text-[15px] font-semibold whitespace-nowrap text-ink disabled:opacity-60'
                            : 'flex h-14 min-w-[88px] flex-col items-center justify-center gap-1 border-0 bg-transparent text-[13px] font-semibold text-ink disabled:opacity-60'
                    "
                    @click="tool.run"
                >
                    <FmIcon :icon="tool.icon" :size="22" />{{ tool.label }}
                </button>
            </div>

            <span v-if="wide" class="h-px bg-camera-raised" />
            <span
                v-if="wide"
                class="text-[13px] font-bold tracking-[0.3px] text-ink-soft uppercase"
                >{{ $t('Improve') }}</span
            >

            <div class="flex flex-col gap-1.5">
                <FormKit
                    type="toggle"
                    name="dewarp"
                    :value="false"
                    disabled
                    :label="$t('Straighten')"
                    :help="$t('Straighten is not available on this phone')"
                />
                <FormKit
                    v-model="enhance"
                    type="toggle"
                    name="enhance"
                    :label="$t('Improve')"
                    :help="$t('Brightens the paper, darkens the text')"
                />
                <FormKit
                    v-model="grey"
                    type="toggle"
                    name="grey"
                    :label="$t('Black and white')"
                    :help="$t('Takes the colours away')"
                />
            </div>

            <template v-if="wide">
                <span class="grow" />
                <div class="flex justify-end gap-2.5">
                    <FmButton variant="text" @click="emit('cancel')">{{
                        $t('Cancel')
                    }}</FmButton>
                    <FmButton :disabled="busy" @click="next">{{
                        nextLabel
                    }}</FmButton>
                </div>
            </template>
        </component>
    </section>
</template>
