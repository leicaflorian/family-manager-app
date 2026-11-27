import { trans } from 'laravel-vue-i18n';

/**
 * The question and the words of the confirmation of *It was not this one*, for what taking the link back does: a
 * payment the bank recorded is removed and its due date is to be paid again (`delete`); a link to an expense or income
 * that was already there only comes off and the movement stays (`detach`).
 *
 * @param kind - `expense` or `income`
 * @param undo - what the server says the undo does (`delete` or `detach`)
 * @returns the question and the explanation to show
 */
export function undoWords(
    kind: string | undefined,
    undo: string | null | undefined,
): { question: string; text: string } {
    const income = kind === 'income';

    if (undo === 'detach') {
        return {
            question: trans('Take off this link?'),
            text: income
                ? trans(
                      'The income stays as it is. The movement goes back to be sorted.',
                  )
                : trans(
                      'The expense stays as it is. The movement goes back to be sorted.',
                  ),
        };
    }

    return {
        question: trans('Take back this payment?'),
        text: income
            ? trans(
                  'The income is removed and the due date is to be paid again.',
              )
            : trans(
                  'The expense is removed and the due date is to be paid again.',
              ),
    };
}
