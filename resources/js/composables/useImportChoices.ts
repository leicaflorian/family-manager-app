import type { CancelToken } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import type { ComputedRef } from 'vue';
import type { AlsoSide, ImportTarget } from '@/lib/alsoDo';
import type { DocumentPayload } from '@/lib/documents';

/** The targets of the expense, the event and each card. */
export type ImportTargets = {
    movement: ImportTarget;
    event: ImportTarget;
    records: Partial<Record<App.Enums.RecordKind, ImportTarget>>;
};

/** A value the person wrote in a box: text, a date or a choice. */
export type OverrideValue = string | number | null;

/** The corrections of the expense, the event and each card, by field, as `ImportChoices::fromArray` reads them. */
export type ImportOverrides = {
    movement: Record<string, OverrideValue>;
    event: Record<string, OverrideValue>;
    records: Partial<
        Record<App.Enums.RecordKind, Record<string, OverrideValue>>
    >;
};

/**
 * What the person changed in *What I do too* for one file. A box left as it
 * is (`null`, or missing for a card) follows the proposal.
 */
export type ImportPicks = {
    movement: boolean | null;
    event: boolean | null;
    records: Partial<Record<App.Enums.RecordKind, boolean>>;
    targets: ImportTargets;
    /** The fields the person wrote, and only those. */
    overrides: ImportOverrides;
    /** *To pay* (true) or *Already paid* (false); null follows the proposal. */
    due: boolean | null;
    /** `auto`, `none` or the `key` of the policy to take. */
    policy: string;
    /** *Create the card*: after the import, *New card* opens with the document linked. */
    createCard: boolean;
    /** The old policies the person unticked under the box of the new policy: the others are cancelled. */
    spared: number[];
};

/** What one file does too, as the server reads it (`App\Data\Reading\ImportChoices`, without the document). */
export type ImportAlso = {
    movement: boolean;
    event: boolean;
    records: App.Enums.RecordKind[];
    targets: ImportTargets;
    /** Only when the person wrote a field. */
    overrides?: ImportOverrides;
    /** Only when the person chose: missing, the server decides. */
    due?: boolean;
    policy: string;
    /** Only when the paper names no card and the person ticked *Create the card*. */
    create_card?: true;
    /** The card of the category of the proposal shown: the server drops the choices of its box for another card. */
    card_type: string | null;
    /** Only while the box of the new policy is ticked and at least one old policy under it is: the ones to cancel. */
    replace_policies?: number[];
};

/** The choices of one file, and how to ask its proposal again. */
export interface ImportChoices {
    /** What the person changed. */
    picks: ImportPicks;
    /** What is sent as `also`: the picks over the proposal; undefined without a proposal. */
    also: ComputedRef<ImportAlso | undefined>;
    /** Whether the person changed anything. */
    changed: ComputedRef<boolean>;
    /** What a box is linked to: the one chosen by the person, `auto` if none. */
    targetOf: (side: AlsoSide) => ImportTarget;
    /** Links a box to a new one, to one chosen, or back to the proposal (`auto`). */
    setTarget: (side: AlsoSide, target: ImportTarget) => void;
    /** Ticks or unticks a box. */
    setTicked: (side: AlsoSide, value: boolean) => void;
    /** What the person wrote in a field of a box, undefined if nothing. */
    overrideOf: (side: AlsoSide, field: string) => OverrideValue | undefined;
    /** Writes a field of a box. */
    setOverride: (side: AlsoSide, field: string, value: OverrideValue) => void;
    /** Asks the proposal again now, without waiting. */
    refresh: () => void;
    /** Forgets the wait and the request running: after an import, nothing asks for a file saved. */
    cancel: () => void;
}

/** How long to wait after the last change before asking the proposal again, in milliseconds. */
const WAIT = 400;

/**
 * The choices of *What I do too* of one file in *Import*: the boxes, the
 * expense, event or card picked by hand, the fields written in a box, *To pay*,
 * the policy and *Create the card*. Every change, and every change of the document of the file,
 * asks the server the proposal again 400 ms after the last one, reloading only
 * `proposals` with `?check={file_id, document, also}` and without changing the
 * address; the server merges it with the others. The fields count too: what
 * is missing, and the deadline of *To pay*, change the proposal. A request
 * still running is cancelled by the next one, so an older proposal never comes
 * after a newer one, and nothing is asked after the page went away. When a
 * new proposal follows another card (`cardType`, the kind of the document
 * changed), the tick, the fields written and the card picked of the box of
 * the card, and of the boxes that went away, are forgotten; the expense and
 * the event keep theirs.
 *
 * @param fileId - the file
 * @param proposal - gives the proposal of the file, as the server sent it last
 * @param options - `document` gives what the form of the document sends;
 *   `enabled` says whether changes ask again (not while the forms fill in)
 * @returns the picks, what to send and how to change them
 */
export function useImportChoices(
    fileId: number,
    proposal: () => App.Data.Reading.ProposalData | undefined,
    options: {
        document?: () => DocumentPayload | undefined;
        enabled?: () => boolean;
    } = {},
): ImportChoices {
    const document = options.document ?? ((): undefined => undefined);
    const enabled = options.enabled ?? ((): boolean => true);

    const picks = reactive<ImportPicks>({
        movement: null,
        event: null,
        records: {},
        targets: { movement: 'auto', event: 'auto', records: {} },
        overrides: { movement: {}, event: {}, records: {} },
        due: null,
        policy: 'auto',
        createCard: false,
        spared: [],
    });

    /**
     * Whether the person wrote any field.
     *
     * @returns true with at least one correction
     */
    function corrected(): boolean {
        const { movement, event, records } = picks.overrides;

        return (
            Object.keys(movement).length > 0 ||
            Object.keys(event).length > 0 ||
            Object.keys(records).length > 0
        );
    }

    const untouched = JSON.stringify(picks);
    const changed = computed(
        (): boolean => JSON.stringify(picks) !== untouched,
    );

    const also = computed((): ImportAlso | undefined => {
        const current = proposal();

        if (!current) {
            return undefined;
        }

        const records = current.records.filter(
            (draft) => picks.records[draft.kind] ?? draft.suggested,
        );
        const replaced = records
            .filter((draft) => draft.kind === 'policy')
            .flatMap((draft) => draft.replaces)
            .map((old) => old.id)
            .filter((id) => !picks.spared.includes(id));

        return {
            movement: picks.movement ?? current.movement?.suggested ?? false,
            event: picks.event ?? current.event?.suggested ?? false,
            records: records.map((draft) => draft.kind),
            targets: {
                movement: picks.targets.movement,
                event: picks.targets.event,
                records: { ...picks.targets.records },
            },
            ...(corrected() ? { overrides: plainOverrides() } : {}),
            ...(picks.due === null ? {} : { due: picks.due }),
            policy: picks.policy,
            ...(current.createCard && picks.createCard
                ? { create_card: true as const }
                : {}),
            card_type: current.cardType,
            ...(replaced.length > 0 ? { replace_policies: replaced } : {}),
        };
    });

    /**
     * The corrections as plain objects, a copy that can be sent.
     *
     * @returns the corrections
     */
    function plainOverrides(): ImportOverrides {
        return JSON.parse(JSON.stringify(picks.overrides)) as ImportOverrides;
    }

    /**
     * What a box is linked to.
     *
     * @param side - the box
     * @returns the target chosen by the person, `auto` if none
     */
    function targetOf(side: AlsoSide): ImportTarget {
        if (side === 'movement' || side === 'event') {
            return picks.targets[side];
        }

        return picks.targets.records[side] ?? 'auto';
    }

    /**
     * Links a box to something else.
     *
     * @param side - the box
     * @param target - `auto`, `new` or the id chosen
     */
    function setTarget(side: AlsoSide, target: ImportTarget): void {
        if (side === 'movement' || side === 'event') {
            picks.targets[side] = target;
        } else {
            picks.targets.records[side] = target;
        }
    }

    /**
     * Ticks or unticks a box.
     *
     * @param side - the box
     * @param value - ticked or not
     */
    function setTicked(side: AlsoSide, value: boolean): void {
        if (side === 'movement' || side === 'event') {
            picks[side] = value;
        } else {
            picks.records[side] = value;
        }
    }

    /**
     * The corrections of a box.
     *
     * @param side - the box
     * @returns its fields written, created when missing
     */
    function overridesOf(side: AlsoSide): Record<string, OverrideValue> {
        if (side === 'movement' || side === 'event') {
            return picks.overrides[side];
        }

        return (picks.overrides.records[side] ??= {});
    }

    /**
     * What the person wrote in a field of a box.
     *
     * @param side - the box
     * @param field - the column
     * @returns the value, undefined if nothing was written
     */
    function overrideOf(
        side: AlsoSide,
        field: string,
    ): OverrideValue | undefined {
        if (side === 'movement' || side === 'event') {
            return picks.overrides[side][field];
        }

        return picks.overrides.records[side]?.[field];
    }

    /**
     * Writes a field of a box.
     *
     * @param side - the box
     * @param field - the column
     * @param value - what the person wrote
     */
    function setOverride(
        side: AlsoSide,
        field: string,
        value: OverrideValue,
    ): void {
        overridesOf(side)[field] = value;
    }

    let timer: ReturnType<typeof setTimeout> | undefined;
    let running: CancelToken | undefined;
    let gone = false;

    /** Forgets the wait and cancels the request running. */
    function cancel(): void {
        clearTimeout(timer);
        running?.cancel();
        running = undefined;
    }

    /** Asks the server the proposal of the file with the choices of now, cancelling the one still running. */
    function ask(): void {
        const sent = also.value;

        if (gone || !sent) {
            return;
        }

        running?.cancel();
        router.reload({
            only: ['proposals'],
            preserveUrl: true,
            data: {
                check: { file_id: fileId, document: document(), also: sent },
            },
            onCancelToken: (token) => (running = token),
            onFinish: () => (running = undefined),
        });
    }

    /**
     * Asks again after a wait, forgetting a wait already running.
     *
     * @param wait - how long to wait, in milliseconds
     */
    function schedule(wait: number): void {
        clearTimeout(timer);
        timer = setTimeout(ask, wait);
    }

    // Only what the person decides: the proposal coming back must not ask again
    watch(
        (): string => JSON.stringify([picks, document()]),
        () => {
            if (enabled()) {
                schedule(WAIT);
            }
        },
    );

    /**
     * Forgets the tick, the fields written and the card picked of a box.
     *
     * @param kind - the box of the card
     */
    function forget(kind: App.Enums.RecordKind): void {
        delete picks.records[kind];
        delete picks.targets.records[kind];
        delete picks.overrides.records[kind];
    }

    // Another kind of document asks another card: what the person did in the box of the old card, or in a box that
    // went away, would end up on a card that is not proposed any more. The expense and the event stay.
    watch(proposal, (now, before) => {
        if (!now || !before || now.cardType === before.cardType) {
            return;
        }

        const kinds = new Set(now.records.map((draft) => draft.kind));
        const known = new Set<App.Enums.RecordKind>([
            ...(Object.keys(picks.records) as App.Enums.RecordKind[]),
            ...(Object.keys(picks.targets.records) as App.Enums.RecordKind[]),
            ...(Object.keys(picks.overrides.records) as App.Enums.RecordKind[]),
        ]);

        for (const kind of known) {
            if (kind === 'card' || !kinds.has(kind)) {
                forget(kind);
            }
        }
    });

    onBeforeUnmount(() => {
        gone = true;
        cancel();
    });

    return {
        picks,
        also,
        changed,
        targetOf,
        setTarget,
        setTicked,
        overrideOf,
        setOverride,
        refresh: () => schedule(0),
        cancel,
    };
}
