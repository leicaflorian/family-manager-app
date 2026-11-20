<script setup lang="ts">
/**
 * *Scan the sheets*: the scanner of the house, driven from the computer through its agent, as the boards `Scanner-pc`
 * and the other `Scanner-…` draw it: under the title a bar of what is done *On all the pages*, the sides in a column
 * on the left (with the ones put aside at its foot), the side in large in the middle with *Rotate*, *Remove* and the
 * reference lines, and on the right the choices, in two tabs once there are sides (*This page* with *Improve*, or the
 * cards of a composed page, and *Scanner*), then the *Import options*, and at the bottom *Add sheets* and *Upload*,
 * which asks first. A window too narrow is asked to widen (board `Scanner`). Put the sheets in the feeder and press
 * *Scan*: they all go through, front and back, and the blank sides are put aside by themselves. A side that went in crooked is
 * straightened by a few degrees; with *On a single page* the cards are cut and put at their real size on one A4.
 * *Upload* makes one PDF of the sides that stay, in the order of the strip (or one per side), sends it to *To sort*
 * as scanned and goes back there. The sides stay, and can be uploaded, also when the agent closes. A phone, a tablet
 * or a Linux computer is told that the scanner is used from a Mac or a PC. Sides not uploaded yet are changes not
 * saved: the app asks before leaving them.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    Copy,
    ChevronLeft,
    FileMinus,
    FileText,
    Grid3x3,
    Hourglass,
    IdCard,
    Maximize2,
    PowerOff,
    RotateCw,
    ScanLine,
    Upload,
    Wand,
} from '@lucide/vue';
import { transChoice, trans } from 'laravel-vue-i18n';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { StripPage } from '@/components/fm/FmPageStrip.vue';
import FmPageStrip from '@/components/fm/FmPageStrip.vue';
import FmTooltip from '@/components/fm/FmTooltip.vue';
import ScanStatus from '@/components/scan/ScanStatus.vue';
import ScannerAgent from '@/components/scan/ScannerAgent.vue';
import ScannerAsideSides from '@/components/scan/ScannerAsideSides.vue';
import ScannerCardCrop from '@/components/scan/ScannerCardCrop.vue';
import ScannerSettings from '@/components/scan/ScannerSettings.vue';
import ScannerSheetParts from '@/components/scan/ScannerSheetParts.vue';
import ScannerSideTools from '@/components/scan/ScannerSideTools.vue';
import type { Side } from '@/composables/useScanner';
import { useScanner } from '@/composables/useScanner';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { useWideLayout } from '@/composables/useWideLayout';
import { desktopSystem } from '@/lib/devices';
import { formatSize } from '@/lib/format';
import type { Phase, ScannerState } from '@/lib/scan/agent';
import { MAX_UPLOAD_BYTES } from '@/lib/scan/pages';
import { cn } from '@/lib/utils';
import { toSort } from '@/routes';
import { agent as agentDownload } from '@/routes/scanner';

const { agent_release: release } = defineProps<{
    /** The last version of the agent: not there until the server has asked GitHub, `null` when it did not answer. */
    agent_release?: App.Data.AgentReleaseData | null;
}>();

const wide = useWideLayout();
const scanner = useScanner();
const system = desktopSystem();
const origin = window.location.origin;
const name = ref('');
const grid = ref(false);
/** The tab open among the choices, once there are sides. */
const tab = ref<'page' | 'scanner'>('page');
/** *Upload* asks first. */
const asking = ref(false);
/** The card of a composed page whose cut is being corrected: the page and the place of the card on it. */
const cropping = ref<{ sheet: string; index: number } | null>(null);

/** The size of the box of the side in large, in pixels. */
const FRAME = { width: 420, height: 580 } as const;

/** How the scanner is, as the line above *Scan* says it. */
const STATES: Record<ScannerState, () => string> = {
    ready: () => trans('Ready, sheets in the feeder'),
    empty: () => trans('Put the sheets in the feeder'),
    off: () => trans('Scanner off or not plugged in'),
    opening: () => trans('Opening the scanner…'),
};

/** Why a scan stopped. */
const FAILURES: Record<string, () => string> = {
    settings: () =>
        trans(
            'The scanner does not accept these choices: change the resolution, the colour or where it reads from, and try again.',
        ),
    off: () => trans('The scanner has turned off or has been unplugged.'),
    empty: () =>
        trans('There are no sheets in the feeder: put them in and try again.'),
    jam: () =>
        trans(
            'A sheet is stuck: free it, put the sheets that have not gone through back in the feeder and press Add sheets.',
        ),
    scanner: () => trans('The scanner has stopped.'),
};

const connected = computed((): boolean => scanner.phase.value === 'connected');
/** Where the page is with the agent, when it is not connected: the notice above the sides says it. */
const agentPhase = computed((): Exclude<Phase, 'connected'> | null =>
    scanner.phase.value === 'connected' ? null : scanner.phase.value,
);
const hasSides = computed((): boolean => scanner.sides.value.length > 0);
const fromGlass = computed(
    (): boolean => scanner.choosing.value && scanner.source.value === 'flatbed',
);
const stripPages = computed((): StripPage[] =>
    scanner.kept.value.map((side) => ({
        id: side.id,
        url: side.url || null,
        rotation: side.rotation,
    })),
);
const count = computed((): string =>
    scanner.kept.value.length === 0
        ? ''
        : transChoice(
              '{1} :count page|[2,*] :count pages',
              scanner.kept.value.length,
              { count: String(scanner.kept.value.length) },
          ),
);
const stateText = computed((): string => {
    if (scanner.scanning.value) return trans('Scanning…');

    if (!scanner.choosing.value) return STATES[scanner.scannerState.value]();

    if (!scanner.scannerId.value) {
        return scanner.scanners.value.length
            ? trans('Choose the scanner')
            : trans('No scanner: turn it on or plug it in');
    }

    if (scanner.scannerState.value === 'ready' && fromGlass.value) {
        return scanner.oneSheet.value
            ? trans('Ready: put the card on the glass')
            : trans('Ready: put the sheet on the glass');
    }

    return STATES[scanner.scannerState.value]();
});
const scanLabel = computed((): string => {
    const cards = scanner.cardSides.value;

    if (scanner.oneSheet.value && fromGlass.value) {
        return cards === 0
            ? trans('Scan the front')
            : cards === 1
              ? trans('Scan the back')
              : trans('Add a side');
    }

    // from the feeder a card goes through once, front and back
    if (scanner.oneSheet.value) {
        return cards ? trans('Add another card') : trans('Scan the card');
    }

    if (!hasSides.value) return trans('Start scanning');

    return fromGlass.value ? trans('Add a sheet') : trans('Add sheets');
});
/** The window is too narrow for the page: it asks to be widened, unless the scanner cannot be used from here at all. */
const narrow = computed(
    (): boolean => !wide.value && agentPhase.value !== 'unsupported',
);
/** The choices of the scanner are there (an agent that knows them). */
const choices = computed(
    (): boolean => connected.value && scanner.choosing.value,
);
/** The bar *On all the pages*: when there is more than one page, or something to do to all of them. */
const allBar = computed(
    (): boolean =>
        scanner.kept.value.length > 1 ||
        scanner.loose.value.length > 1 ||
        scanner.crooked.value.length > 0,
);
/** The line of how the scanner is: not when it is ready and there are sides already, as the board draws it. */
const showState = computed(
    (): boolean =>
        connected.value &&
        (!hasSides.value ||
            scanner.scanning.value ||
            scanner.scannerState.value !== 'ready'),
);
/** The question of *Upload*: «Shall I upload the 3 sides?». */
const question = computed((): string =>
    transChoice(
        '{1} Shall I upload the side?|[2,*] Shall I upload the :count sides?',
        scanner.kept.value.length,
        { count: String(scanner.kept.value.length) },
    ),
);
/** Under the question: what they become, and what stays out. */
const details = computed((): string => {
    const sides = scanner.kept.value.length;
    const aside = scanner.aside.value.length;

    return [
        scanner.split.value
            ? trans('They become :count PDFs in «To sort», one per side.', {
                  count: String(sides),
              })
            : transChoice(
                  '{1} It becomes a PDF in «To sort».|[2,*] They become a PDF in «To sort».',
                  sides,
              ),
        aside > 0 &&
            transChoice(
                '{1} The side put aside stays out.|[2,*] The :count sides put aside stay out.',
                aside,
                { count: String(aside) },
            ),
    ]
        .filter(Boolean)
        .join(' ');
});
const outdated = computed((): boolean => scanner.outdated(release?.version));
/** The state line takes the icon of how the scanner is. */
const stateIcon = computed(() => {
    if (scanner.scannerState.value === 'off') return PowerOff;
    if (scanner.scannerState.value === 'opening' || scanner.scanning.value) {
        return Hourglass;
    }

    return CircleCheck;
});
const status = computed(() => ({
    notice: null,
    error: scanner.uploadError.value,
    canRetry:
        scanner.uploadError.value !== null &&
        !scanner.sending.value &&
        !scanner.tooLarge.value,
    done: null,
    sending: scanner.sending.value,
    progress: scanner.progress.value,
    progressText: scanner.progressText.value,
}));
/** How many sides, how many PDFs and what they weigh. */
const weightText = computed((): string => {
    const sides = scanner.kept.value.length;
    const size = formatSize(scanner.weight.value);

    return scanner.split.value
        ? transChoice(
              '{1} :count side → :count PDF · :size|[2,*] :count sides → :count PDF · :size',
              sides,
              { count: String(sides), size },
          )
        : transChoice(
              '{1} :count side · :size|[2,*] :count sides · :size',
              sides,
              { count: String(sides), size },
          );
});
const crookedText = computed((): string =>
    transChoice(
        '{1} Straighten the crooked one|[2,*] Straighten the :count crooked ones',
        scanner.crooked.value.length,
        { count: String(scanner.crooked.value.length) },
    ),
);
/** The card whose cut is being corrected, with its page. */
const cropped = computed(() => {
    const at = cropping.value;
    const sheet = at
        ? scanner.sides.value.find((side) => side.id === at.sheet)
        : undefined;
    const part = at ? sheet?.parts?.[at.index] : undefined;

    return part ? { sheet: sheet!, part } : null;
});
const croppingOpen = computed({
    get: (): boolean => cropped.value !== null,
    set: (open: boolean): void => {
        if (!open) cropping.value = null;
    },
});

// Sides not uploaded yet are lost if the page changes: the app asks first. Not while the agent is being started: it
// looks to the browser like leaving the page
const changes = useUnsavedChanges(
    () => ({
        sides: scanner.kept.value.map((side) => side.id),
        name: name.value,
    }),
    { active: () => !scanner.launching.value },
);

/**
 * The picture of the side in large, turned as it is given and, while a slider moves, by the degrees not made yet.
 *
 * @param side - the side
 * @returns its style
 */
function previewStyle(side: Side): Record<string, string> {
    const turn =
        side.rotation +
        (side.parts ? 0 : side.improvement.angle - side.applied.angle);
    const lying = side.rotation === 90 || side.rotation === 270;
    const { width, height } = FRAME;

    return {
        transform: `rotate(${turn}deg)`,
        maxWidth: `${lying ? height : width}px`,
        maxHeight: `${lying ? width : height}px`,
    };
}

/** *Try again*, or *Start the agent*: the agent is looked for again, and the server asked again for its version. */
function retry(): void {
    router.reload({ only: ['agent_release'] });
    void scanner.connect(true);
}

/** *Upload* in the question: closes it and sends. */
function confirmUpload(): void {
    asking.value = false;
    void upload();
}

/** *Upload*: sends the PDF, then goes back to *To sort*. */
async function upload(): Promise<void> {
    if (!(await scanner.upload(name.value))) return;

    name.value = '';
    changes.reset();
    router.visit(toSort().url);
}
</script>

<template>
    <Head :title="$t('Scan the sheets')" />

    <!-- As tall as the window, as the board: the choices scroll on their own and Upload stays in sight -->
    <div class="flex h-dvh flex-col overflow-hidden">
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
            <FmButton
                class="-ml-3 justify-self-start"
                variant="text"
                :icon="ChevronLeft"
                data-discard-changes
                :href="toSort().url"
                >{{ $t('Back') }}</FmButton
            >
            <h1 class="m-0" :class="wide ? 'text-subtitle' : 'text-section'">
                {{ $t('Scan the sheets') }}
            </h1>
            <span class="justify-self-end text-body-sm text-ink-soft">{{
                narrow ? '' : count
            }}</span>
        </header>

        <div v-if="narrow" class="flex grow items-center justify-center p-6">
            <div
                role="status"
                :aria-label="$t('Widen the window')"
                class="flex max-w-[300px] flex-col items-center gap-2.5 text-center"
            >
                <span
                    class="flex size-16 items-center justify-center rounded-card bg-action-soft text-action"
                >
                    <FmIcon :icon="Maximize2" :size="32" />
                </span>
                <span class="text-stat text-ink">{{
                    $t('Widen the window')
                }}</span>
                <span class="text-body-sm text-ink-soft">{{
                    $t(
                        'To use the scanner more room is needed: widen the browser window or make it full screen. The page arranges itself.',
                    )
                }}</span>
            </div>
        </div>

        <template v-else>
            <div
                v-if="agentPhase"
                :class="
                    cn(
                        'overflow-y-auto',
                        hasSides ? 'max-h-[40%] shrink-0' : 'min-h-0 grow',
                    )
                "
            >
                <ScannerAgent
                    :phase="agentPhase"
                    :system="system"
                    :origin="origin"
                    :release="release"
                    :blocked="scanner.blocked.value"
                    :has-sides="hasSides"
                    @retry="retry"
                />
            </div>

            <div
                v-if="(connected || hasSides) && allBar"
                role="toolbar"
                :aria-label="$t('All the pages')"
                class="flex shrink-0 items-center gap-2 border-b border-edge-soft bg-card px-8 py-2.5"
            >
                <span class="mr-1 text-label text-ink-soft">{{
                    $t('On all the pages')
                }}</span>
                <span class="grow" />
                <FmButton
                    v-if="scanner.loose.value.length > 1"
                    variant="outline"
                    compact
                    :icon="IdCard"
                    :disabled="scanner.sending.value"
                    @click="scanner.gatherLoose"
                    >{{
                        $t('Put on one page (:count)', {
                            count: String(scanner.loose.value.length),
                        })
                    }}</FmButton
                >
                <FmButton
                    v-if="scanner.crooked.value.length > 0"
                    variant="outline"
                    compact
                    :icon="Wand"
                    :disabled="scanner.sending.value"
                    @click="scanner.straightenAll"
                    >{{ crookedText }}</FmButton
                >
                <FmButton
                    variant="outline"
                    compact
                    :icon="RotateCw"
                    :disabled="scanner.sending.value"
                    @click="scanner.rotateAll"
                    >{{ $t('Rotate all') }}</FmButton
                >
                <FmButton
                    v-if="scanner.chosen.value && scanner.kept.value.length > 1"
                    variant="outline"
                    compact
                    :icon="Copy"
                    aria-describedby="copy-improve-hint"
                    :disabled="scanner.sending.value"
                    @click="scanner.improveAll(scanner.chosen.value.id)"
                    >{{ $t('Do the same to all the pages') }}</FmButton
                >
                <span id="copy-improve-hint" class="sr-only">{{
                    $t(
                        'Copies the light and the ink and Black and white of the chosen page to all the other pages; Straighten stays with each page',
                    )
                }}</span>
            </div>

            <div
                v-if="connected || hasSides"
                class="flex min-h-0 grow flex-row items-stretch"
            >
                <div
                    v-if="
                        scanner.kept.value.length > 0 ||
                        scanner.aside.value.length > 0
                    "
                    class="flex w-[150px] shrink-0 flex-col items-center gap-3 border-r border-edge-soft py-6"
                >
                    <FmPageStrip
                        v-if="scanner.kept.value.length > 0"
                        orientation="column"
                        :pages="stripPages"
                        :selected="scanner.chosenId.value"
                        @select="scanner.choose"
                        @move="scanner.move"
                        @add="scanner.startScan"
                    />
                    <ScannerAsideSides
                        v-if="scanner.aside.value.length > 0"
                        class="mt-auto"
                        :sides="scanner.aside.value"
                        :disabled="scanner.sending.value"
                        @put-back="scanner.putBack"
                    />
                </div>

                <main
                    class="flex min-w-0 grow flex-col items-center justify-center-safe gap-3.5 overflow-y-auto p-8"
                >
                    <div
                        v-if="!scanner.chosen.value"
                        class="flex h-[580px] w-[420px] shrink-0 flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed border-edge p-6 text-center"
                    >
                        <FmIcon
                            :icon="FileText"
                            :size="36"
                            class="text-ink-soft"
                        />
                        <span class="text-stat">{{ $t('No pages') }}</span>
                        <span class="text-body-sm text-ink-soft">{{
                            $t(
                                'Put the sheets in the feeder of the scanner and press Start scanning: they all go through, front and back.',
                            )
                        }}</span>
                    </div>

                    <template v-else>
                        <div
                            data-preview
                            class="relative flex h-[580px] w-[420px] shrink-0 items-center justify-center"
                        >
                            <div class="relative">
                                <img
                                    v-if="scanner.chosen.value.url"
                                    :src="scanner.chosen.value.url"
                                    :alt="
                                        $t('Page :number', {
                                            number: String(
                                                scanner.kept.value.indexOf(
                                                    scanner.chosen.value,
                                                ) + 1,
                                            ),
                                        })
                                    "
                                    :style="previewStyle(scanner.chosen.value)"
                                    class="block rounded-card border border-edge-soft bg-card object-contain shadow-float"
                                />
                                <div
                                    v-if="grid && !scanner.chosen.value.parts"
                                    aria-hidden="true"
                                    data-grid
                                    class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--color-action)_1px,transparent_1px),linear-gradient(to_bottom,var(--color-action)_1px,transparent_1px)] bg-[size:10%_10%] opacity-40"
                                />
                                <template v-if="scanner.chosen.value.parts">
                                    <button
                                        v-for="(spot, index) in scanner.spotsOf(
                                            scanner.chosen.value,
                                        )"
                                        :key="index"
                                        type="button"
                                        :aria-label="
                                            $t(
                                                'Correct the cut of side :number',
                                                {
                                                    number: String(index + 1),
                                                },
                                            )
                                        "
                                        class="absolute cursor-pointer rounded-tag border-2 border-dashed border-action/60 bg-transparent hover:bg-action/10"
                                        :style="spot"
                                        :disabled="scanner.sending.value"
                                        @click="
                                            cropping = {
                                                sheet: scanner.chosen.value.id,
                                                index,
                                            }
                                        "
                                    >
                                        <span
                                            class="absolute top-1 left-1 rounded-tag bg-action px-1.5 text-badge text-on-action"
                                            >{{ index + 1 }}</span
                                        >
                                    </button>
                                </template>
                            </div>
                            <span
                                v-if="scanner.chosen.value.working"
                                class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-tag bg-camera-bg px-2.5 py-1 text-caption whitespace-nowrap text-on-action"
                                >{{ $t('Making the page…') }}</span
                            >
                        </div>

                        <div class="flex gap-2.5">
                            <FmButton
                                v-if="!scanner.chosen.value.parts"
                                variant="outline"
                                :icon="RotateCw"
                                :disabled="scanner.sending.value"
                                @click="scanner.rotate(scanner.chosen.value.id)"
                                >{{ $t('Rotate') }}</FmButton
                            >
                            <FmTooltip
                                :text="
                                    $t(
                                        'The page leaves the PDF and goes among the «Put aside», in the bar on the left: from there you can put it back.',
                                    )
                                "
                                side="top"
                            >
                                <FmButton
                                    variant="neutral"
                                    :icon="FileMinus"
                                    :label="$t('Remove this page')"
                                    class="border! border-edge-soft! bg-card! hover:bg-canvas!"
                                    :disabled="scanner.sending.value"
                                    @click="
                                        scanner.remove(scanner.chosen.value.id)
                                    "
                                />
                            </FmTooltip>
                            <FmTooltip
                                v-if="!scanner.chosen.value.parts"
                                :text="
                                    $t(
                                        'Reference lines: a grid over the page, to see whether it is straight while you straighten it. It does not end up in the PDF.',
                                    )
                                "
                                side="top"
                            >
                                <FmButton
                                    variant="neutral"
                                    :icon="Grid3x3"
                                    :label="$t('Reference lines')"
                                    :aria-pressed="grid"
                                    class="aria-pressed:bg-action-soft aria-pressed:text-action"
                                    @click="grid = !grid"
                                />
                            </FmTooltip>
                        </div>
                    </template>
                </main>

                <aside
                    :aria-label="$t('The choices')"
                    class="flex min-h-0 w-[380px] min-w-[300px] shrink flex-col border-l border-edge-soft bg-card"
                >
                    <div
                        class="flex min-h-0 grow flex-col gap-[18px] overflow-y-auto p-7"
                    >
                        <FmNotice v-if="connected && outdated" tone="warning">
                            {{
                                $t(
                                    'The agent installed is version :installed: there is :latest. The page works all the same.',
                                    {
                                        installed:
                                            scanner.status.value?.version ?? '',
                                        latest: release?.version ?? '',
                                    },
                                )
                            }}
                            <template
                                v-if="scanner.status.value?.version === '1.0.0'"
                            >
                                {{
                                    $t(
                                        'Version 1.0.0 does not update itself: download the new version, choose Quit from the icon of the agent and open the new one. It is the last time it has to be done by hand.',
                                    )
                                }}
                            </template>
                            <template v-else>
                                {{
                                    $t(
                                        'It updates itself: it downloads the new version and installs it when it closes, so you find it the next time it opens. There is nothing to do.',
                                    )
                                }}
                            </template>
                            <template
                                v-if="
                                    scanner.status.value?.version === '1.0.0' &&
                                    system !== 'other'
                                "
                                #action
                            >
                                <FmButton
                                    variant="text"
                                    download
                                    :href="agentDownload(system).url"
                                    >{{ $t('Download') }}</FmButton
                                >
                            </template>
                        </FmNotice>

                        <ScannerSettings
                            v-if="choices && !hasSides"
                            :scanner="scanner"
                        />

                        <TabsRoot
                            v-else-if="hasSides"
                            v-model="tab"
                            class="flex flex-col gap-[18px]"
                        >
                            <TabsList
                                v-if="choices"
                                :aria-label="$t('The choices')"
                                class="flex h-control shrink-0 gap-1 rounded-control bg-segment p-1"
                            >
                                <TabsTrigger
                                    v-for="choice in [
                                        {
                                            value: 'page',
                                            label: $t('This page'),
                                        },
                                        {
                                            value: 'scanner',
                                            label: $t('Scanner'),
                                        },
                                    ]"
                                    :key="choice.value"
                                    :value="choice.value"
                                    class="flex-1 cursor-pointer rounded-[7px] border-0 bg-transparent px-2 text-body-sm text-ink-soft focus-visible:outline-2 focus-visible:outline-action data-[state=active]:bg-card data-[state=active]:text-label data-[state=active]:text-ink data-[state=active]:shadow-raised"
                                    >{{ choice.label }}</TabsTrigger
                                >
                            </TabsList>
                            <TabsContent
                                value="page"
                                class="flex flex-col gap-[18px]"
                            >
                                <template v-if="scanner.chosen.value">
                                    <ScannerSheetParts
                                        v-if="scanner.chosen.value.parts"
                                        :sizes="
                                            scanner.chosen.value.parts.map(
                                                scanner.partSize,
                                            )
                                        "
                                        :shrink="
                                            scanner.shrinkOf(
                                                scanner.chosen.value,
                                            )
                                        "
                                        :round="
                                            scanner.chosen.value.parts.every(
                                                (part) => part.round,
                                            )
                                        "
                                        :disabled="scanner.sending.value"
                                        @crop="
                                            (index) =>
                                                (cropping = {
                                                    sheet: scanner.chosen.value!
                                                        .id,
                                                    index,
                                                })
                                        "
                                        @turn="
                                            (index) =>
                                                scanner.turnPart(
                                                    scanner.chosen.value!.id,
                                                    index,
                                                )
                                        "
                                        @move="
                                            (index, step) =>
                                                scanner.movePart(
                                                    scanner.chosen.value!.id,
                                                    index,
                                                    step,
                                                )
                                        "
                                        @drop="
                                            (index) =>
                                                scanner.dropPart(
                                                    scanner.chosen.value!.id,
                                                    index,
                                                )
                                        "
                                        @round="
                                            (on) =>
                                                scanner.setRound(
                                                    scanner.chosen.value!.id,
                                                    on,
                                                )
                                        "
                                        @separate="
                                            scanner.separate(
                                                scanner.chosen.value.id,
                                            )
                                        "
                                    />

                                    <ScannerSideTools
                                        :improvement="
                                            scanner.chosen.value.improvement
                                        "
                                        :skew="scanner.chosen.value.skew"
                                        :composed="
                                            scanner.chosen.value.parts !== null
                                        "
                                        :disabled="scanner.sending.value"
                                        @angle="
                                            (degrees) =>
                                                scanner.setAngle(
                                                    scanner.chosen.value!.id,
                                                    degrees,
                                                )
                                        "
                                        @find="
                                            scanner.findAngle(
                                                scanner.chosen.value.id,
                                            )
                                        "
                                        @enhance="
                                            (value) =>
                                                scanner.setEnhance(
                                                    scanner.chosen.value!.id,
                                                    value,
                                                )
                                        "
                                        @grey="
                                            (on) =>
                                                scanner.setGrey(
                                                    scanner.chosen.value!.id,
                                                    on,
                                                )
                                        "
                                    />
                                </template>
                            </TabsContent>
                            <TabsContent v-if="choices" value="scanner">
                                <ScannerSettings :scanner="scanner" next />
                            </TabsContent>
                        </TabsRoot>

                        <template v-if="hasSides">
                            <h2
                                class="m-0 border-t border-edge-soft pt-[18px] text-amount"
                            >
                                {{ $t('Import options') }}
                            </h2>
                            <FormKit
                                :classes="{ outer: 'mb-0!' }"
                                v-model="name"
                                type="text"
                                name="name"
                                :label="$t('Name of the document')"
                                :placeholder="
                                    $t('Optional: for example «home insurance»')
                                "
                                maxlength="200"
                                :disabled="scanner.sending.value"
                            />
                            <div
                                v-if="scanner.kept.value.length > 1"
                                class="border-t border-edge-soft pt-[18px]"
                            >
                                <FormKit
                                    :classes="{ outer: 'mb-0!' }"
                                    v-model="scanner.onePerSide.value"
                                    type="toggle"
                                    name="one_per_side"
                                    :label="$t('One PDF per side')"
                                    :help="
                                        $t(
                                            'Each side becomes a document of its own: the AI reads each one, one reading per side.',
                                        )
                                    "
                                    :disabled="scanner.sending.value"
                                />
                            </div>
                        </template>
                    </div>

                    <div
                        class="flex shrink-0 flex-col gap-3 border-t border-edge-soft px-7 pt-4 pb-7"
                    >
                        <span
                            v-if="scanner.kept.value.length > 0"
                            class="text-body-sm text-ink-soft"
                            data-weight
                            >{{ weightText }}</span
                        >

                        <span
                            v-if="showState"
                            class="flex items-center gap-2 text-body-sm text-ink-soft"
                            role="status"
                        >
                            <FmIcon :icon="stateIcon" :size="18" />
                            {{ stateText }}
                        </span>

                        <FmNotice
                            v-if="scanner.failure.value"
                            :tone="
                                scanner.failure.value.code === 'empty'
                                    ? 'info'
                                    : 'warning'
                            "
                        >
                            {{ FAILURES[scanner.failure.value.code]?.() }}
                            <template
                                v-if="
                                    scanner.failure.value.code === 'scanner' &&
                                    scanner.failure.value.message
                                "
                            >
                                ({{ scanner.failure.value.message }})
                            </template>
                            <template
                                v-if="
                                    scanner.failure.value.code !== 'empty' &&
                                    hasSides
                                "
                            >
                                {{
                                    $t(
                                        'The sides already scanned are still here.',
                                    )
                                }}
                            </template>
                        </FmNotice>

                        <FmNotice v-if="scanner.tooLarge.value" tone="warning">
                            {{
                                scanner.split.value
                                    ? $t(
                                          'A side weighs :size: over :most the file cannot be read. Take that side away, or make it black and white.',
                                          {
                                              size: formatSize(
                                                  scanner.heaviest.value,
                                              ),
                                              most: formatSize(
                                                  MAX_UPLOAD_BYTES,
                                              ),
                                          },
                                      )
                                    : $t(
                                          'The sides weigh :size: over :most the file cannot be read. Take some sides away, upload the document in two goes or make a PDF per side.',
                                          {
                                              size: formatSize(
                                                  scanner.weight.value,
                                              ),
                                              most: formatSize(
                                                  MAX_UPLOAD_BYTES,
                                              ),
                                          },
                                      )
                            }}
                        </FmNotice>
                        <FmNotice
                            v-if="scanner.improveError.value"
                            tone="warning"
                        >
                            {{ scanner.improveError.value }}
                        </FmNotice>

                        <ScanStatus v-bind="status" @retry="upload" />

                        <FmButton
                            v-if="connected"
                            wide
                            :variant="
                                scanner.kept.value.length > 0
                                    ? 'outline'
                                    : 'solid'
                            "
                            :icon="ScanLine"
                            :disabled="
                                !scanner.canScan.value || scanner.scanning.value
                            "
                            @click="scanner.startScan"
                            >{{ scanLabel }}</FmButton
                        >

                        <FmButton
                            v-if="hasSides"
                            wide
                            :icon="Upload"
                            :disabled="
                                scanner.kept.value.length === 0 ||
                                scanner.tooLarge.value ||
                                scanner.scanning.value ||
                                scanner.working.value ||
                                scanner.sending.value
                            "
                            @click="asking = true"
                            >{{ $t('Upload') }}</FmButton
                        >
                    </div>
                </aside>
            </div>
        </template>
    </div>

    <ScannerCardCrop
        v-if="cropped"
        v-model:open="croppingOpen"
        :image="cropped.part.from.original"
        :width="cropped.part.from.width"
        :height="cropped.part.from.height"
        :millimetre="cropped.part.from.millimetre"
        :found="cropped.part.from.found"
        :cut="cropped.part.cut"
        :round="cropped.part.round"
        @done="(cut) => scanner.setCut(cropping!.sheet, cropping!.index, cut)"
    />

    <FmConfirm
        v-model:open="asking"
        :question="question"
        :text="details"
        :confirm-label="$t('Upload')"
        confirm-variant="solid"
        @confirm="confirmUpload"
    />
</template>
