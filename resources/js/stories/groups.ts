/**
 * The groups of the components, in the order Storybook shows them, with their
 * labels: the label is the first part of the title of a story, as
 * `Basics/FmNotice`. A component names its group with the `@group` tag of its
 * TSDoc.
 */
import { trans } from 'laravel-vue-i18n';

/** The groups of the components, in the order they are shown. */
export const groups = [
    'basics',
    'navigation',
    'rows',
    'panels',
    'forms',
    'figures',
    'files',
    'sections',
] as const;

/** One group of the components. */
export type Group = (typeof groups)[number];

/**
 * The label of a group, in the language of the page.
 *
 * @param group - The group.
 * @returns The translated label.
 */
export function groupLabel(group: Group): string {
    switch (group) {
        case 'basics':
            return trans('Basics');
        case 'navigation':
            return trans('Navigation');
        case 'rows':
            return trans('Rows');
        case 'panels':
            return trans('Panels');
        case 'forms':
            return trans('Forms');
        case 'figures':
            return trans('Figures');
        case 'files':
            return trans('Files');
        case 'sections':
            return trans('Page sections');
    }
}
