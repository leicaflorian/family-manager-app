import { trans } from 'laravel-vue-i18n';

/**
 * How a link between a bank transaction and an expense or income came, in words: from the filter that took it, a
 * rule, a proposal the person accepted, the person's own choice, or an expense that was already there. One place for
 * the line of the movement page and the line under a row of the list *All*.
 *
 * @param how - a `BankMatchSource` value
 * @param filterName - the name of the filter that took it, when one did
 * @param lower - the words for the middle of a line («from a rule») instead of the start of one («From a rule»)
 * @returns the words
 */
export function originWords(
    how: string,
    filterName: string | null | undefined,
    lower = false,
): string {
    switch (how) {
        case 'rule':
            if (filterName) {
                return lower
                    ? trans('from the filter :name', { name: filterName })
                    : trans('From the filter :name', { name: filterName });
            }

            return lower ? trans('from a rule') : trans('From a rule');
        case 'proposed':
            return lower
                ? trans('proposal accepted')
                : trans('Proposal accepted');
        case 'assigned':
            return lower ? trans('chosen by hand') : trans('Chosen by hand');
        case 'created':
            return lower
                ? trans('created from here')
                : trans('Created from here');
        default:
            return lower
                ? trans('already recorded, now linked')
                : trans('Already recorded, now linked');
    }
}
