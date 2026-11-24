import type { InjectionKey, Ref } from 'vue';
import type { PageInfoKey } from '@/lib/pageInfo';

/**
 * Given by a frame that has its own title on top (`FmSettingsPage` in the wide
 * layout): while true, the `FmTitle` of the page inside is the title of a
 * section, an `h2` without the link back.
 */
export const SECTION_TITLE: InjectionKey<Readonly<Ref<boolean>>> =
    Symbol('section-title');

/**
 * Given by `usePageMeta` to the `FmTitle` of the page: the key of the page's explanation (the «i»), if the page
 * has one.
 */
export const PAGE_INFO: InjectionKey<Readonly<Ref<PageInfoKey | undefined>>> =
    Symbol('page-info');
