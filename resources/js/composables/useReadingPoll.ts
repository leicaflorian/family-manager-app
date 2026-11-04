import { usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';

/** How often a page asks for the state of the readings, in milliseconds. */
const POLL_INTERVAL = 2000;

/**
 * Keeps asking the server for the state of the readings while one runs: a page that shows files being read
 * calls it with the props that carry that state, so what the person is writing is not reloaded. It stops by
 * itself when no reading runs and when the page goes away.
 *
 * @param running - whether a reading is queued or running
 * @param only - the props to ask for
 */
export function useReadingPoll(running: () => boolean, only: string[]): void {
    const poll = usePoll(
        POLL_INTERVAL,
        { only, preserveUrl: true },
        { autoStart: false },
    );

    watch(running, (reading) => (reading ? poll.start() : poll.stop()), {
        immediate: true,
    });
}
