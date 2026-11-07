/**
 * The texts of the boxes of *What I do too* in *Import*: what the reading
 * does with the expense or income, the health event and the cards of a file,
 * as the boxes of Directus said it (`module.vue`).
 */
import { trans, transChoice } from 'laravel-vue-i18n';
import { formatDate, formatEuro } from '@/lib/format';

/** What a box is linked to: the one found (`auto`), a new one whatever is found, or the one picked by hand. */
export type ImportTarget = 'auto' | 'new' | number;

/** The box of the policy taken from another file or from one already registered. */
export type PolicyBox = {
    label: string;
    hint?: string;
    warnings: string[];
    ticked: boolean;
    disabled: boolean;
};

/** Why the box of a card starts unticked, when it is not for a missing field. */
const RECORD_UNTICKED: Record<App.Enums.RecordKind, string> = {
    organization:
        'A shop on a receipt does not become an organization by itself: tick the box if you want to keep it among the organizations.',
    vehicle: 'Tick the box to create the vehicle.',
    utility: 'Tick the box to create the utility.',
    policy: 'Tick the box to create the policy.',
    belonging:
        'Under 100 € a purchase does not become an item by itself: tick the box if you want to keep it among the items, with the warranty.',
    maintenance: 'Tick the box to create the intervention.',
};

/** How a box speaks of a card already there, by its kind: `Link to the vehicle "…"`. */
const LINK_TO: Record<App.Enums.RecordKind, string> = {
    organization: 'Link to the organization ":title"',
    vehicle: 'Link to the vehicle ":title"',
    utility: 'Link to the utility ":title"',
    policy: 'Link to the policy ":title"',
    belonging: 'Link to the item ":title"',
    maintenance: 'Link to the intervention ":title"',
};

/**
 * Whether *To pay* is in effect for the expense: the choice of the person,
 * otherwise what the proposal suggests. Never without a deadline to propose.
 *
 * @param movement - the expense or income of the proposal
 * @param due - *To pay* (true), *Already paid* (false), or not chosen (null)
 * @returns true when the expense is to pay
 */
export function dueInEffect(
    movement: App.Data.Reading.MovementProposalData,
    due: boolean | null | undefined,
): boolean {
    return movement.due !== null && (due ?? movement.due.suggested);
}

/**
 * The text of the box of the expense or income.
 *
 * @param movement - the expense or income of the proposal
 * @param due - whether *To pay* is in effect (see `dueInEffect`)
 * @returns for example `Create the expense of 120,00 €`
 */
export function movementLabel(
    movement: App.Data.Reading.MovementProposalData,
    due = false,
): string {
    const income = movement.values.kind === 'income';
    const amount = movement.values.amount;
    const euro = amount === null ? '' : formatEuro(amount);

    if (movement.duplicate) {
        return trans(
            income
                ? 'Link to the income ":title" of :date'
                : 'Link to the expense ":title" of :date',
            named(movement.duplicate),
        );
    }

    const plan = movement.due?.plan;

    if (due && movement.due?.mode === 'existing' && plan) {
        return trans(
            'Link to the expense to pay ":title" of :date',
            named(plan),
        );
    }

    if (due && movement.due?.mode === 'exception' && plan) {
        const deadline = {
            title: plan.title,
            date: formatDate(movement.due.occurrence ?? plan.date),
        };

        return euro === ''
            ? trans('Update the deadline of ":title" of :date', deadline)
            : trans('Update the deadline of ":title" of :date: :amount', {
                  ...deadline,
                  amount: euro,
              });
    }

    if (due) {
        return euro === ''
            ? trans('Create the expense to pay')
            : trans('Create the expense to pay of :amount', { amount: euro });
    }

    if (movement.pays) {
        return trans(
            'Record the payment of ":title" of :date',
            named(movement.pays),
        );
    }

    if (euro === '') {
        return trans(income ? 'Create the income' : 'Create the expense');
    }

    return trans(
        income
            ? 'Create the income of :amount'
            : 'Create the expense of :amount',
        { amount: euro },
    );
}

/**
 * The text of the box of the health event.
 *
 * @param event - the event of the proposal
 * @returns `Create the health event`, or the link to the one already there
 */
export function eventLabel(event: App.Data.Reading.EventProposalData): string {
    return event.existing
        ? trans('Link to the event ":title" of :date', named(event.existing))
        : trans('Create the health event');
}

/**
 * The text of the box of a card.
 *
 * @param draft - the card of the proposal
 * @returns `Create the organization Studio Ferri`, or the link to the one already there
 */
export function recordLabel(draft: App.Data.Reading.RecordDraftData): string {
    return draft.existing
        ? trans(LINK_TO[draft.kind], { title: draft.existing.title })
        : trans('Create :title', { title: draft.title });
}

/**
 * The title and the date of something already registered, for the texts.
 *
 * @param item - its title and date
 * @returns the replacements of `:title` and `:date`
 */
function named(item: { title: string; date: string }): {
    title: string;
    date: string;
} {
    return { title: item.title, date: formatDate(item.date) };
}

/**
 * The line under the box of the expense or income: why it starts unticked,
 * what it is linked to, or its category and person.
 *
 * @param movement - the expense or income of the proposal
 * @param state - whether it is ticked, *To pay* is in effect, and its target
 * @param subtitle - its category and person, as the page names them
 * @returns the line, if there is one
 */
export function movementHint(
    movement: App.Data.Reading.MovementProposalData,
    state: { ticked: boolean; due: boolean; target: ImportTarget },
    subtitle: string,
): string | undefined {
    const income = movement.values.kind === 'income';

    if (!state.ticked && !movement.suggested) {
        return trans(
            'It does not look like a payment: tick the box to create the expense anyway.',
        );
    }

    if (state.ticked && movement.duplicate) {
        if (state.target !== 'auto') {
            return trans(
                income
                    ? 'The document is linked to the income you chose.'
                    : 'The document is linked to the expense you chose.',
            );
        }

        return trans(
            income
                ? 'The income is already registered (same commitment, or same amount and organization on close dates): instead of creating another, the document is linked to that one.'
                : 'The expense is already registered (same commitment, or same amount and organization on close dates): instead of creating another, the document is linked to that one.',
        );
    }

    if (state.ticked && movement.pays && !state.due) {
        return trans(
            'The receipt pays an expense to pay: the payment is recorded on that one.',
        );
    }

    return subtitle === '' ? undefined : subtitle;
}

/**
 * What to know before leaving the expense or income ticked: what it misses,
 * what changes on the expense found, how many others were as close.
 *
 * @param movement - the expense or income of the proposal
 * @param state - whether it is ticked and *To pay* is in effect
 * @returns the lines, none while it is unticked
 */
export function movementWarnings(
    movement: App.Data.Reading.MovementProposalData,
    state: { ticked: boolean; due: boolean },
): string[] {
    if (!state.ticked) {
        return [];
    }

    if (movement.duplicate) {
        return [
            ...movement.changes.map((change) =>
                trans('On the expense changes: :change', { change }),
            ),
            ...movement.warnings,
            ...(movement.others > 0
                ? [
                      transChoice(
                          '{1} There is :count other expense with the same amount close to this date: if it is not the right one, choose another.|[2,*] There are :count other expenses with the same amount close to this date: if it is not the right one, choose another.',
                          movement.others,
                      ),
                  ]
                : []),
        ];
    }

    return [
        ...(movement.blocked ? [movement.blocked] : []),
        ...movement.warnings,
        ...(movement.pays && !state.due && movement.pays.others > 0
            ? [
                  transChoice(
                      '{1} There is :count other expense to pay with the same amount close to this date.|[2,*] There are :count other expenses to pay with the same amount close to this date.',
                      movement.pays.others,
                  ),
              ]
            : []),
    ];
}

/**
 * The line under the box of the health event: why it starts unticked, what
 * it is linked to, or its title, person and date.
 *
 * @param event - the event of the proposal
 * @param state - whether it is ticked, and its target
 * @param subtitle - its title, person and date, as the page names them
 * @returns the line, if there is one
 */
export function eventHint(
    event: App.Data.Reading.EventProposalData,
    state: { ticked: boolean; target: ImportTarget },
    subtitle: string,
): string | undefined {
    if (!state.ticked && !event.suggested) {
        return trans(
            'A prescription usually comes from a visit already registered: tick the box if you want its event anyway.',
        );
    }

    if (state.ticked && event.existing) {
        return trans(
            state.target === 'auto'
                ? 'There is already an event of this person (same day, or same place and same type a few days apart): instead of creating another, the document is linked to that one, and the reading only fills its empty fields.'
                : 'The document is linked to the event you chose, and the reading only fills its empty fields.',
        );
    }

    return subtitle === '' ? undefined : subtitle;
}

/**
 * What a new health event misses, while it is ticked.
 *
 * @param event - the event of the proposal
 * @param ticked - whether its box is ticked
 * @returns the lines
 */
export function eventWarnings(
    event: App.Data.Reading.EventProposalData,
    ticked: boolean,
): string[] {
    return ticked && !event.existing && event.blocked ? [event.blocked] : [];
}

/**
 * The line under the box of a card: why it starts unticked, or what it is
 * linked to.
 *
 * @param draft - the card of the proposal
 * @param state - whether it is ticked, and its target
 * @returns the line, if there is one
 */
export function recordHint(
    draft: App.Data.Reading.RecordDraftData,
    state: { ticked: boolean; target: ImportTarget },
): string | undefined {
    if (!state.ticked && !draft.suggested && !draft.existing) {
        return trans(RECORD_UNTICKED[draft.kind]);
    }

    if (state.ticked && draft.existing) {
        return trans(
            state.target === 'auto'
                ? 'There is already a card that matches: instead of creating another, the document is linked to that one.'
                : 'The document is linked to the card you chose.',
        );
    }

    return undefined;
}

/**
 * What a new card misses, while it is ticked.
 *
 * @param draft - the card of the proposal
 * @param ticked - whether its box is ticked
 * @returns the lines
 */
export function recordWarnings(
    draft: App.Data.Reading.RecordDraftData,
    ticked: boolean,
): string[] {
    if (!ticked || draft.existing || draft.missing.length === 0) {
        return [];
    }

    return [
        transChoice(
            '{1} To create it :fields is missing.|[2,*] To create it :fields are missing.',
            draft.missing.length,
            { fields: draft.missing.join(', ') },
        ),
    ];
}

/**
 * The box of the policy of an insurance paper without a number: the one a
 * file of the import creates or one already registered, or none when more
 * fit or the person left it.
 *
 * @param policy - the policy of the proposal
 * @param choice - `auto`, `none` or the `key` of a policy
 * @returns the box, or undefined when the paper needs no policy or none is found
 */
export function policyBox(
    policy: App.Data.Reading.ProposalData['policy'],
    choice: string,
): PolicyBox | undefined {
    if (!policy?.need) {
        return undefined;
    }

    if (choice === 'none') {
        return {
            label: trans('Link to a policy'),
            hint: trans('The document is not linked to a policy.'),
            warnings: [],
            ticked: false,
            disabled: false,
        };
    }

    const giver = policy.giver;

    if (giver) {
        const words = { number: giver.number, file: giver.fileName ?? '' };
        let hint = trans(
            'The document is linked to the policy :number, already registered.',
            words,
        );

        if (giver.creates) {
            hint = trans(
                'The document is linked to the policy :number, which the file :file creates.',
                words,
            );
        } else if (giver.fileName) {
            hint = trans(
                'The document is linked to the policy :number, already registered, found from the file :file.',
                words,
            );
        }

        return {
            label: trans('Link to the policy :number', words),
            hint,
            warnings: [],
            ticked: true,
            disabled: false,
        };
    }

    if (policy.many) {
        return {
            label: trans('Link to a policy'),
            warnings: [
                trans(
                    'There are more policies that fit in this import: choose the one of the document.',
                ),
            ],
            ticked: false,
            disabled: true,
        };
    }

    return undefined;
}
