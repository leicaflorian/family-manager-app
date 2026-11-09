import { trans } from 'laravel-vue-i18n';
import type { ComputedRef, Ref } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { desktopSystem } from '@/lib/devices';
import * as agent from '@/lib/scan/agent';
import type {
    AgentPages,
    AgentStatus,
    Colour,
    Phase,
    ScanFailure,
    Scanner,
    ScannerState,
    Source,
} from '@/lib/scan/agent';
import { isBlank } from '@/lib/scan/blank';
import { remakeSide } from '@/lib/scan/canvas';
import {
    colourFor,
    readChoices,
    resolutionFor,
    resolutionsOf,
    scannerFor,
    scannerLabel,
    sidesThatFit,
    sourceFor,
    writeChoices,
} from '@/lib/scan/choices';
import type { Choices } from '@/lib/scan/choices';
import type { Turns } from '@/lib/scan/crop';
import { scannerFile } from '@/lib/scan/scannerFile';
import { ScanRequestError, sendScanFile } from '@/lib/scan/server';
import { ScanTooHeavyError, oneSheetState } from '@/lib/scan/pages';

/** How often the sides are asked for during a scan, in milliseconds. */
const PAGES_EVERY = 500;
/** How long the agent has to answer after the page asked the system to start it, in milliseconds. */
const LAUNCH_WAIT = 5000;
/** What "On a single page" asks of the scanner: enough dots to read the small print of a card. */
export const CARD_RESOLUTION = 300;

/** One side of a sheet, already downloaded from the agent: it stays in the page even when the agent quits. */
export type Side = {
    /** Identifies the side in the page. */
    id: string;
    /** The JPEG as it arrived from the scanner: every improvement starts from it. */
    original: Blob;
    /** What goes in the PDF: the original, or the one made again with the turns and improvements asked. */
    shown: Blob;
    /** The address of `shown`. */
    url: string;
    /** Quarters of a turn clockwise, given by hand. */
    turns: Turns;
    /** *Improve*: the light is evened out and the ink deepened. */
    enhance: boolean;
    /** *Black and white*. */
    grey: boolean;
    /** Why it is not among the sides of the PDF: found blank, or taken away by hand. */
    aside: 'blank' | 'removed' | null;
    /** `shown` is being made again. */
    working: boolean;
    /** What `shown` was made with, as text: it differs from what is asked while the side is being made again. */
    applied: string;
};

/** Why a scan stopped, or did not start: `settings` is the agent refusing what the scan was asked with. */
export type Failure = ScanFailure | 'settings';

/** What the scanner chosen has, kept from the last answer that said it: during a scan the agent does not say it. */
type Capabilities = {
    sources: Source[];
    resolutions: number[];
    colours: Colour[];
};

/** What `useScanner` gives to the page. */
export type UseScanner = {
    /** Where the page is with the agent. */
    phase: Ref<Phase>;
    /** The last answer of the agent. */
    status: Ref<AgentStatus | null>;
    /** How the scanner is. */
    scannerState: ComputedRef<ScannerState>;
    /** The agent knows the choices (api 2): the page shows them. */
    choosing: ComputedRef<boolean>;
    /** The scanners the computer sees. */
    scanners: Ref<Scanner[]>;
    /** The options of the scanner menu. */
    scannerOptions: ComputedRef<{ label: string; value: string }[]>;
    /** The scanner chosen. */
    scannerId: Ref<string | null>;
    /** The name of the scanner of the last scan when the computer does not see it now. */
    missingScanner: ComputedRef<string | null>;
    /** The scanner chosen has said what it can do. */
    ready: ComputedRef<boolean>;
    /** The resolution chosen, in dots per inch. */
    resolution: Ref<number | null>;
    /** The options of the resolution menu. */
    resolutionOptions: ComputedRef<{ label: string; value: number }[]>;
    /** About how many sides fit in the largest file, at the resolution chosen. */
    fit: ComputedRef<number | null>;
    /** The colour chosen. */
    colour: Ref<Colour>;
    /** The colours the scanner has. */
    colours: ComputedRef<Colour[]>;
    /** Where the sheets come from. */
    source: Ref<Source | null>;
    /** The places the sheets can come from. */
    sources: ComputedRef<Source[]>;
    /** "On a single page" is switched on. */
    oneSheet: Ref<boolean>;
    /** "On a single page" can be used: it takes two to four sides. */
    oneSheetUsable: ComputedRef<boolean>;
    /** A scan is running. */
    scanning: Ref<boolean>;
    /** Why the last scan stopped. */
    failure: Ref<{ code: Failure; message: string } | null>;
    /** A new scan can be started. */
    canScan: ComputedRef<boolean>;
    /** The agent installed is older than the last one published. */
    outdated: (latest: string | undefined) => boolean;
    /** Chrome was told not to let this site reach the programs of the computer. */
    blocked: Ref<boolean>;
    /** The sides, the ones that stay and the ones put aside, in the order of the agent. */
    sides: Ref<Side[]>;
    /** The sides that go in the PDF, in the order of the strip. */
    kept: ComputedRef<Side[]>;
    /** The sides put aside: blank, or taken away. */
    aside: ComputedRef<Side[]>;
    /** The id of the side shown in large. */
    chosenId: Ref<string | null>;
    /** The side shown in large. */
    chosen: ComputedRef<Side | null>;
    /** Some side is being made again. */
    working: ComputedRef<boolean>;
    /** The files are leaving. */
    sending: Ref<boolean>;
    /** How much has left, from 0 to 100. */
    progress: Ref<number>;
    /** «Uploading…». */
    progressText: Ref<string>;
    /** Why the upload stopped. */
    uploadError: Ref<string | null>;
    /** Looks for the agent, asking the system to start it when nobody answers. */
    connect: (allowLaunch: boolean) => Promise<void>;
    /** Starts a scan with what is chosen. */
    startScan: () => Promise<void>;
    /** The scanner was chosen by hand. */
    chooseScanner: (id: string | null) => void;
    /** The source was chosen by hand. */
    chooseSource: (value: Source) => void;
    /** "On a single page": in colour at 300 dots and from the glass when the scanner has it; off, back as it was. */
    setOneSheet: (on: boolean) => void;
    /** Chooses the side shown in large. */
    choose: (id: string) => void;
    /** Turns a side a quarter of a turn clockwise. */
    rotate: (id: string) => void;
    /** Switches *Improve* or *Black and white* of a side. */
    improve: (id: string, patch: { enhance?: boolean; grey?: boolean }) => void;
    /** Gives the improvements of a side to all the sides that stay. */
    improveAll: (id: string) => void;
    /** Takes a side out of the PDF. */
    remove: (id: string) => void;
    /** Puts a side that was aside back. */
    putBack: (id: string) => void;
    /** Moves a side among the ones that stay (positions in the strip). */
    move: (from: number, to: number) => void;
    /** Makes the PDF, sends it as the scanner's, empties the agent. True when it all went through. */
    upload: (name: string) => Promise<boolean>;
    /** Empties the temporary folder of the agent. */
    clear: () => Promise<void>;
};

/**
 * The scanner of the house, driven through its agent: finds the agent, follows its answers, keeps the choices of
 * the scan and the sides that come in, and sends the PDF. The page only shows it.
 *
 * @returns the state and the actions
 */
export function useScanner(): UseScanner {
    const system = desktopSystem();
    const phase = ref<Phase>('searching');
    const status = ref<AgentStatus | null>(null);
    const blocked = ref(false);
    const sides = ref<Side[]>([]);
    const chosenId = ref<string | null>(null);
    const scanning = ref(false);
    const failure = ref<{ code: Failure; message: string } | null>(null);
    const sending = ref(false);
    const progress = ref(0);
    const progressText = ref('');
    const uploadError = ref<string | null>(null);

    const remembered = ref<Choices>(readChoices());
    const scanners = ref<Scanner[]>([]);
    const listed = ref(false);
    const scannerId = ref<string | null>(null);
    const capabilities = ref<Capabilities | null>(null);
    const resolution = ref<number | null>(null);
    // not remembered: grey every time the page opens, and what is chosen holds until the page is left
    const colour = ref<Colour>(colourFor(null, []));
    const source = ref<Source | null>(null);
    const oneSheet = ref(false);

    const kept = computed((): Side[] =>
        sides.value.filter((side) => !side.aside),
    );
    const aside = computed((): Side[] =>
        sides.value.filter((side) => side.aside),
    );
    const chosen = computed(
        (): Side | null =>
            kept.value.find((side) => side.id === chosenId.value) ?? null,
    );
    const working = computed((): boolean =>
        sides.value.some((side) => side.working),
    );
    const scannerState = computed(
        (): ScannerState => status.value?.scanner ?? 'off',
    );
    const choosing = computed((): boolean => agent.hasChoices(status.value));
    const ready = computed(
        (): boolean => scannerId.value !== null && capabilities.value !== null,
    );
    const scannerOptions = computed(() =>
        scanners.value.map((scanner) => ({
            label: scannerLabel(scanner),
            value: scanner.id,
        })),
    );
    const resolutionOptions = computed(() =>
        resolutionsOf(capabilities.value?.resolutions ?? []).map((offered) => ({
            label: trans(':name (:dots dots)', {
                name: offered.name,
                dots: String(offered.dots),
            }),
            value: offered.dots,
        })),
    );
    const fit = computed((): number | null =>
        resolution.value
            ? sidesThatFit(
                  resolution.value,
                  capabilities.value?.resolutions ?? [],
              )
            : null,
    );
    const colours = computed((): Colour[] => capabilities.value?.colours ?? []);
    const sources = computed((): Source[] => capabilities.value?.sources ?? []);
    const oneSheetUsable = computed(
        (): boolean => oneSheetState(pagesOf(kept.value)) !== 'hidden',
    );
    const missingScanner = computed((): string | null => {
        const last = remembered.value.scanner;

        if (
            !last ||
            !listed.value ||
            scanners.value.some((scanner) => scanner.id === last.id)
        ) {
            return null;
        }

        return last.name;
    });
    const canScan = computed((): boolean => {
        if (
            sending.value ||
            scannerState.value === 'off' ||
            scannerState.value === 'opening'
        ) {
            return false;
        }

        return (
            !choosing.value ||
            Boolean(
                scannerId.value &&
                capabilities.value &&
                resolution.value &&
                source.value,
            )
        );
    });

    /** The sides already taken from the agent, by its session and their id: an agent started again counts from 1. */
    const known = new Set<string>();
    let nextId = 0;
    let collecting: Promise<void> = Promise.resolve();
    /** The sides made again one at a time: a side at 300 dots takes more than a hundred MB while it is worked on. */
    let rendering: Promise<void> = Promise.resolve();
    /** What was chosen before "On a single page" was switched on: it comes back when it is switched off. */
    let before: {
        resolution: number | null;
        colour: Colour;
        source: Source | null;
    } | null = null;
    let timer: ReturnType<typeof setInterval> | null = null;
    let ticking = false;
    let ticks = 0;
    let unmounted = false;

    const sleep = (time: number): Promise<void> =>
        new Promise((resolve) => setTimeout(resolve, time));

    /**
     * `ScanPage`s that stand for some sides, to ask `oneSheetState` whether "On a single page" applies.
     *
     * @param list - the sides
     * @returns one cut out photo for each
     */
    function pagesOf(list: Side[]): Parameters<typeof oneSheetState>[0] {
        return list.map((side) => ({
            id: side.id,
            file: new File([], side.id),
            kind: 'photo' as const,
            corners: null,
            turns: 0 as const,
            improve: { enhance: false, grey: false, dewarp: false },
            cut: true,
            jpeg: side.shown,
        }));
    }

    /** What a side is asked to be made with, as text. */
    const wanted = (side: Side): string =>
        `${side.turns}-${side.enhance}-${side.grey}`;

    /**
     * Makes the JPEG of a side again after the turns or the improvements changed.
     *
     * @param side - the side
     */
    function refresh(side: Side): void {
        side.working = side.applied !== wanted(side);

        if (!side.working) return;

        rendering = rendering.then(async () => {
            const asked = wanted(side);

            if (unmounted || side.applied === asked) {
                side.working = side.applied !== wanted(side);

                return;
            }

            try {
                const blob = await remakeSide(side.original, {
                    turns: side.turns,
                    enhance: side.enhance,
                    grey: side.grey,
                });

                if (unmounted) return;

                URL.revokeObjectURL(side.url);
                side.shown = blob;
                side.url = URL.createObjectURL(blob);
                side.applied = asked;
            } catch {
                // the browser has no memory for it: the side stays as it was
                side.turns = Number(side.applied.split('-')[0]) as Turns;
                side.enhance = side.applied.split('-')[1] === 'true';
                side.grey = side.applied.split('-')[2] === 'true';
            }

            side.working = side.applied !== wanted(side);
        });
    }

    /**
     * Downloads the sides the page does not have yet, one at a time and in the order of the agent.
     *
     * @param snapshot - what the agent holds
     */
    function collect(snapshot: AgentPages): Promise<void> {
        // a download that failed must not stop the ones after it
        collecting = collecting
            .catch(() => undefined)
            .then(async () => {
                for (const one of snapshot.pages) {
                    const key = `${snapshot.session}/${one.id}`;

                    if (known.has(key) || unmounted) continue;

                    const blob = await agent.page(one.id);
                    const bitmap = await createImageBitmap(blob);
                    const blank = isBlank(bitmap, bitmap.width, bitmap.height);
                    bitmap.close();
                    known.add(key);
                    const side: Side = {
                        id: `side-${++nextId}`,
                        original: blob,
                        shown: blob,
                        url: URL.createObjectURL(blob),
                        turns: 0,
                        enhance: false,
                        grey: false,
                        aside: blank ? 'blank' : null,
                        working: false,
                        applied: '0-false-false',
                    };
                    sides.value.push(side);

                    if (!blank && chosenId.value === null) {
                        chosenId.value = side.id;
                    }
                }
            });

        return collecting;
    }

    /** What a failed call says about the agent. */
    function lost(error: unknown): void {
        const reason = agent.failureOf(error);

        if (reason === 'pending') phase.value = 'pending';
        else if (reason === 'denied') phase.value = 'denied';
        else if (reason === 'unreachable') phase.value = 'stopped';
    }

    /** Forgets the scanner's capabilities and what depends on them. */
    function setScanner(id: string | null): void {
        scannerId.value = id;
        capabilities.value = null;
        source.value = null;
        // what "On a single page" chose was of the scanner before
        oneSheet.value = false;
        before = null;
    }

    /** The list of the scanners the computer sees. The first time, the scanner of the page is chosen from it. */
    async function list(): Promise<void> {
        scanners.value = await agent.scanners();

        if (!listed.value || !scannerId.value) {
            const chosenOne = scannerFor(
                scanners.value,
                scannerId.value ?? remembered.value.scanner?.id,
            );

            if (chosenOne !== scannerId.value) setScanner(chosenOne);
        }

        listed.value = true;
    }

    /**
     * Asks the agent how the scanner chosen is, which makes the agent open it and no other; without a scanner
     * chosen the agent opens none. What the scanner has decides which choices are offered, and which hold.
     */
    async function look(): Promise<void> {
        const [asked, from] = [scannerId.value, source.value];
        const answer = await agent.status(
            asked ?? undefined,
            from ?? remembered.value.source,
        );

        // the scanner changed while the agent answered: the answer is of the one before
        if (asked !== scannerId.value) return;

        status.value = answer;

        if (
            !asked ||
            !answer.sources ||
            !answer.resolutions ||
            !answer.colours
        ) {
            return;
        }

        capabilities.value = {
            sources: answer.sources,
            resolutions: answer.resolutions,
            colours: answer.colours,
        };
        source.value = sourceFor(
            source.value ?? remembered.value.source,
            answer.sources,
        );
        resolution.value = resolutionFor(
            resolution.value ?? remembered.value.resolution,
            answer.resolutions,
        );
        colour.value = colourFor(colour.value, answer.colours);
    }

    /** The scanner was chosen by hand: the agent lets go of the one before and opens this one. */
    function chooseScanner(id: string | null): void {
        if (id === scannerId.value || id === null) return;

        setScanner(id);

        if (status.value)
            status.value = { ...status.value, scanner: 'opening' };

        look().catch(lost);
    }

    /** The source was chosen by hand. */
    function chooseSource(value: Source): void {
        source.value = value;
        look().catch(lost);
    }

    /** "On a single page" switched on or off. */
    function setOneSheet(on: boolean): void {
        if (on === oneSheet.value) return;

        oneSheet.value = on;
        const has = capabilities.value;

        if (on) {
            before = {
                resolution: resolution.value,
                colour: colour.value,
                source: source.value,
            };
            colour.value = colourFor('colour', has?.colours ?? []);
            resolution.value =
                resolutionFor(CARD_RESOLUTION, has?.resolutions ?? []) ??
                resolution.value;

            if (
                has?.sources.includes('flatbed') &&
                source.value !== 'flatbed'
            ) {
                chooseSource('flatbed');
            }
        } else if (before) {
            const back = before;
            before = null;
            colour.value = back.colour;
            resolution.value = back.resolution;

            if (back.source && back.source !== source.value) {
                chooseSource(back.source);
            }
        }
    }

    /** Follows a scan until the sheets are over, taking the sides as they arrive. */
    async function follow(): Promise<void> {
        scanning.value = true;

        try {
            for (;;) {
                const snapshot = await agent.pages();
                await collect(snapshot);

                if (snapshot.state !== 'scanning') {
                    if (snapshot.state === 'error' && snapshot.error) {
                        failure.value = {
                            code: snapshot.error,
                            message: snapshot.message,
                        };
                    }

                    return;
                }

                if (unmounted) return;

                await sleep(PAGES_EVERY);
            }
        } catch (error) {
            lost(error);
        } finally {
            scanning.value = false;
        }
    }

    /** Starts a scan with what is chosen, and follows it. */
    async function startScan(): Promise<void> {
        failure.value = null;

        try {
            if (choosing.value) {
                const chosenOne = scanners.value.find(
                    (scanner) => scanner.id === scannerId.value,
                );

                if (!chosenOne || !resolution.value || !source.value) return;

                const settings = {
                    scanner: chosenOne.id,
                    source: source.value,
                    resolution: resolution.value,
                    colour: colour.value,
                };
                // the choices stay for the next time, in this browser; not the ones "On a single page" made, which
                // would open the page at 300 dots and on the glass
                remembered.value = oneSheet.value
                    ? {
                          ...remembered.value,
                          scanner: { id: chosenOne.id, name: chosenOne.name },
                      }
                    : {
                          scanner: { id: chosenOne.id, name: chosenOne.name },
                          resolution: settings.resolution,
                          source: settings.source,
                      };
                writeChoices(remembered.value);
                await agent.scan(settings);
            } else {
                await agent.scan();
            }
        } catch (error) {
            const reason = agent.failureOf(error);

            if (reason === 'settings') {
                failure.value = { code: 'settings', message: '' };

                return;
            }

            // a scan is already running (asked from another tab): it is followed like ours
            if (reason !== 'scanning') return lost(error);
        }

        await follow();
    }

    /** With the agent found: the sides it still holds (the page was reloaded) and a scan in progress are taken up again. */
    async function resume(): Promise<void> {
        const snapshot = await agent.pages();

        if (snapshot.state === 'scanning') return void follow();

        await collect(snapshot);
    }

    /**
     * Looks for the agent; if nobody answers asks the system to start it and tries again for five seconds.
     *
     * @param allowLaunch - false when the browser would refuse to open a program without a click
     */
    async function connect(allowLaunch: boolean): Promise<void> {
        if (system === 'other') {
            phase.value = 'unsupported';

            return;
        }

        phase.value = 'searching';
        let deadline = 0;

        for (;;) {
            try {
                // no scanner is asked yet: an agent that knows the choices opens none
                status.value = await agent.status();
                phase.value = 'connected';

                if (choosing.value) await list().then(look).catch(lost);

                await resume().catch(lost);

                return;
            } catch (error) {
                const reason = agent.failureOf(error);

                if (reason === 'pending' || reason === 'denied') {
                    phase.value = reason;

                    return;
                }
            }

            if (unmounted) return;

            if (!deadline) {
                if (!allowLaunch || !agent.canLaunch()) break;

                agent.launch();
                deadline = Date.now() + LAUNCH_WAIT;
            }

            if (Date.now() > deadline) break;

            await sleep(500);
        }

        blocked.value = await agent.localAccessDenied();
        phase.value = 'missing';
    }

    /**
     * Asks the scanner how it is, only with the page in view: with the tab hidden the page asks nothing, so the
     * agent can quit after its ten minutes.
     */
    async function tick(): Promise<void> {
        if (
            ticking ||
            document.visibilityState !== 'visible' ||
            scanning.value ||
            sending.value
        ) {
            return;
        }

        if (phase.value !== 'connected' && phase.value !== 'pending') return;

        ticking = true;

        try {
            if (
                choosing.value &&
                (ticks++ % agent.LIST_EVERY === 0 || !scannerId.value)
            ) {
                await list();
            }

            await look();

            if (phase.value === 'pending') {
                phase.value = 'connected';
                await resume();
            }
        } catch (error) {
            lost(error);
        } finally {
            ticking = false;
        }
    }

    /** @param id - the side to find */
    const find = (id: string): Side | undefined =>
        sides.value.find((side) => side.id === id);

    /** Chooses the side shown in large. */
    function choose(id: string): void {
        if (find(id)) chosenId.value = id;
    }

    /** Turns a side a quarter of a turn clockwise: the fourth time it is back as it arrived. */
    function rotate(id: string): void {
        const side = find(id);

        if (!side) return;

        side.turns = ((side.turns + 1) % 4) as Turns;
        refresh(side);
    }

    /** Switches *Improve* or *Black and white* of a side. */
    function improve(
        id: string,
        patch: { enhance?: boolean; grey?: boolean },
    ): void {
        const side = find(id);

        if (!side) return;

        Object.assign(side, patch);
        refresh(side);
    }

    /** Gives the improvements of a side to all the sides that stay. */
    function improveAll(id: string): void {
        const from = find(id);

        if (!from) return;

        for (const side of kept.value) {
            if (side.id === id) continue;

            side.enhance = from.enhance;
            side.grey = from.grey;
            refresh(side);
        }
    }

    /** Chooses the side that comes next to the one that left, or none. */
    function chooseAfter(id: string): void {
        if (chosenId.value !== id) return;

        const stay = kept.value.filter((side) => side.id !== id);
        const at = kept.value.findIndex((side) => side.id === id);
        chosenId.value = (stay[at] ?? stay[at - 1])?.id ?? null;
    }

    /** Takes a side out of the PDF. */
    function remove(id: string): void {
        const side = find(id);

        if (!side) return;

        chooseAfter(id);
        side.aside = 'removed';
    }

    /** Puts a side that was aside back. */
    function putBack(id: string): void {
        const side = find(id);

        if (!side) return;

        side.aside = null;
        chosenId.value ??= id;
    }

    /** Moves a side among the ones that stay. */
    function move(from: number, to: number): void {
        const [one, other] = [kept.value[from], kept.value[to]];

        if (!one || !other) return;

        const [a, b] = [sides.value.indexOf(one), sides.value.indexOf(other)];
        const moved = [...sides.value];
        moved.splice(a, 1);
        moved.splice(b, 0, one);
        sides.value = moved;
    }

    /** Empties the temporary folder of the agent. */
    async function clear(): Promise<void> {
        await agent.clear().catch(() => undefined);
    }

    /**
     * Makes the PDF of the sides that stay and sends it as the scanner's, then empties the agent.
     *
     * @param name - the name of the document, empty for the name of the moment
     * @returns true when it all went through
     */
    async function upload(name: string): Promise<boolean> {
        if (sending.value || kept.value.length === 0) return false;

        await rendering;
        uploadError.value = null;
        sending.value = true;
        progress.value = 0;
        progressText.value = trans('Uploading…');

        try {
            const file = await scannerFile(
                kept.value.map((side) => ({ blob: side.shown })),
                { oneSheet: oneSheet.value },
            );
            await sendScanFile(
                file,
                '',
                (fraction) => (progress.value = Math.round(fraction * 100)),
                { source: 'scanner', name: name.trim() },
            );
        } catch (error) {
            uploadError.value =
                error instanceof ScanTooHeavyError
                    ? `${error.message} ${trans('Take away some pages and upload in two goes.')}`
                    : error instanceof ScanRequestError && error.sessionEnded
                      ? trans(
                            'Your session has ended: sign in again. The pages are still here.',
                        )
                      : trans(':reason What was not uploaded is still here.', {
                            reason:
                                error instanceof Error
                                    ? error.message
                                    : trans('The upload did not go through.'),
                        });

            return false;
        } finally {
            sending.value = false;
        }

        for (const side of sides.value) URL.revokeObjectURL(side.url);

        sides.value = [];
        chosenId.value = null;
        known.clear();
        await clear();

        return true;
    }

    /** Asks the scanner how it is when the tab comes back in view. */
    function visibilityChange(): void {
        if (document.visibilityState === 'visible') void tick();
    }

    onMounted(() => {
        void connect(true);
        timer = setInterval(() => void tick(), agent.STATUS_EVERY);
        document.addEventListener('visibilitychange', visibilityChange);
    });

    onBeforeUnmount(() => {
        unmounted = true;

        if (timer) clearInterval(timer);

        document.removeEventListener('visibilitychange', visibilityChange);

        for (const side of sides.value) URL.revokeObjectURL(side.url);

        // the sides left in the agent are the ones the person gave up: its folder does not keep them
        if (phase.value === 'connected') void clear();
    });

    return {
        phase,
        status,
        scannerState,
        choosing,
        scanners,
        scannerOptions,
        scannerId,
        missingScanner,
        ready,
        resolution,
        resolutionOptions,
        fit,
        colour,
        colours,
        source,
        sources,
        oneSheet,
        oneSheetUsable,
        scanning,
        failure,
        canScan,
        outdated: (latest) =>
            Boolean(
                status.value &&
                latest &&
                agent.isOlder(status.value.version, latest),
            ),
        blocked,
        sides,
        kept,
        aside,
        chosenId,
        chosen,
        working,
        sending,
        progress,
        progressText,
        uploadError,
        connect,
        startScan,
        chooseScanner,
        chooseSource,
        setOneSheet,
        choose,
        rotate,
        improve,
        improveAll,
        remove,
        putBack,
        move,
        upload,
        clear,
    };
}
