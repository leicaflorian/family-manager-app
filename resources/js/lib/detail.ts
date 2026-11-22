import { cn } from './utils';

/**
 * The ‹ and › buttons at the top of a column beside a list (a document, a
 * drawing): a square on the page background, dimmed at an end of the list.
 */
export const detailStep = cn(
    'flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-md border-0 bg-canvas text-ink disabled:cursor-default disabled:opacity-40',
);
