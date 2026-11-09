// What the page "Scansiona" lets choose before a scan, with an agent that knows the choices (api 2): the scanner,
// the resolution, the colour and where the sheets come from. Which one holds when nothing was chosen, or when the
// scanner does not have the one remembered, is decided here; the choices are remembered in this browser, all but
// the colour, which starts from grey every time the page opens.

import { trans, transChoice } from 'laravel-vue-i18n';
import { MAX_UPLOAD_BYTES } from './pages';
import type { Colour, Scanner, Source } from './agent';

const MB = 1024 * 1024;

/** A resolution the page offers: its dots per inch, its name, and about how much a colour side of an A4 weighs. */
export type Resolution = { dots: number; name: string; bytes: number };

/**
 * The three resolutions (a function: the names follow the language of the page, which is known after start). The weight at 200 dots was measured on 10/10/2026 (0.6-0.7 MB); the other two are
 * estimated from the number of dots, and a grey side was never weighed: it counts as a colour one.
 */
export const resolutions = (): Resolution[] => [
    { dots: 150, name: trans('Light'), bytes: 0.4 * MB },
    { dots: 200, name: trans('Normal'), bytes: 0.65 * MB },
    { dots: 300, name: trans('High'), bytes: 1.5 * MB },
];

/** What holds until something is chosen. */
export const DEFAULT_RESOLUTION = 150;
/**
 * Grey since 11/10/2026, asked by the owner: most sheets are text, and a colour one is chosen when it is needed.
 * For the same reason the colour is not remembered: after one scan in colour the page would open in colour.
 */
export const DEFAULT_COLOUR: Colour = 'grey';
export const DEFAULT_SOURCE: Source = 'feeder';

/** The name of a colour choice, in the language of the page. */
export function colourName(colour: Colour): string {
    return colour === 'colour' ? trans('In colour') : trans('In grey');
}

/** The name of a source, in the language of the page. */
export function sourceName(source: Source): string {
    return source === 'feeder' ? trans('Feeder') : trans('Glass');
}

/** "About 50 sides" for a resolution, from `sidesThatFit`. */
export function sidesText(count: number): string {
    return transChoice('{1} about 1 side|[2,*] about :count sides', count, {
        count: String(count),
    });
}

/**
 * What the page remembers of the last scan, in this browser: the scanner with its name, to say which one is not
 * there. Not the colour: a colour kept by a browser from before 11/10/2026 is left unread.
 */
export type Choices = {
    scanner?: { id: string; name: string };
    resolution?: number;
    source?: Source;
};

/** "SP-1120N · USB", "Canon TS5100 series · rete". */
export function scannerLabel(scanner: Scanner): string {
    return scanner.connection === 'network'
        ? trans(':name · network', { name: scanner.name })
        : trans(':name · USB', { name: scanner.name });
}

/**
 * The resolutions offered of a scanner: the ones of the three that it has. A scanner with none of the three gets
 * the nearest it has above the lightest (or its highest), without a name.
 */
export function resolutionsOf(has: number[]): Resolution[] {
    const offered = resolutions().filter((resolution) =>
        has.includes(resolution.dots),
    );
    if (offered.length || !has.length) return offered;
    const sorted = [...has].sort((a, b) => a - b);
    const dots =
        sorted.find((value) => value >= DEFAULT_RESOLUTION) ??
        sorted[sorted.length - 1];
    // the weight grows with the number of dots of the side
    return [
        {
            dots,
            name: trans(':dots dots', { dots: String(dots) }),
            bytes: 0.65 * MB * (dots / 200) ** 2,
        },
    ];
}

/**
 * The resolution that holds: the one wanted (the one remembered, or the lightest) when the scanner has it,
 * otherwise the nearest above, otherwise the highest. Null when the scanner offers none.
 */
export function resolutionFor(
    wanted: number | null | undefined,
    has: number[],
): number | null {
    const offered = resolutionsOf(has).map((resolution) => resolution.dots);
    if (!offered.length) return null;
    const target = wanted ?? DEFAULT_RESOLUTION;
    return (
        offered.find((dots) => dots >= target) ?? offered[offered.length - 1]
    );
}

/** About how many sides fit in the largest file the reading accepts. */
export function sidesThatFit(dots: number, has: number[]): number | null {
    const resolution = resolutionsOf(has).find(
        (offered) => offered.dots === dots,
    );
    return resolution ? Math.floor(MAX_UPLOAD_BYTES / resolution.bytes) : null;
}

/**
 * The scanner chosen when the page opens: the only one there is; otherwise the one remembered, when it is in the
 * list; otherwise none. The page never moves to another scanner by itself.
 */
export function scannerFor(
    list: Scanner[],
    remembered: string | null | undefined,
): string | null {
    if (list.length === 1) return list[0].id;
    return list.some((scanner) => scanner.id === remembered)
        ? remembered!
        : null;
}

/**
 * The colour that holds: the one wanted (the one chosen in the page since it was opened) when the scanner has it,
 * otherwise grey, otherwise what it has. With nothing known of the scanner yet (`has` empty) the one wanted holds.
 */
export function colourFor(
    wanted: Colour | null | undefined,
    has: Colour[],
): Colour {
    if (wanted && (!has.length || has.includes(wanted))) return wanted;
    return has.includes(DEFAULT_COLOUR) || !has.length
        ? DEFAULT_COLOUR
        : has[0];
}

/** Where the sheets come from: where wanted when the scanner has it, otherwise the feeder, otherwise what it has. */
export function sourceFor(
    wanted: Source | null | undefined,
    has: Source[],
): Source {
    if (wanted && (!has.length || has.includes(wanted))) return wanted;
    return has.includes(DEFAULT_SOURCE) || !has.length
        ? DEFAULT_SOURCE
        : has[0];
}

const STORAGE_KEY = 'family-manager.scan.choices';

type Storage = Pick<globalThis.Storage, 'getItem' | 'setItem'>;

/** The choices of the last scan. A browser that keeps nothing (private window, storage off) gives none. */
export function readChoices(storage: Storage = localStorage): Choices {
    try {
        const kept = JSON.parse(storage.getItem(STORAGE_KEY) ?? '{}');
        const choices: Choices = {};
        if (
            typeof kept?.scanner?.id === 'string' &&
            typeof kept.scanner.name === 'string'
        ) {
            choices.scanner = { id: kept.scanner.id, name: kept.scanner.name };
        }
        if (Number.isInteger(kept?.resolution) && kept.resolution > 0)
            choices.resolution = kept.resolution;
        if (kept?.source === 'feeder' || kept?.source === 'flatbed')
            choices.source = kept.source;
        return choices;
    } catch {
        return {};
    }
}

/** Remembers the choices of this scan in the browser; does nothing when the storage does not work. */
export function writeChoices(
    choices: Choices,
    storage: Storage = localStorage,
): void {
    try {
        storage.setItem(STORAGE_KEY, JSON.stringify(choices));
    } catch {
        // nothing is remembered: the next time the page starts from what holds without a choice
    }
}
