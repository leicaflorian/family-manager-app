/**
 * The changes not saved yet in the forms of the page: a form says whether it
 * has some (`useUnsavedChanges`), and before the page changes the app asks
 * whether to leave without saving. The question is one for the whole app,
 * shown by `AppLayout`.
 */
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/**
 * The forms open now: the function that says whether one has changes, with
 * the one that takes its values of now as the start.
 */
const forms = new Map<() => boolean, () => void>();

/** What the person answers to the question still open, if one is. */
let pending: ((leave: boolean) => void) | null = null;

/** The question «Leave without saving?», open or closed. */
export const leaveQuestion = reactive({ open: false });

/**
 * A value as it is compared: nothing written is the same whether it is empty
 * text, `null` or an empty list; a number is the same as the same number
 * written as text (a currency field gives one or the other); a file is its
 * name and size, because choosing it again makes a new object.
 *
 * @param value - a value of a form
 * @returns the value to compare, `undefined` for nothing written
 */
function comparable(value: unknown): unknown {
    if (value === null || value === undefined || value === '') {
        return undefined;
    }

    if (typeof File !== 'undefined' && value instanceof File) {
        return `file:${value.name}:${value.size}`;
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? undefined : value.map(comparable);
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>)
            .map(([key, item]): [string, unknown] => [key, comparable(item)])
            .filter(([, item]) => item !== undefined)
            .sort(([a], [b]) => a.localeCompare(b));

        return entries.length === 0 ? undefined : Object.fromEntries(entries);
    }

    if (
        typeof value === 'string' &&
        value.trim() !== '' &&
        !Number.isNaN(Number(value))
    ) {
        return Number(value);
    }

    return value;
}

/**
 * The values of a form written as one text, to keep the ones it opened with and
 * compare them later: what changes only the way of writing nothing, a number or
 * a file gives the same text.
 *
 * @param values - the values of a form
 * @returns the same text for values that are the same for the person
 */
export function fingerprint(values: unknown): string {
    return JSON.stringify(comparable(values)) ?? '';
}

/**
 * Whether two sets of values of a form are the same for the person.
 *
 * @param before - the values when the form opened or was last saved
 * @param now - the values now
 * @returns true when nothing has changed
 */
export function sameValues(before: unknown, now: unknown): boolean {
    return fingerprint(before) === fingerprint(now);
}

/**
 * Adds a form to the open ones.
 *
 * @param dirty - says whether the form has changes not saved
 * @param reset - takes the values of now as the start, giving up the changes
 * @returns the function that removes the form, when it closes
 */
export function trackChanges(
    dirty: () => boolean,
    reset: () => void = (): void => undefined,
): () => void {
    forms.set(dirty, reset);

    return (): void => {
        forms.delete(dirty);
    };
}

/**
 * Whether an open form has changes not saved.
 *
 * @returns true when leaving now loses something
 */
export function hasChanges(): boolean {
    return [...forms.keys()].some((dirty) => dirty());
}

/**
 * Gives up the changes of every open form: *Cancel* is a choice, not a mistake.
 */
export function discardChanges(): void {
    for (const reset of forms.values()) {
        reset();
    }
}

/**
 * Asks whether to leave without saving.
 *
 * @returns true when the person leaves, false when they stay
 */
export function askToLeave(): Promise<boolean> {
    // A question still open is answered «stay»: only the last one counts
    pending?.(false);
    leaveQuestion.open = true;

    return new Promise((resolve) => {
        pending = resolve;
    });
}

/**
 * Closes the question with the answer of the person.
 *
 * @param leave - true to leave without saving, false to stay
 */
export function answerLeave(leave: boolean): void {
    const resolve = pending;
    pending = null;
    leaveQuestion.open = false;
    resolve?.(leave);
}

/**
 * Asks before a visit of Inertia to another page and before the browser closes
 * or reloads the tab, while a form has changes. Saving, deleting, a reload of
 * the page and the visits prepared in advance go through: they do not throw
 * the form away. A click on an element with `data-discard-changes` (*Cancel*)
 * gives the changes up without asking.
 *
 * @returns the function that stops asking
 */
export function guardVisits(): () => void {
    // The visit repeated after «Leave without saving» does not ask again
    let leaving = false;

    const stopVisits = router.on('before', (event) => {
        const { visit } = event.detail;

        if (leaving) {
            leaving = false;

            return;
        }

        if (
            visit.method !== 'get' ||
            visit.prefetch ||
            visit.only.length > 0 ||
            visit.except.length > 0 ||
            visit.url.href === window.location.href ||
            !hasChanges()
        ) {
            return;
        }

        // `before` is a cancelable DOM event: preventing it ends the visit before any request
        event.preventDefault();

        void askToLeave().then((leave) => {
            if (!leave) {
                return;
            }

            leaving = true;
            router.visit(visit.url, {
                method: visit.method,
                data: visit.data,
                replace: visit.replace,
                preserveScroll: visit.preserveScroll,
                preserveState: visit.preserveState,
                headers: visit.headers,
            });
        });
    });

    /**
     * Asks the browser for its own question: it does not show a text of the app.
     *
     * @param event - the tab is closing or reloading
     */
    function onBeforeUnload(event: BeforeUnloadEvent): void {
        if (hasChanges()) {
            event.preventDefault();
        }
    }

    /**
     * Gives up the changes at a click on *Cancel*. It listens while the click
     * goes down the page, before the Link of Inertia starts its visit: the Link
     * does not call a `@click` of its own.
     *
     * @param event - a click on the page
     */
    function onClick(event: MouseEvent): void {
        if (
            event.target instanceof Element &&
            event.target.closest('[data-discard-changes]')
        ) {
            discardChanges();
        }
    }

    window.addEventListener('beforeunload', onBeforeUnload);
    document.addEventListener('click', onClick, true);

    return (): void => {
        stopVisits();
        window.removeEventListener('beforeunload', onBeforeUnload);
        document.removeEventListener('click', onClick, true);
    };
}
