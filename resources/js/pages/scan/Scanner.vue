<script setup lang="ts">
/**
 * *Scan the sheets*: the scanner of the house, driven from the computer through its agent. It has no board of its own:
 * it takes the layout of `Scansione-pc` (the sides in a column on the left, the side in large in the middle with
 * *Rotate* and *Improve*, the choices on the right). Put the sheets in the feeder and press *Scan*: they all go
 * through, front and back, and the blank sides are put aside by themselves. A side that went in crooked is
 * straightened by a few degrees; with *On a single page* the cards are cut and put at their real size on one A4.
 * *Upload* makes one PDF of the sides that stay, in the order of the strip (or one per side), sends it to *To sort*
 * as scanned and goes back there. The sides stay, and can be uploaded, also when the agent closes. A phone, a tablet
 * or a Linux computer is told that the scanner is used from a Mac or a PC. Sides not uploaded yet are changes not
 * saved: the app asks before leaving them.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    FileText,
    Hourglass,
    IdCard,
    PowerOff,
    RotateCw,
    ScanLine,
    Trash2,
    Undo2,
    Upload,
    Wand,
} from '@lucide/vue';
import { transChoice, trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { StripPage } from '@/components/fm/FmPageStrip.vue';
import FmPageStrip from '@/components/fm/FmPageStrip.vue';
import ScanStatus from '@/components/scan/ScanStatus.vue';
import ScannerAgent from '@/components/scan/ScannerAgent.vue';
import ScannerCardCrop from '@/components/scan/ScannerCardCrop.vue';
import ScannerSheetParts from '@/components/scan/ScannerSheetParts.vue';
import ScannerSideTools from '@/components/scan/ScannerSideTools.vue';
import type { Side } from '@/composables/useScanner';
import { useScanner } from '@/composables/useScanner';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { useWideLayout } from '@/composables/useWideLayout';
import { desktopSystem } from '@/lib/devices';
import { formatSize } from '@/lib/format';
import type { Colour, Phase, ScannerState, Source } from '@/lib/scan/agent';
import { colourName, sidesText, sourceName } from '@/lib/scan/choices';
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
/** The card of a composed page whose cut is being corrected: the page and the place of the card on it. */
const cropping = ref<{ sheet: string; index: number } | null>(null);

/** The size of the box of the side in large, in pixels. */
const frame = computed(() =>
    wide.value ? { width: 420, height: 580 } : { width: 250, height: 340 },
);

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
        return cards ? trans('Add a card') : trans('Scan the card');
    }

    if (!hasSides.value) return trans('Start scanning');

    return fromGlass.value ? trans('Add a sheet') : trans('Add sheets');
});
const fitText = computed((): string | undefined =>
    scanner.fit.value ? sidesText(scanner.fit.value) : undefined,
);
const colourOptions = computed(() =>
    scanner.colours.value.map((value: Colour) => ({
        label: colourName(value),
        value,
    })),
);
const sourceOptions = computed(() =>
    scanner.sources.value.map((value: Source) => ({
        label: sourceName(value),
        value,
    })),
);
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
    const { width, height } = frame.value;

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
            class="justify-self-start"
            variant="text"
            data-discard-changes
            :href="toSort().url"
            >{{ $t('Cancel') }}</FmButton
        >
        <h1 class="m-0" :class="wide ? 'text-subtitle' : 'text-section'">
            {{ $t('Scan the sheets') }}
        </h1>
        <span class="justify-self-end text-body-sm text-ink-soft">{{
            count
        }}</span>
    </header>

    <ScannerAgent
        v-if="agentPhase"
        :phase="agentPhase"
        :system="system"
        :origin="origin"
        :release="release"
        :blocked="scanner.blocked.value"
        :has-sides="hasSides"
        @retry="retry"
    />

    <div
        v-if="connected || hasSides"
        :class="
            cn(
                'flex min-h-0 grow',
                wide ? 'flex-row items-stretch' : 'flex-col',
            )
        "
    >
        <FmPageStrip
            v-if="scanner.kept.value.length > 0"
            :class="wide ? 'w-[150px] shrink-0 py-6' : 'justify-center'"
            :orientation="wide ? 'column' : 'row'"
            :pages="stripPages"
            :selected="scanner.chosenId.value"
            @select="scanner.choose"
            @move="scanner.move"
            @add="scanner.startScan"
        />

        <main
            :class="
                cn(
                    'flex min-w-0 grow flex-col items-center gap-3.5',
                    wide ? 'justify-center p-8' : 'px-5 pt-3 pb-4',
                )
            "
        >
            <div
                v-if="scanner.kept.value.length > 0"
                class="flex flex-wrap justify-center gap-2"
            >
                <FmButton
                    v-if="scanner.loose.value.length > 1"
                    variant="outline"
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
                    :icon="Wand"
                    :disabled="scanner.sending.value"
                    @click="scanner.straightenAll"
                    >{{ crookedText }}</FmButton
                >
                <FmButton
                    variant="outline"
                    :icon="RotateCw"
                    :disabled="scanner.sending.value"
                    @click="scanner.rotateAll"
                    >{{ $t('Rotate all') }}</FmButton
                >
            </div>

            <div
                v-if="!scanner.chosen.value"
                :class="
                    cn(
                        'flex shrink-0 flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed border-edge p-6 text-center',
                        wide ? 'h-[580px] w-[420px]' : 'h-[340px] w-[250px]',
                    )
                "
            >
                <FmIcon :icon="FileText" :size="36" class="text-ink-soft" />
                <span class="text-amount">{{ $t('No pages') }}</span>
                <span class="text-body-sm text-ink-soft">{{
                    $t(
                        'Put the sheets in the feeder of the scanner and press Start scanning: they all go through, front and back.',
                    )
                }}</span>
            </div>

            <template v-else>
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
                                    $t('Correct the cut of side :number', {
                                        number: String(index + 1),
                                    })
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
                    <FmButton
                        variant="outline"
                        :icon="Trash2"
                        :label="$t('Remove this page')"
                        :disabled="scanner.sending.value"
                        @click="scanner.remove(scanner.chosen.value.id)"
                    />
                </div>

                <ScannerSheetParts
                    v-if="scanner.chosen.value.parts"
                    :sizes="scanner.chosen.value.parts.map(scanner.partSize)"
                    :shrink="scanner.shrinkOf(scanner.chosen.value)"
                    :round="
                        scanner.chosen.value.parts.every((part) => part.round)
                    "
                    :disabled="scanner.sending.value"
                    @crop="
                        (index) =>
                            (cropping = {
                                sheet: scanner.chosen.value!.id,
                                index,
                            })
                    "
                    @turn="
                        (index) =>
                            scanner.turnPart(scanner.chosen.value!.id, index)
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
                            scanner.dropPart(scanner.chosen.value!.id, index)
                    "
                    @round="
                        (on) => scanner.setRound(scanner.chosen.value!.id, on)
                    "
                    @separate="scanner.separate(scanner.chosen.value.id)"
                />

                <ScannerSideTools
                    v-model:grid="grid"
                    :improvement="scanner.chosen.value.improvement"
                    :skew="scanner.chosen.value.skew"
                    :composed="scanner.chosen.value.parts !== null"
                    :many="scanner.kept.value.length > 1"
                    :disabled="scanner.sending.value"
                    @angle="
                        (degrees) =>
                            scanner.setAngle(scanner.chosen.value!.id, degrees)
                    "
                    @find="scanner.findAngle(scanner.chosen.value.id)"
                    @enhance="
                        (value) =>
                            scanner.setEnhance(scanner.chosen.value!.id, value)
                    "
                    @grey="
                        (on) => scanner.setGrey(scanner.chosen.value!.id, on)
                    "
                    @all="scanner.improveAll(scanner.chosen.value.id)"
                />
            </template>

            <section
                v-if="scanner.aside.value.length > 0"
                class="flex w-full max-w-[420px] flex-col gap-2"
            >
                <h2 class="m-0 text-label text-ink-soft">
                    {{ $t('Sides put aside: they do not go in the PDF') }}
                </h2>
                <ul class="m-0 flex list-none flex-wrap gap-3 p-0">
                    <li
                        v-for="side in scanner.aside.value"
                        :key="side.id"
                        class="flex w-[88px] flex-col items-center gap-1.5"
                    >
                        <img
                            :src="side.url"
                            :alt="$t('Side put aside')"
                            class="h-[110px] w-[78px] rounded-tag border border-edge-soft bg-card object-contain"
                        />
                        <span class="text-caption text-ink-soft">{{
                            side.aside === 'blank'
                                ? $t('blank')
                                : $t('taken away')
                        }}</span>
                        <FmButton
                            variant="text"
                            :icon="Undo2"
                            :disabled="scanner.sending.value"
                            @click="scanner.putBack(side.id)"
                            >{{ $t('Put back') }}</FmButton
                        >
                    </li>
                </ul>
            </section>
        </main>

        <aside
            :class="
                cn(
                    'flex flex-col gap-[18px] bg-card p-7',
                    wide
                        ? 'w-[380px] min-w-[300px] shrink border-l border-edge-soft'
                        : 'border-t border-edge-soft',
                )
            "
        >
            <FmNotice v-if="connected && outdated" tone="warning">
                {{
                    $t(
                        'The agent installed is version :installed: there is :latest. The page works all the same.',
                        {
                            installed: scanner.status.value?.version ?? '',
                            latest: release?.version ?? '',
                        },
                    )
                }}
                <template v-if="scanner.status.value?.version === '1.0.0'">
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

            <template v-if="connected && scanner.choosing.value">
                <FmNotice v-if="scanner.missingScanner.value" tone="warning">
                    {{
                        $t(
                            ':name cannot be seen: it is off or not on the network.',
                            {
                                name: scanner.missingScanner.value,
                            },
                        )
                    }}
                </FmNotice>

                <FormKit
                    type="dropdown"
                    name="scanner"
                    :label="$t('Scanner')"
                    :model-value="scanner.scannerId.value"
                    :options="scanner.scannerOptions.value"
                    :placeholder="
                        scanner.scanners.value.length
                            ? $t('Choose the scanner')
                            : $t('No scanner')
                    "
                    :disabled="
                        scanner.scanning.value ||
                        scanner.scanners.value.length === 0
                    "
                    @update:model-value="
                        (value: unknown) =>
                            scanner.chooseScanner(
                                typeof value === 'string' ? value : null,
                            )
                    "
                />
                <template v-if="scanner.ready.value">
                    <FormKit
                        v-if="scanner.resolutionOptions.value.length > 0"
                        type="dropdown"
                        name="resolution"
                        :label="$t('Resolution')"
                        :model-value="scanner.resolution.value"
                        :options="scanner.resolutionOptions.value"
                        :help="fitText"
                        :disabled="scanner.scanning.value"
                        @update:model-value="
                            (value: unknown) =>
                                (scanner.resolution.value =
                                    typeof value === 'number' ? value : null)
                        "
                    />
                    <FormKit
                        type="togglebuttons"
                        enforced
                        name="colour"
                        :label="$t('Colour')"
                        :model-value="scanner.colour.value"
                        :options="colourOptions"
                        :disabled="scanner.scanning.value"
                        @update:model-value="
                            (value: unknown) =>
                                (scanner.colour.value = value as Colour)
                        "
                    />
                    <FormKit
                        v-if="scanner.sources.value.length > 1"
                        type="togglebuttons"
                        enforced
                        name="source"
                        :label="$t('Where from')"
                        :model-value="scanner.source.value"
                        :options="sourceOptions"
                        :disabled="scanner.scanning.value"
                        @update:model-value="
                            (value: unknown) =>
                                scanner.chooseSource(value as Source)
                        "
                    />
                    <FormKit
                        type="toggle"
                        name="one_sheet"
                        :label="$t('On a single page')"
                        :help="
                            $t(
                                'Front and back of a card on one page, in colour and at high resolution',
                            )
                        "
                        :model-value="scanner.oneSheet.value"
                        :disabled="scanner.scanning.value"
                        @update:model-value="
                            (value: unknown) =>
                                scanner.setOneSheet(value === true)
                        "
                    />
                </template>
            </template>

            <template v-if="hasSides">
                <FormKit
                    v-model="name"
                    type="text"
                    name="name"
                    :label="$t('Name of the document')"
                    :placeholder="$t('Optional: for example «home insurance»')"
                    maxlength="200"
                    :disabled="scanner.sending.value"
                />
                <FormKit
                    v-if="scanner.kept.value.length > 1"
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
                <span
                    v-if="scanner.kept.value.length > 0"
                    class="text-body-sm text-ink-soft"
                    data-weight
                    >{{ weightText }}</span
                >
            </template>

            <span class="grow" />

            <span
                v-if="connected"
                class="flex items-center gap-2 text-body-sm text-ink-soft"
                role="status"
            >
                <FmIcon :icon="stateIcon" :size="18" />
                {{ stateText }}
            </span>

            <FmNotice
                v-if="scanner.failure.value"
                :tone="
                    scanner.failure.value.code === 'empty' ? 'info' : 'warning'
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
                    v-if="scanner.failure.value.code !== 'empty' && hasSides"
                >
                    {{ $t('The sides already scanned are still here.') }}
                </template>
            </FmNotice>

            <FmButton
                v-if="connected"
                wide
                :variant="scanner.kept.value.length > 0 ? 'outline' : 'solid'"
                :icon="ScanLine"
                :disabled="!scanner.canScan.value || scanner.scanning.value"
                @click="scanner.startScan"
                >{{ scanLabel }}</FmButton
            >

            <FmNotice v-if="scanner.tooLarge.value" tone="warning">
                {{
                    scanner.split.value
                        ? $t(
                              'A side weighs :size: over :most the file cannot be read. Take that side away, or make it black and white.',
                              {
                                  size: formatSize(scanner.heaviest.value),
                                  most: formatSize(MAX_UPLOAD_BYTES),
                              },
                          )
                        : $t(
                              'The sides weigh :size: over :most the file cannot be read. Take some sides away, upload the document in two goes or make a PDF per side.',
                              {
                                  size: formatSize(scanner.weight.value),
                                  most: formatSize(MAX_UPLOAD_BYTES),
                              },
                          )
                }}
            </FmNotice>
            <FmNotice v-if="scanner.improveError.value" tone="warning">
                {{ scanner.improveError.value }}
            </FmNotice>

            <ScanStatus v-bind="status" @retry="upload" />

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
                @click="upload"
                >{{ $t('Upload') }}</FmButton
            >
        </aside>
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
</template>
