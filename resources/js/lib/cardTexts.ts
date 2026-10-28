/**
 * The short texts that count cards, in the language of the page: «3 cards»,
 * «1 overdue». They are the same on the list of the areas and on the page of
 * an area.
 */
import { transChoice } from 'laravel-vue-i18n';

/**
 * How many cards.
 *
 * @param count - the number of cards
 * @returns for example «3 cards», or «No cards» for zero
 */
export function cardCountText(count: number): string {
    return transChoice(
        '{0} No cards|{1} :count card|[2,*] :count cards',
        count,
        { count: String(count) },
    );
}

/**
 * How many cards are overdue.
 *
 * @param count - the number of overdue cards
 * @returns for example «1 overdue»
 */
export function overdueText(count: number): string {
    return transChoice('{1} :count overdue|[2,*] :count overdue', count, {
        count: String(count),
    });
}

/**
 * The line under the name of an area.
 *
 * @param count - the number of cards
 * @param overdue - how many of them are overdue
 * @returns for example «2 cards · 1 overdue», without the second part if none is
 */
export function areaSummary(count: number, overdue: number): string {
    return overdue > 0
        ? `${cardCountText(count)} · ${overdueText(overdue)}`
        : cardCountText(count);
}
