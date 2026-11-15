import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/**
 * The version of the app and the notes of the panel *What's new*, from the shared props: the version
 * comes with every page, the notes only when the panel asks. The only place that reloads `release_notes`.
 *
 * @returns the version (`dev` when the code has none), the notes and `loadNotes`
 */
export function useReleaseNotes(): {
    release: ComputedRef<string>;
    notes: ComputedRef<App.Data.ReleaseNoteData[] | undefined>;
    loadNotes: () => void;
} {
    const page = usePage();

    return {
        release: computed((): string => page.props.release ?? 'dev'),
        notes: computed(
            (): App.Data.ReleaseNoteData[] | undefined =>
                page.props.release_notes,
        ),
        /** Asks the server for the notes of the versions, without changing the address. */
        loadNotes: (): void => {
            router.reload({ only: ['release_notes'], preserveUrl: true });
        },
    };
}
