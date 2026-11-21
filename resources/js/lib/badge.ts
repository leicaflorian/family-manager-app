/**
 * The number on a badge of the app: nothing is shown at zero, so the caller
 * hides it; above 99 it reads «99+».
 *
 * @param count - how many
 * @returns the text of the badge
 */
export function countBadge(count: number): string {
    return count > 99 ? '99+' : String(count);
}

/**
 * The classes of the red dot with the number on a round button (the bell, the
 * link to *To sort*): the caller adds where it sits.
 */
export const COUNT_BADGE_CLASS =
    'absolute top-1 right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-overdue px-1 text-tiny text-on-action';
