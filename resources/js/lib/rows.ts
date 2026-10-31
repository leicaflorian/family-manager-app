import { cn } from './utils';

/**
 * The classes of a list row that leads somewhere (a document, a payment):
 * the hand and the soft blue on hover and on focus, the same in every list.
 */
export const rowLink = cn(
    'cursor-pointer hover:bg-action-soft focus-visible:bg-action-soft focus-visible:outline-none',
);
