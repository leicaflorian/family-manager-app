import { computed, onUnmounted, ref } from 'vue';
import type { ComputedRef } from 'vue';
import { askToLeave, fingerprint, trackChanges } from '@/lib/unsavedChanges';

/** What a form gets to know and handle its changes not saved. */
export interface UnsavedChanges {
    /** Whether the values are not the ones the form opened with or last saved. */
    dirty: ComputedRef<boolean>;
    /**
     * Takes the values of now as the start: after saving, after *Cancel*, or
     * when the form shows another item.
     */
    reset: () => void;
    /**
     * Asks before closing a panel with changes; with none it does not ask.
     *
     * @returns true when the panel may close
     */
    confirmClose: () => Promise<boolean>;
}

/**
 * The changes not saved of a form: while it has some, the app asks before the
 * page changes, the tab closes or the panel of the form closes.
 *
 * @param values - gives the values of the form now
 * @param options - `active` says whether the form is in use: a form in a
 *   closed panel stays in the page, and has nothing to lose
 * @returns whether it has changes, and how to reset them or close the panel
 */
export function useUnsavedChanges(
    values: () => unknown,
    options: { active?: () => boolean } = {},
): UnsavedChanges {
    const active = options.active ?? ((): boolean => true);
    const start = ref(fingerprint(values()));
    const dirty = computed(
        (): boolean => active() && fingerprint(values()) !== start.value,
    );

    /** Takes the values of now as the start. */
    function reset(): void {
        start.value = fingerprint(values());
    }

    /**
     * Asks before closing a panel with changes.
     *
     * @returns true when the panel may close
     */
    async function confirmClose(): Promise<boolean> {
        if (!dirty.value) {
            return true;
        }

        const leave = await askToLeave();

        if (leave) {
            reset();
        }

        return leave;
    }

    onUnmounted(trackChanges(() => dirty.value, reset));

    return { dirty, reset, confirmClose };
}
