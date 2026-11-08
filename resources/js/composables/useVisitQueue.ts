/** What `useVisitQueue` gives to the caller. */
export type UseVisitQueue = {
    /**
     * Runs a visit now, or after the ones already on their way.
     *
     * @param start - starts the visit and calls `done` when it finishes (Inertia's `onFinish`, which also runs after an error or a cancel)
     */
    enqueue: (start: (done: () => void) => void) => void;
};

/**
 * A line of Inertia visits, one at a time. Inertia cancels a visit still on its way when another starts, but
 * both reach the server in any order, and one could save old values; so each visit starts when the one
 * before it finishes, and reads what it sends when it starts, so it sends the latest values.
 *
 * @returns the function that puts a visit in the line
 */
export function useVisitQueue(): UseVisitQueue {
    /** Visits waiting for the one on its way; each starts when the one before it finishes. */
    const queue: Array<() => void> = [];
    let busy = false;

    /** Starts the next visit in the queue, or frees the line when there is none. */
    function next(): void {
        const start = queue.shift();

        busy = start !== undefined;
        start?.();
    }

    function enqueue(start: (done: () => void) => void): void {
        queue.push(() => start(next));

        if (!busy) {
            next();
        }
    }

    return { enqueue };
}
