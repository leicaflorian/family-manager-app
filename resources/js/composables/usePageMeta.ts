import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { computed, provide, toValue } from 'vue';
import type { Section } from '@/components/fm/navigation';
import { sectionLink } from '@/components/fm/navigation';
import { usePageTrail } from '@/composables/usePageTrail';
import type { TrailPiece } from '@/lib/pageTrail';
import type { PageInfoKey } from '@/lib/pageInfo';
import { PAGE_INFO } from '@/lib/titles';

/** A level above the page: a section of the registry (label and address from there) or a piece written out. */
export type ParentPiece = Section | TrailPiece;

/** What a page tells about itself. */
export type PageMetaOptions = {
    /** The title of the page: the `FmTitle`, the tab of the browser and the last piece of the trail. */
    title: MaybeRefOrGetter<string>;
    /** The tab of the browser, when it must say something else than the title (Home, whose title is the greeting). */
    head?: MaybeRefOrGetter<string>;
    /** The levels above the page, from the top; without one the page is a page of a section and writes no trail. */
    parent?: MaybeRefOrGetter<ParentPiece | ParentPiece[]>;
    /** The key of the explanation of the page (`pageInfo.ts`): `FmTitle` and `FmCardHeader` show its «i». */
    info?: PageInfoKey;
};

/** What `usePageMeta` gives back. */
export type PageMeta = {
    /** The title for `FmTitle`. */
    title: ComputedRef<string>;
    /** The title for `<Head>`. */
    head: ComputedRef<string>;
    /** The pieces written in the top bar: the parents, then the title; empty without a parent. */
    trail: ComputedRef<TrailPiece[]>;
};

/**
 * Title, tab title and trail of a page from one place: the parents of the registry of the sections give the
 * pieces above the title, so a page does not write them by hand. It writes the trail into the top bar (as
 * `usePageTrail`) and gives `info` to the `FmTitle` of the page.
 *
 * @param options - the title, the parents and the key of the explanation
 * @returns the title, the title for the tab and the trail
 */
export function usePageMeta(options: PageMetaOptions): PageMeta {
    const title = computed((): string => toValue(options.title));
    const head = computed((): string =>
        options.head === undefined ? title.value : toValue(options.head),
    );
    const trail = computed((): TrailPiece[] => {
        if (options.parent === undefined) {
            return [];
        }

        const parents = [toValue(options.parent)].flat();
        const pieces = parents.map((parent): TrailPiece =>
            typeof parent === 'string' ? sectionLink(parent) : parent,
        );

        return [...pieces, { label: title.value }];
    });

    provide(
        PAGE_INFO,
        computed(() => options.info),
    );

    if (options.parent !== undefined) {
        usePageTrail(() => trail.value);
    }

    return { title, head, trail };
}
