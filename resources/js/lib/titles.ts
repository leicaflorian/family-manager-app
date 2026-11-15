import type { InjectionKey, Ref } from 'vue';

/**
 * Given by a frame that has its own title on top (`FmSettingsPage` in the wide
 * layout): while true, the `FmTitle` of the page inside is the title of a
 * section, an `h2` without the link back.
 */
export const SECTION_TITLE: InjectionKey<Readonly<Ref<boolean>>> =
    Symbol('section-title');
