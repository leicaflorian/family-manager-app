<script setup lang="ts">
/**
 * *Scan the sheets*: the scanner of the house, driven from the computer through its agent. It has no board of its own:
 * it takes the layout of `Scansione-pc` (the sides in a column on the left, the side in large in the middle with
 * *Rotate* and *Improve*, the choices on the right). Put the sheets in the feeder and press *Scan*: they all go
 * through, front and back, and the blank sides are put aside by themselves. *Upload* makes one PDF of the sides that
 * stay, in the order of the strip, sends it to *To sort* as scanned and goes back there. A phone, a tablet or a
 * Linux computer is told that the scanner is used from a Mac or a PC. Sides not uploaded yet are changes not saved:
 * the app asks before leaving them.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    FileText,
    Hourglass,
    PowerOff,
    RotateCw,
    ScanLine,
    Trash2,
    Undo2,
    Upload,
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
import { useScanner } from '@/composables/useScanner';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { useWideLayout } from '@/composables/useWideLayout';
import { desktopSystem } from '@/lib/devices';
import type { Colour, ScannerState, Source } from '@/lib/scan/agent';
import { colourName, sidesText, sourceName } from '@/lib/scan/choices';
import { cn } from '@/lib/utils';
import { toSort } from '@/routes';

const { agent_release: release } = defineProps<{
    /** The last version of the agent: not there until the server has asked GitHub, `null` when it did not answer. */
    agent_release?: App.Data.AgentReleaseData | null;
}>();

const wide = useWideLayout();
const scanner = useScanner();
const system = desktopSystem();
const origin = window.location.origin;
const name = ref('');

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

const stripPages = computed((): StripPage[] =>
    scanner.kept.value.map((side) => ({ id: side.id, url: side.url })),
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

    return scanner.scannerState.value === 'ready' &&
        scanner.source.value === 'flatbed'
        ? trans('Ready: put the sheet on the glass')
        : STATES[scanner.scannerState.value]();
});
const scanLabel = computed((): string => {
    if (scanner.sides.value.length === 0) return trans('Start scanning');

    return scanner.source.value === 'flatbed'
        ? trans('Add a sheet')
        : trans('Add sheets');
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
const sheetHelp = computed((): string =>
    scanner.oneSheetUsable.value
        ? trans('Front and back of a card, on one sheet')
        : trans('It takes two to four sides'),
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
    canRetry: scanner.uploadError.value !== null && !scanner.sending.value,
    done: null,
    sending: scanner.sending.value,
    progress: scanner.progress.value,
    progressText: scanner.progressText.value,
}));

// Sides not uploaded yet are lost if the page changes: the app asks first
const changes = useUnsavedChanges(() => ({
    sides: scanner.kept.value.map((side) => side.id),
    name: name.value,
}));

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
        <h1 class="m-0 font-bold" :class="wide ? 'text-lg' : 'text-[17px]'">
            {{ $t('Scan the sheets') }}
        </h1>
        <span class="justify-self-end text-[15px] text-ink-soft">{{
            count
        }}</span>
    </header>

    <ScannerAgent
        v-if="scanner.phase.value !== 'connected'"
        :phase="scanner.phase.value"
        :system="system"
        :origin="origin"
        :release="release"
        :blocked="scanner.blocked.value"
        :has-sides="scanner.sides.value.length > 0"
        @retry="scanner.connect(true)"
    />

    <div
        v-else
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
                v-if="!scanner.chosen.value"
                :class="
                    cn(
                        'flex shrink-0 flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed border-edge p-6 text-center',
                        wide ? 'h-[580px] w-[420px]' : 'h-[340px] w-[250px]',
                    )
                "
            >
                <FmIcon :icon="FileText" :size="36" class="text-ink-soft" />
                <span class="text-base font-bold">{{ $t('No pages') }}</span>
                <span class="text-sm text-ink-soft">{{
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
                    <img
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
                        class="max-h-full max-w-full rounded-card border border-edge-soft bg-card object-contain shadow-float"
                    />
                    <span
                        v-if="scanner.chosen.value.working"
                        class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-tag bg-camera-bg px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-on-action"
                        >{{ $t('Making the page…') }}</span
                    >
                </div>

                <div class="flex gap-2.5">
                    <FmButton
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

                <fieldset
                    class="m-0 flex w-full max-w-[420px] flex-col gap-2 rounded-card border border-edge-soft bg-card p-4"
                >
                    <legend class="px-1 text-sm font-bold">
                        {{ $t('Improve') }}
                    </legend>
                    <FormKit
                        type="toggle"
                        name="enhance"
                        :model-value="scanner.chosen.value.enhance"
                        :label="$t('Improve the light and the ink')"
                        :disabled="scanner.sending.value"
                        @update:model-value="
                            (value: unknown) =>
                                scanner.improve(scanner.chosen.value!.id, {
                                    enhance: value === true,
                                })
                        "
                    />
                    <FormKit
                        type="toggle"
                        name="grey"
                        :model-value="scanner.chosen.value.grey"
                        :label="$t('Black and white')"
                        :disabled="scanner.sending.value"
                        @update:model-value="
                            (value: unknown) =>
                                scanner.improve(scanner.chosen.value!.id, {
                                    grey: value === true,
                                })
                        "
                    />
                    <FmButton
                        v-if="scanner.kept.value.length > 1"
                        variant="text"
                        :disabled="scanner.sending.value"
                        @click="scanner.improveAll(scanner.chosen.value.id)"
                        >{{ $t('Do the same to all the pages') }}</FmButton
                    >
                </fieldset>
            </template>

            <section
                v-if="scanner.aside.value.length > 0"
                class="flex w-full max-w-[420px] flex-col gap-2"
            >
                <h2 class="m-0 text-sm font-bold text-ink-soft">
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
                        <span class="text-xs text-ink-soft">{{
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
            <FmNotice v-if="outdated" tone="warning">
                {{
                    $t(
                        'The agent installed is version :installed: there is :latest. The page works all the same.',
                        {
                            installed: scanner.status.value?.version ?? '',
                            latest: release?.version ?? '',
                        },
                    )
                }}
            </FmNotice>

            <template v-if="scanner.choosing.value">
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

                <!-- the key: a menu shows the label of its value only for the options it was made with -->
                <FormKit
                    :key="`scanner-${scanner.scannerOptions.value.map((one) => one.value).join()}`"
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
                        :key="`resolution-${scanner.resolutionOptions.value.map((one) => one.value).join()}`"
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
                        :help="sheetHelp"
                        :model-value="scanner.oneSheet.value"
                        :disabled="scanner.scanning.value"
                        @update:model-value="
                            (value: unknown) =>
                                scanner.setOneSheet(value === true)
                        "
                    />
                </template>
            </template>

            <FormKit
                v-if="scanner.sides.value.length > 0"
                v-model="name"
                type="text"
                name="name"
                :label="$t('Name of the document')"
                :placeholder="$t('Optional: for example «home insurance»')"
                maxlength="200"
                :disabled="scanner.sending.value"
            />

            <span class="grow" />

            <span
                class="flex items-center gap-2 text-sm text-ink-soft"
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
                    v-if="
                        scanner.failure.value.code !== 'empty' &&
                        scanner.sides.value.length > 0
                    "
                >
                    {{ $t('The sides already scanned are still here.') }}
                </template>
            </FmNotice>

            <FmButton
                wide
                :variant="scanner.kept.value.length > 0 ? 'outline' : 'solid'"
                :icon="ScanLine"
                :disabled="!scanner.canScan.value || scanner.scanning.value"
                @click="scanner.startScan"
                >{{ scanLabel }}</FmButton
            >

            <ScanStatus v-bind="status" @retry="upload" />

            <FmButton
                v-if="scanner.sides.value.length > 0"
                wide
                :icon="Upload"
                :disabled="
                    scanner.kept.value.length === 0 ||
                    scanner.scanning.value ||
                    scanner.working.value ||
                    scanner.sending.value
                "
                @click="upload"
                >{{ $t('Upload') }}</FmButton
            >
        </aside>
    </div>
</template>
