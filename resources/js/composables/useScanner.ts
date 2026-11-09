import { trans, transChoice } from 'laravel-vue-i18n';
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
import { composedSheet, improvedJpeg, measureSide } from '@/lib/scan/canvas';
import { MOST_PER_SHEET, layout, sizeOf } from '@/lib/scan/card';
import type { Card, Cut, Size } from '@/lib/scan/card';
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
import { SHEET } from '@/lib/scan/crop';
import { scanFileNames } from '@/lib/scan/fileName';
import {
    MOST_ANGLE,
    PLAIN,
    SKEW_WORTH,
    isPlain,
    sameImprovement,
} from '@/lib/scan/improve';
import type { Improvement } from '@/lib/scan/improve';
import { MAX_UPLOAD_BYTES } from '@/lib/scan/pages';
import type { Rotation } from '@/lib/scan/pdf';
import { scannerFile } from '@/lib/scan/scannerFile';
import { sendScanFile, uploadErrorText } from '@/lib/scan/server';

/** How often the sides are asked for during a scan, in milliseconds. */
const PAGES_EVERY = 500;
/** How long the agent has to answer after the page asked the system to start it, in milliseconds. */
export const LAUNCH_WAIT = 5000;
/** How long the page counts as launching the agent, in milliseconds: it looks like leaving the page, and is not. */
export const LAUNCHING_FOR = 1000;
/** What "On a single page" asks of the scanner: enough dots to read the small print of a card. */
export const CARD_RESOLUTION = 300;
/** How long after a slider stops the side is made again, in milliseconds. */
export const SLIDER_WAIT = 300;

/**
 * How a side was on a composed page it is no longer on: the cut and the turns, also the ones set by hand, are there
 * again when it goes back on one.
 */
export type AsCard = { cut: Cut; turns: number; round: boolean };

/**
 * One side of a card on a composed page: the side of the scanner it is cut from (out of the strip while it is here),
 * where, how many quarters of a turn clockwise it is given, and whether its corners are rounded.
 */
export type Part = { from: Side; cut: Cut; turns: number; round: boolean };

/** One side of a sheet, already downloaded from the agent, or a composed page: it stays when the agent quits. */
export type Side = {
    /** Identifies the side in the page. */
    id: string;
    /** The JPEG as it arrived from the scanner (a composed page: the page before *Improve*): every change starts from it. */
    original: Blob;
    /** What goes in the PDF: the original, or the one made again with what was asked. */
    blob: Blob;
    /** The address of `blob`; empty while a composed page is drawn for the first time. */
    url: string;
    /** The size in dots. */
    width: number;
    height: number;
    /** How many of its dots make a millimetre. */
    millimetre: number;
    /** How much it is turned by hand, in degrees clockwise: it goes in the PDF, the JPEG stays as it is. */
    rotation: Rotation;
    /** Why it is not among the sides of the PDF: found blank, or taken away by hand. */
    aside: 'blank' | 'removed' | null;
    /** By how much the sheet went in crooked, in degrees clockwise; null when the side does not say. */
    skew: number | null;
    /** What is asked for the side, and what `blob` was made with: they differ while it is being made again. */
    improvement: Improvement;
    applied: Improvement;
    /** `blob` is being made again. */
    working: boolean;
    /** Where a card was found on it, in its dots, and whether it is small enough to be a card. */
    found: Cut | null;
    small: boolean;
    /** A composed page: the cards it holds, from the top; null for a side of the scanner. */
    parts: Part[] | null;
    /** The parts changed since `blob` was made. */
    stale: boolean;
    /** How it was on a composed page it is no longer on. */
    asCard: AsCard | null;
};

/** Why a scan stopped, or did not start: `settings` is the agent refusing what the scan was asked with. */
export type Failure = ScanFailure | 'settings';

/** What the scanner chosen has, kept from the last answer that said it: during a scan the agent does not say it. */
type Capabilities = {
    sources: Source[];
    resolutions: number[];
    colours: Colour[];
};

/** Where a card is on its composed page, as shares of the page (`left: '10%'`). */
export type Spot = { left: string; top: string; width: string; height: string };

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
    /** "On a single page" is switched on: the sides that arrive are cut and put on a composed page. */
    oneSheet: Ref<boolean>;
    /** How many sides of cards are on the composed pages of the PDF. */
    cardSides: ComputedRef<number>;
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
    /** The page is asking the system to start the agent: it looks like leaving the page, and is not. */
    launching: Ref<boolean>;
    /** The sides, the ones that stay and the ones put aside, in the order of the strip. */
    sides: Ref<Side[]>;
    /** The sides that go in the PDF, in the order of the strip. */
    kept: ComputedRef<Side[]>;
    /** The sides put aside: blank, or taken away. */
    aside: ComputedRef<Side[]>;
    /** The sides of the PDF that look like a card, or were on a composed page, and are not on one now. */
    loose: ComputedRef<Side[]>;
    /** The sides of the PDF that went in crooked enough to be straightened, and were not. */
    crooked: ComputedRef<Side[]>;
    /** The id of the side shown in large. */
    chosenId: Ref<string | null>;
    /** The side shown in large. */
    chosen: ComputedRef<Side | null>;
    /** Some side is being made again. */
    working: ComputedRef<boolean>;
    /** One PDF per side instead of one for all. */
    onePerSide: Ref<boolean>;
    /** One PDF per side is asked, and there is more than one side. */
    split: ComputedRef<boolean>;
    /** What the sides of the PDF weigh, in bytes. */
    weight: ComputedRef<number>;
    /** What the heaviest side weighs, in bytes. */
    heaviest: ComputedRef<number>;
    /** A PDF would be over the limit: the one of all the sides, or with a PDF per side the one of the heaviest. */
    tooLarge: ComputedRef<boolean>;
    /** The files are leaving. */
    sending: Ref<boolean>;
    /** How much has left, from 0 to 100. */
    progress: Ref<number>;
    /** «Uploading…». */
    progressText: Ref<string>;
    /** Why the upload stopped. */
    uploadError: Ref<string | null>;
    /** A side could not be made again: the browser has not enough memory. */
    improveError: Ref<string | null>;
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
    /** Turns every side of the PDF a quarter of a turn; a composed page stays standing. */
    rotateAll: () => void;
    /** Turns a side by a few degrees, within `MOST_ANGLE`. */
    setAngle: (id: string, degrees: number) => void;
    /** Turns a side back by as much as it went in crooked. */
    findAngle: (id: string) => void;
    /** Turns back every side of the PDF that went in crooked. */
    straightenAll: () => void;
    /** How strong *Improve* is on a side, from 0 to 100. */
    setEnhance: (id: string, value: number) => void;
    /** *Black and white* on a side. */
    setGrey: (id: string, on: boolean) => void;
    /** Gives *Improve* and *Black and white* of a side to all the sides that stay; the angle is of each sheet. */
    improveAll: (id: string) => void;
    /** Takes a side out of the PDF. */
    remove: (id: string) => void;
    /** Puts a side that was aside back. */
    putBack: (id: string) => void;
    /** Moves a side among the ones that stay (positions in the strip). */
    move: (from: number, to: number) => void;
    /** "Put on one page": the loose sides go on composed pages. */
    gatherLoose: () => void;
    /** "Separate": the sides of a composed page go back in the strip as they arrived. */
    separate: (id: string) => void;
    /** "Rounded corners" for all the cards of a composed page. */
    setRound: (id: string, on: boolean) => void;
    /** A quarter of a turn clockwise to one card of a composed page. */
    turnPart: (id: string, index: number) => void;
    /** Moves a card of a composed page one place up or down. */
    movePart: (id: string, index: number, step: -1 | 1) => void;
    /** Takes a card off a composed page: its side goes among the ones put aside. */
    dropPart: (id: string, index: number) => void;
    /** The cut of a card corrected by hand. */
    setCut: (id: string, index: number, cut: Cut) => void;
    /** Where the cards of a composed page are on it. */
    spotsOf: (side: Side) => Spot[];
    /** By how much the cards of a composed page were reduced to fit: 1 at their real size. */
    shrinkOf: (side: Side) => number;
    /** How large a card is on its page, in millimetres. */
    partSize: (part: Part) => Size;
    /** Makes the PDFs, sends them as the scanner's, empties the agent. True when it all went through. */
    upload: (name: string) => Promise<boolean>;
    /** Empties the temporary folder of the agent. */
    clear: () => Promise<void>;
};

/**
 * The scanner of the house, driven through its agent: finds the agent, follows its answers, keeps the choices of
 * the scan and the sides that come in (straightened, improved, cut onto composed pages) and sends the PDF. The page
 * only shows it.
 *
 * @returns the state and the actions
 */
export function useScanner(): UseScanner {
    const system = desktopSystem();
    const phase = ref<Phase>('searching');
    const status = ref<AgentStatus | null>(null);
    const blocked = ref(false);
    const launching = ref(false);
    const sides = ref<Side[]>([]);
    const chosenId = ref<string | null>(null);
    const scanning = ref(false);
    const failure = ref<{ code: Failure; message: string } | null>(null);
    const onePerSide = ref(false);
    const sending = ref(false);
    const progress = ref(0);
    const progressText = ref('');
    const uploadError = ref<string | null>(null);
    const improveError = ref<string | null>(null);

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
    const loose = computed((): Side[] =>
        kept.value.filter(
            (side) => (side.small || side.asCard !== null) && !side.parts,
        ),
    );
    const crooked = computed((): Side[] => kept.value.filter(isCrooked));
    const cardSides = computed((): number =>
        kept.value.reduce(
            (total, side) => total + (side.parts?.length ?? 0),
            0,
        ),
    );
    const chosen = computed(
        (): Side | null =>
            kept.value.find((side) => side.id === chosenId.value) ?? null,
    );
    const working = computed((): boolean =>
        sides.value.some((side) => side.working),
    );
    const split = computed(
        (): boolean => onePerSide.value && kept.value.length > 1,
    );
    const weight = computed((): number =>
        kept.value.reduce((total, side) => total + side.blob.size, 0),
    );
    const heaviest = computed((): number =>
        Math.max(0, ...kept.value.map((side) => side.blob.size)),
    );
    const tooLarge = computed(
        (): boolean =>
            (split.value ? heaviest.value : weight.value) > MAX_UPLOAD_BYTES,
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
            phase.value !== 'connected' ||
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
    /** The dots per inch asked for the last scan: the size of a dot, when the JPEG does not say it. */
    let askedResolution: number | null = null;
    /** Whether the last composed page was asked with rounded corners: the next ones start so. */
    let rounding = false;
    /** The PDFs already uploaded by a "Upload" stopped halfway, and when it started: the names go on from them. */
    let uploadedBefore = 0;
    let uploadStarted = new Date();
    const sliderTimers = new Map<string, ReturnType<typeof setTimeout>>();
    let timer: ReturnType<typeof setInterval> | null = null;
    let ticking = false;
    let ticks = 0;
    let unmounted = false;

    const sleep = (time: number): Promise<void> =>
        new Promise((resolve) => setTimeout(resolve, time));

    /**
     * Whether a side went in crooked enough to be worth straightening, and was not turned yet.
     *
     * @param side - the side
     * @returns true for a side to straighten
     */
    function isCrooked(side: Side): boolean {
        return (
            side.parts === null &&
            side.skew !== null &&
            Math.abs(side.skew) >= SKEW_WORTH &&
            !side.improvement.angle
        );
    }

    /**
     * A part as `sheetCanvas` wants it, with its cut copied: the part can change while the page is drawn.
     *
     * @param part - the card on the page
     * @returns the card to draw
     */
    function cardOf(part: Part): Card {
        return {
            image: part.from.original,
            cut: { box: { ...part.cut.box }, angle: part.cut.angle },
            turns: part.turns,
            millimetre: part.from.millimetre,
            round: part.round,
        };
    }

    /**
     * Makes the JPEG of a side again after what is asked changed: from the JPEG of the scanner every time; a
     * composed page is drawn again from its cards. One side at a time.
     *
     * @param side - the side
     * @returns when the queue of sides to make is done
     */
    function render(side: Side): Promise<void> {
        const pending = (): boolean =>
            side.stale || !sameImprovement(side.improvement, side.applied);
        side.working = pending();

        if (!side.working) return rendering;

        rendering = rendering.then(async () => {
            const wanted = { ...side.improvement };

            // a composed page that was separated in the meantime is no longer in the strip
            if (
                unmounted ||
                !pending() ||
                (side.parts && !sides.value.includes(side))
            ) {
                side.working = false;

                return;
            }

            try {
                if (side.parts) {
                    side.stale = false;
                    const page = await composedSheet(
                        side.parts.map(cardOf),
                        wanted,
                    );

                    if (unmounted) return;

                    if (side.url) URL.revokeObjectURL(side.url);

                    Object.assign(side, {
                        original: page.original,
                        blob: page.blob,
                        url: URL.createObjectURL(page.blob),
                        width: page.width,
                        height: page.height,
                        millimetre: page.width / SHEET.width,
                    });
                } else {
                    const blob = isPlain(wanted)
                        ? side.original
                        : await improvedJpeg(side.original, wanted);

                    if (unmounted) return;

                    URL.revokeObjectURL(side.url);
                    side.blob = blob;
                    side.url = URL.createObjectURL(blob);
                }

                side.applied = wanted;
            } catch {
                side.improvement = { ...side.applied };

                if (side.parts && !side.url) {
                    // a page that was never composed has nothing to show: its sides go back as they arrived
                    separate(side.id);
                    improveError.value = trans(
                        'The cards could not be put on one page: the browser has not enough memory. The sides are back as they arrived from the scanner.',
                    );
                } else {
                    improveError.value = trans(
                        'A side could not be improved and stayed as it was: the browser has not enough memory. Try with fewer tabs open, or scan at a lower resolution.',
                    );
                }
            }

            // asked again in the meantime: whoever changed it calls this again
            side.working = pending();
        });

        return rendering;
    }

    /**
     * Makes a side again a moment after a slider stopped, not at every step of it.
     *
     * @param side - the side
     */
    function later(side: Side): void {
        clearTimeout(sliderTimers.get(side.id));
        side.working = true;
        sliderTimers.set(
            side.id,
            setTimeout(() => {
                sliderTimers.delete(side.id);
                void render(side);
            }, SLIDER_WAIT),
        );
    }

    /**
     * A new composed page with one part: empty until `render` draws it, as large as an A4 meanwhile.
     *
     * @param part - its first card
     * @returns the page
     */
    function sheetOf(part: Part): Side {
        const nothing = new Blob();

        return {
            id: `side-${++nextId}`,
            original: nothing,
            blob: nothing,
            url: '',
            width: SHEET.width,
            height: SHEET.height,
            millimetre: 1,
            rotation: 0,
            aside: null,
            skew: null,
            improvement: { ...PLAIN },
            applied: { ...PLAIN },
            working: true,
            found: null,
            small: false,
            parts: [part],
            stale: true,
            asCard: null,
        };
    }

    /** The parts of a composed page changed: it is drawn again. */
    function recompose(sheet: Side): void {
        sheet.stale = true;
        void render(sheet);
    }

    /**
     * Puts sides of the scanner on a composed page, each as the card found in it, lying with its long side across;
     * a side where nothing was found goes whole, to be cut by hand. With `join` they go on the last composed page
     * while it has room; otherwise, and when it is full, a new page takes the place of the side.
     *
     * @param list - the sides
     * @param join - whether the last composed page can take them
     */
    function gather(list: Side[], join: boolean): void {
        let sheet = join
            ? [...kept.value]
                  .reverse()
                  .find(
                      (side) =>
                          side.parts && side.parts.length < MOST_PER_SHEET,
                  )
            : undefined;

        for (const side of list) {
            const at = sides.value.indexOf(side);

            if (at < 0 || side.parts) continue;

            const cut = side.asCard?.cut ??
                side.found ?? {
                    box: { x: 0, y: 0, width: side.width, height: side.height },
                    angle: 0,
                };
            const part: Part = {
                from: side,
                cut: { box: { ...cut.box }, angle: cut.angle },
                turns:
                    side.asCard?.turns ??
                    (side.small && cut.box.height > cut.box.width ? 1 : 0),
                round: side.asCard?.round ?? rounding,
            };
            const wasChosen = chosenId.value === side.id;

            if (sheet?.parts && sheet.parts.length < MOST_PER_SHEET) {
                sides.value.splice(at, 1);
                sheet.parts.push(part);
            } else {
                sides.value.splice(at, 1, sheetOf(part));
                sheet = sides.value[at]!;
            }

            if (wasChosen || chosenId.value === null) chosenId.value = sheet.id;

            recompose(sheet);
        }
    }

    /**
     * Downloads the sides the page does not have yet, one at a time and in the order of the agent.
     *
     * @param snapshot - what the agent holds
     * @returns when they are all in the page
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
                    const measure = await measureSide(
                        blob,
                        bitmap,
                        askedResolution,
                    );
                    const { width, height } = bitmap;
                    bitmap.close();
                    known.add(key);
                    sides.value.push({
                        id: `side-${++nextId}`,
                        original: blob,
                        blob,
                        url: URL.createObjectURL(blob),
                        width,
                        height,
                        millimetre: measure.millimetre,
                        rotation: 0,
                        aside: blank ? 'blank' : null,
                        skew: measure.skew,
                        improvement: { ...PLAIN },
                        applied: { ...PLAIN },
                        working: false,
                        found: measure.found,
                        small: measure.small,
                        parts: null,
                        stale: false,
                        asCard: null,
                    });
                    const arrived = sides.value[sides.value.length - 1]!;

                    if (blank) continue;

                    // with "On a single page" every side with something on it goes on the composed page, also one
                    // where no card was found: there its cut is corrected by hand
                    if (oneSheet.value) gather([arrived], true);
                    else chosenId.value ??= arrived.id;
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

    /**
     * Whether two lists of scanners are the same: the menu is not given a new list for nothing.
     *
     * @param a - one list
     * @param b - the other
     * @returns true when they have the same scanners, in the same order
     */
    function sameScanners(a: Scanner[], b: Scanner[]): boolean {
        return (
            a.length === b.length &&
            a.every(
                (one, index) =>
                    one.id === b[index]!.id &&
                    one.name === b[index]!.name &&
                    one.connection === b[index]!.connection,
            )
        );
    }

    /** The list of the scanners the computer sees. The first time, the scanner of the page is chosen from it. */
    async function list(): Promise<void> {
        const found = await agent.scanners();

        if (!sameScanners(found, scanners.value)) scanners.value = found;

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
        // the button of the strip has no state of its own: a scan during another, or during an upload, would lose sides
        if (!canScan.value || scanning.value) return;

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
                askedResolution = settings.resolution;
                await agent.scan(settings);
            } else {
                askedResolution = null;
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

                launching.value = true;
                agent.launch();
                setTimeout(() => (launching.value = false), LAUNCHING_FOR);
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

        if (!side || side.parts) return;

        side.rotation = ((side.rotation + 90) % 360) as Rotation;
    }

    /** Turns the sides of the PDF; a composed page stays standing, its cards are turned one by one. */
    function rotateAll(): void {
        for (const side of kept.value) rotate(side.id);
    }

    /** Turns a side by some degrees, within what a side may be turned by hand. */
    function setAngle(id: string, degrees: number): void {
        const side = find(id);

        if (!side || side.parts) return;

        // `|| 0`: a zero that comes from a negative number is still a zero
        side.improvement.angle =
            Math.min(
                MOST_ANGLE,
                Math.max(-MOST_ANGLE, Math.round(degrees * 10) / 10),
            ) || 0;
        later(side);
    }

    /** Turns a side back by as much as it was found crooked. */
    function findAngle(id: string): void {
        const side = find(id);

        if (side?.skew !== null && side?.skew !== undefined)
            setAngle(id, -side.skew);
    }

    /** Every side of the PDF that went in crooked is turned back by as much as it was found crooked. */
    function straightenAll(): void {
        for (const side of crooked.value) {
            side.improvement.angle = -(side.skew ?? 0) || 0;
            void render(side);
        }
    }

    /** How strong *Improve* is on a side. */
    function setEnhance(id: string, value: number): void {
        const side = find(id);

        if (!side) return;

        side.improvement.enhance =
            Math.min(100, Math.max(0, Math.round(value))) || 0;
        later(side);
    }

    /** *Black and white* on a side. */
    function setGrey(id: string, on: boolean): void {
        const side = find(id);

        if (!side) return;

        side.improvement.grey = on;
        void render(side);
    }

    /** Gives *Improve* and *Black and white* of a side to all the sides that stay. */
    function improveAll(id: string): void {
        const from = find(id);

        if (!from) return;

        for (const side of kept.value) {
            if (side.id === id) continue;

            side.improvement.enhance = from.improvement.enhance;
            side.improvement.grey = from.improvement.grey;
            void render(side);
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

    /** The loose sides of the PDF go on composed pages, cut as they were. */
    function gatherLoose(): void {
        gather([...loose.value], false);
    }

    /** The sides of a composed page go back in the strip as they arrived, where the page was. */
    function separate(id: string): void {
        const sheet = find(id);
        const at = sheet ? sides.value.indexOf(sheet) : -1;

        if (!sheet?.parts || at < 0) return;

        for (const part of sheet.parts) {
            part.from.asCard = {
                cut: part.cut,
                turns: part.turns,
                round: part.round,
            };
        }

        const back = sheet.parts.map((part) => part.from);
        sides.value.splice(at, 1, ...back);

        if (chosenId.value === id) chosenId.value = back[0]?.id ?? null;

        if (sheet.url) URL.revokeObjectURL(sheet.url);
    }

    /** "Rounded corners" for all the cards of a composed page; the cards put on a page after this start the same. */
    function setRound(id: string, on: boolean): void {
        const sheet = find(id);

        if (!sheet?.parts) return;

        rounding = on;

        for (const part of sheet.parts) part.round = on;

        recompose(sheet);
    }

    /** A quarter of a turn clockwise to one card of a composed page. */
    function turnPart(id: string, index: number): void {
        const sheet = find(id);
        const part = sheet?.parts?.[index];

        if (!sheet || !part) return;

        part.turns = (part.turns + 1) % 4;
        recompose(sheet);
    }

    /** Moves a card of a composed page one place up or down. */
    function movePart(id: string, index: number, step: -1 | 1): void {
        const sheet = find(id);
        const parts = sheet?.parts;
        const to = index + step;

        if (!sheet || !parts?.[index] || to < 0 || to >= parts.length) return;

        const [part] = parts.splice(index, 1);
        parts.splice(to, 0, part!);
        recompose(sheet);
    }

    /** Takes a card off a composed page: its side goes among the ones put aside, whole; a page left empty goes away. */
    function dropPart(id: string, index: number): void {
        const sheet = find(id);
        const parts = sheet?.parts;
        const part = parts?.[index];

        if (!sheet || !parts || !part) return;

        parts.splice(index, 1);
        part.from.asCard = {
            cut: part.cut,
            turns: part.turns,
            round: part.round,
        };
        part.from.aside = 'removed';
        sides.value.push(part.from);

        if (parts.length) {
            recompose(sheet);

            return;
        }

        separate(id);
    }

    /** The cut of a card corrected by hand: the page is drawn again. */
    function setCut(id: string, index: number, cut: Cut): void {
        const sheet = find(id);
        const part = sheet?.parts?.[index];

        if (!sheet || !part) return;

        part.cut = { box: { ...cut.box }, angle: cut.angle };
        recompose(sheet);
    }

    /** How large a card is on its page, in millimetres. */
    function partSize(part: Part): Size {
        return sizeOf(cardOf(part));
    }

    /** Where the cards of a composed page are on it, as shares of the page. */
    function spotsOf(side: Side): Spot[] {
        const share = (value: number, of: number): string =>
            `${(value / of) * 100}%`;

        return layout((side.parts ?? []).map(partSize)).places.map((place) => ({
            left: share(place.x, SHEET.width),
            top: share(place.y, SHEET.height),
            width: share(place.width, SHEET.width),
            height: share(place.height, SHEET.height),
        }));
    }

    /** By how much the cards of a composed page were reduced to fit. */
    function shrinkOf(side: Side): number {
        return layout((side.parts ?? []).map(partSize)).scale;
    }

    /** Empties the temporary folder of the agent. */
    async function clear(): Promise<void> {
        await agent.clear().catch(() => undefined);
    }

    /**
     * Forgets some sides: their pictures are given back.
     *
     * @param gone - the sides that leave the page
     */
    function forget(gone: Side[]): void {
        for (const side of gone) {
            URL.revokeObjectURL(side.url);

            for (const part of side.parts ?? []) {
                URL.revokeObjectURL(part.from.url);
            }
        }

        sides.value = sides.value.filter((side) => !gone.includes(side));

        if (!kept.value.some((side) => side.id === chosenId.value)) {
            chosenId.value = kept.value[0]?.id ?? null;
        }
    }

    /**
     * Makes the PDF of the sides that stay, or one per side, and sends them as the scanner's, then empties the
     * agent. When a PDF per side stops halfway, the sides already uploaded leave the page: a new *Upload* does not
     * send them twice, and their names go on from there.
     *
     * @param name - the name of the document, empty for the name of the moment
     * @returns true when it all went through
     */
    async function upload(name: string): Promise<boolean> {
        if (sending.value || kept.value.length === 0 || tooLarge.value) {
            return false;
        }

        // first: a second click while the sides are still being made must not send twice
        sending.value = true;
        uploadError.value = null;
        progress.value = 0;
        progressText.value = trans('Uploading…');
        let groups: Side[][] = [];
        let done = 0;

        try {
            // the sides still being made go in the PDF as they were asked
            await rendering;
            groups = split.value
                ? kept.value.map((side) => [side])
                : [[...kept.value]];

            if (!uploadedBefore) uploadStarted = new Date();

            // one PDF takes the name written, or the server names it with the moment; more PDFs are numbered
            const names =
                groups.length === 1 && !uploadedBefore
                    ? [name.trim()]
                    : scanFileNames(
                          name,
                          groups.length,
                          uploadStarted,
                          uploadedBefore,
                      );

            for (const [index, group] of groups.entries()) {
                if (groups.length > 1) {
                    progressText.value = trans(
                        'Uploading file :number of :count…',
                        {
                            number: String(index + 1),
                            count: String(groups.length),
                        },
                    );
                }

                const file = await scannerFile(
                    group.map((side) => ({
                        blob: side.blob,
                        width: side.width,
                        height: side.height,
                        rotation: side.rotation,
                    })),
                );
                await sendScanFile(
                    file,
                    '',
                    (fraction) =>
                        (progress.value = Math.round(
                            ((index + fraction) / groups.length) * 100,
                        )),
                    { source: 'scanner', name: names[index] ?? '' },
                );
                done++;
            }
        } catch (error) {
            if (done) {
                uploadedBefore += done;
                forget(groups.slice(0, done).flat());
                uploadError.value = transChoice(
                    '{1} :done side of :total uploaded, the others not: :reason|[2,*] :done sides of :total uploaded, the others not: :reason',
                    done,
                    {
                        done: String(done),
                        total: String(groups.length),
                        reason: uploadErrorText(error),
                    },
                );
            } else {
                uploadError.value = uploadErrorText(error);
            }

            return false;
        } finally {
            sending.value = false;
        }

        uploadedBefore = 0;
        forget([...sides.value]);
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

        for (const pending of sliderTimers.values()) clearTimeout(pending);

        document.removeEventListener('visibilitychange', visibilityChange);

        for (const side of sides.value) {
            URL.revokeObjectURL(side.url);

            for (const part of side.parts ?? []) {
                URL.revokeObjectURL(part.from.url);
            }
        }

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
        cardSides,
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
        launching,
        sides,
        kept,
        aside,
        loose,
        crooked,
        chosenId,
        chosen,
        working,
        onePerSide,
        split,
        weight,
        heaviest,
        tooLarge,
        sending,
        progress,
        progressText,
        uploadError,
        improveError,
        connect,
        startScan,
        chooseScanner,
        chooseSource,
        setOneSheet,
        choose,
        rotate,
        rotateAll,
        setAngle,
        findAngle,
        straightenAll,
        setEnhance,
        setGrey,
        improveAll,
        remove,
        putBack,
        move,
        gatherLoose,
        separate,
        setRound,
        turnPart,
        movePart,
        dropPart,
        setCut,
        spotsOf,
        shrinkOf,
        partSize,
        upload,
        clear,
    };
}
