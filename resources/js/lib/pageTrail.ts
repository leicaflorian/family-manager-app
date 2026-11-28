import type { InjectionKey, Ref } from 'vue';
import { ref } from 'vue';
import { SECTIONS } from '@/components/fm/navigation';

/** One piece of the trail in the top bar: the last piece has no `href`. */
export type TrailPiece = { label: string; href?: string };

/** The trail the page writes and the top bar reads. */
export type TrailState = {
    /** The pieces now shown. */
    pieces: Ref<TrailPiece[]>;
    /** Replaces the pieces. */
    set(pieces: TrailPiece[]): void;
    /** Empties the trail. */
    clear(): void;
};

/**
 * Creates the state shared by the layout (which provides it), the top bar (which reads it) and the
 * pages (which write it with `usePageTrail`).
 *
 * @returns a new, empty trail state
 */
export function createTrailState(): TrailState {
    const pieces = ref<TrailPiece[]>([]);

    return {
        pieces,
        set(next: TrailPiece[]): void {
            pieces.value = next;
        },
        clear(): void {
            pieces.value = [];
        },
    };
}

/** Injection key of the trail state, provided by the layout. */
export const TRAIL_KEY: InjectionKey<TrailState> = Symbol('page-trail');

/** The pages of a section and its top tabs: they show no trail. They come from the registry of the sections. */
export const SECTION_PAGES: readonly string[] = SECTIONS.flatMap(
    (entry) => entry.pages,
);

/**
 * Tells whether an Inertia page is the page of a section (or one of its top tabs).
 *
 * @param component - the Inertia component name, such as `family/People`
 * @returns true for a section page
 */
export function isSectionPage(component: string): boolean {
    return SECTION_PAGES.includes(component);
}

/** The pages under a section in the sidebar: they show their trail, and the tools of a section page beside the title. */
export const SUBPAGES: readonly string[] = SECTIONS.flatMap(
    (entry) => entry.subpages ?? [],
);

/**
 * Tells whether a page keeps the search and the bell beside its title on the phone: the section pages and the pages
 * under a section in the sidebar.
 *
 * @param component - the Inertia component name, such as `budget/BankTransactions`
 * @returns true when the title has the tools
 */
export function hasTitleTools(component: string): boolean {
    return isSectionPage(component) || SUBPAGES.includes(component);
}
