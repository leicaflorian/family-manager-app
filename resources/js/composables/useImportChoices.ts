import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import type { ComputedRef } from 'vue';
import type { ImportTarget } from '@/lib/alsoDo';
import type { DocumentPayload } from '@/lib/documents';

/** The targets of the expense, the event and each card. */
export type ImportTargets = {
    movement: ImportTarget;
    event: ImportTarget;
    records: Partial<Record<App.Enums.RecordKind, ImportTarget>>;
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
    /** *To pay* (true) or *Already paid* (false); null follows the proposal. */
    due: boolean | null;
    /** `auto`, `none` or the `key` of the policy to take. */
    policy: string;
};

/** What one file does too, as the server reads it (`App\Data\Reading\ImportChoices`, without the document). */
export type ImportAlso = {
    movement: boolean;
    event: boolean;
    records: App.Enums.RecordKind[];
    targets: ImportTargets;
    /** Only when the person chose: missing, the server decides. */
    due?: boolean;
    policy: string;
};

/** The choices of one file, and how to ask its proposal again. */
export interface ImportChoices {
    /** What the person changed: the page writes in it. */
    picks: ImportPicks;
    /** What is sent as `also`: the picks over the proposal; undefined without a proposal. */
    also: ComputedRef<ImportAlso | undefined>;
    /** Asks the proposal again now, without waiting. */
    refresh: () => void;
}

/** How long to wait after the last change before asking the proposal again, in milliseconds. */
const WAIT = 400;

/**
 * The choices of *What I do too* of one file in *Import*: the boxes, the
 * expense, event or card picked by hand, *To pay* and the policy. Every change,
 * and every change of the document of the file, asks the server the proposal
 * again 400 ms after the last one, reloading only `proposals` with
 * `?check={file_id, document, also}` and without changing the address; the
 * server merges it with the others. Nothing is asked after the page went away.
 *
 * @param fileId - the file
 * @param proposal - gives the proposal of the file, as the server sent it last
 * @param options - `document` gives what the form of the document sends;
 *   `enabled` says whether changes ask again (not while the forms fill in)
 * @returns the picks, what to send and `refresh`
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
        due: null,
        policy: 'auto',
    });

    const also = computed((): ImportAlso | undefined => {
        const current = proposal();

        if (!current) {
            return undefined;
        }

        return {
            movement: picks.movement ?? current.movement?.suggested ?? false,
            event: picks.event ?? current.event?.suggested ?? false,
            records: current.records
                .filter((draft) => picks.records[draft.kind] ?? draft.suggested)
                .map((draft) => draft.kind),
            targets: {
                movement: picks.targets.movement,
                event: picks.targets.event,
                records: { ...picks.targets.records },
            },
            ...(picks.due === null ? {} : { due: picks.due }),
            policy: picks.policy,
        };
    });

    let timer: ReturnType<typeof setTimeout> | undefined;
    let gone = false;

    /** Asks the server the proposal of the file with the choices of now. */
    function ask(): void {
        const sent = also.value;

        if (gone || !sent) {
            return;
        }

        router.reload({
            only: ['proposals'],
            preserveUrl: true,
            data: {
                check: { file_id: fileId, document: document(), also: sent },
            },
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

    onBeforeUnmount(() => {
        gone = true;
        clearTimeout(timer);
    });

    return { picks, also, refresh: () => schedule(0) };
}
