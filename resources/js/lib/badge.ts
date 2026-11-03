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
