import { trans, transChoice } from 'laravel-vue-i18n';
import { longMonth, monthName } from '@/lib/budget';
import { formatDayMonth, formatMoney } from '@/lib/format';
import { create as createFilter } from '@/routes/budget/bank/filters';

/** The fields of a group the words below read. */
type Group = Pick<
    App.Data.BankGroupData,
    | 'key'
    | 'kind'
    | 'count'
    | 'min'
    | 'max'
    | 'from'
    | 'to'
    | 'proposal_action'
    | 'proposal_title'
    | 'proposal_detail'
>;

/**
 * How many movements a group has and their amounts: «11 movements · from 10,00 € to 50,00 €», «3 incomes · 407,60 €
 * each».
 *
 * @param group - the group
 * @returns the line, in the language of the page
 */
export function groupAmounts(group: Group): string {
    const income = group.kind === 'income';
    const min = formatMoney(group.min, 'EUR');
    const max = formatMoney(group.max, 'EUR');

    if (group.min === group.max) {
        return transChoice(
            income
                ? '{1} :count income · :amount|[2,*] :count incomes · :amount each'
                : '{1} :count movement · :amount|[2,*] :count movements · :amount each',
            group.count,
            { amount: min },
        );
    }

    return transChoice(
        income
            ? '{1} :count income · from :min to :max|[2,*] :count incomes · from :min to :max'
            : '{1} :count movement · from :min to :max|[2,*] :count movements · from :min to :max',
        group.count,
        { min, max },
    );
}

/**
 * The months of a group, from the oldest movement to the newest: «from September to November», «in November», with the
 * year when the two are of different years.
 *
 * @param group - the group
 * @returns the line, in the language of the page
 */
export function groupMonths(group: Group): string {
    const sameYear = group.from.slice(0, 4) === group.to.slice(0, 4);
    const name = (day: string): string =>
        sameYear ? longMonth(day) : monthName(day, false);

    return group.from.slice(0, 7) === group.to.slice(0, 7)
        ? trans('in :month', { month: name(group.to) })
        : trans('from :from to :to', {
              from: name(group.from),
              to: name(group.to),
          });
}

/**
 * The days of a group, as the panel of the group writes them: «between 02/09 and 24/11».
 *
 * @param group - the group
 * @returns the line, in the language of the page
 */
export function groupDays(group: Group): string {
    return trans('between :from and :to', {
        from: formatDayMonth(group.from),
        to: formatDayMonth(group.to),
    });
}

/**
 * What the system proposes for a group, in a few words starting in lower case: «create the expense «Ricarica
 * Satispay» · Varie · Satispay», «mark the planned income «Assegno Unico» as received».
 *
 * @param group - the group
 * @returns the words, null when no expense looks like the movements
 */
export function groupProposal(group: Group): string | null {
    switch (group.proposal_action) {
        case 'create': {
            const what = trans('create the expense «:title»', {
                title: group.proposal_title ?? '',
            });

            return group.proposal_detail
                ? `${what} · ${group.proposal_detail}`
                : what;
        }
        case 'pay':
            return group.kind === 'income'
                ? trans('mark the planned income «:title» as received', {
                      title: group.proposal_detail ?? '',
                  })
                : trans('pay the planned expense «:title»', {
                      title: group.proposal_detail ?? '',
                  });
        default:
            return null;
    }
}

/**
 * The form of the new filter of a group, filled in with the proposal. Without a proposal it asks the AI for title and
 * category (`ai=1`); with one the form needs nothing more.
 *
 * @param group - the group
 * @returns the address
 */
export function groupFilterUrl(group: Group): string {
    return createFilter({
        query:
            group.proposal_action === null
                ? { group: group.key, ai: 1 }
                : { group: group.key },
    }).url;
}
