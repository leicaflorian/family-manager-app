/**
 * The texts of the boxes of *What I do too* in *Import*: what the reading
 * does with the expense or income, the health event and the cards of a file,
 * as the boxes of Directus said it (`module.vue`).
 */
import { trans, transChoice } from 'laravel-vue-i18n';
import { formatDate, formatEuro } from '@/lib/format';

/** What a box is linked to: the one found (`auto`), a new one whatever is found, or the one picked by hand. */
export type ImportTarget = 'auto' | 'new' | number;

/** A box of *What I do too* that links to something: the expense or income, the event, or a card. */
export type AlsoSide = 'movement' | 'event' | App.Enums.RecordKind;

/** Something a box can be linked to instead: a card has a label, an expense a title, a date and an amount. */
export type Candidate = {
    id: number;
    label?: string;
    title?: string;
    date?: string;
    amount?: number;
};

/** One of the two choices under the box of the expense. */
export type DueChoice = { label: string; due: boolean; checked: boolean };

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

    const lead = movement.values.lead?.title;

    if (lead) {
        return euro === ''
            ? trans(
                  income
                      ? 'Create the income (:lead)'
                      : 'Create the expense (:lead)',
                  { lead },
              )
            : trans(
                  income
                      ? 'Create the income of :amount (:lead)'
                      : 'Create the expense of :amount (:lead)',
                  { amount: euro, lead },
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

/**
 * The name of an option, by its value.
 *
 * @param options - the options
 * @param id - the id, if there is one
 * @returns the name, or an empty string
 */
function nameOf(options: App.Data.OptionData[], id: number | null): string {
    return options.find((option) => option.value === String(id))?.label ?? '';
}

/**
 * The line under the box of a new expense: its category, without the main one, and its person.
 *
 * @param movement - the expense of the proposal
 * @param options - the expense categories, with their path, and the people
 * @returns for example `Spese mediche · Giulia Bianchi`
 */
export function movementSubtitle(
    movement: App.Data.Reading.MovementProposalData,
    options: {
        categories: App.Data.OptionData[];
        people: App.Data.OptionData[];
    },
): string {
    const category = nameOf(options.categories, movement.values.category_id)
        .split(' › ')
        .at(-1);

    return [category, nameOf(options.people, movement.values.person_id)]
        .filter((part) => part)
        .join(' · ');
}

/**
 * The line under the box of a new health event: its title, person and date.
 *
 * @param event - the event of the proposal
 * @param people - the people
 * @returns for example `«Visita dal dentista» · Giulia Bianchi · 22/10/2026`
 */
export function eventSubtitle(
    event: App.Data.Reading.EventProposalData,
    people: App.Data.OptionData[],
): string {
    const { title, person_id: person, date } = event.values;

    return [
        title ? `«${title}»` : '',
        nameOf(people, person),
        date ? formatDate(date) : '',
    ]
        .filter((part) => part)
        .join(' · ');
}

/**
 * The two choices under the box of the expense: already paid, with its date and account, or to pay.
 *
 * @param movement - the expense of the proposal
 * @param toPay - whether *To pay* is in effect
 * @param accounts - the accounts, to name the one it was paid with
 * @returns each choice, whether it is in effect and what it sends
 */
export function dueChoices(
    movement: App.Data.Reading.MovementProposalData,
    toPay: boolean,
    accounts: App.Data.OptionData[],
): DueChoice[] {
    const { date, financial_account_id: account } = movement.values;
    const accountName = nameOf(accounts, account);
    let paid = trans('Already paid');

    if (date && accountName) {
        paid = trans('Already paid on :date with :account', {
            date: formatDate(date),
            account: accountName,
        });
    } else if (date) {
        paid = trans('Already paid on :date', { date: formatDate(date) });
    }

    const deadline = movement.due?.date;

    return [
        { label: paid, due: false, checked: !toPay },
        {
            label: deadline
                ? trans('To pay by :date', { date: formatDate(deadline) })
                : trans('To pay'),
            due: true,
            checked: toPay,
        },
    ];
}

/**
 * The texts of the actions of a box.
 *
 * @param side - the box
 * @param existing - whether it is linked to one already there
 * @param income - whether the movement is an income
 * @returns the texts of *Create a new …*, of the button that chooses another and of its menu
 */
export function actionTexts(
    side: AlsoSide,
    existing: boolean,
    income: boolean,
): { create: string; choose: string; menu: string } {
    if (side === 'movement') {
        return {
            create: trans(
                income ? 'Create a new income' : 'Create a new expense',
            ),
            choose: existing
                ? trans('Choose another…')
                : trans(
                      income
                          ? 'Link to an income already registered…'
                          : 'Link to an expense already registered…',
                  ),
            menu: trans(income ? 'The income to link' : 'The expense to link'),
        };
    }

    if (side === 'event') {
        return {
            create: trans('Create a new event'),
            choose: existing
                ? trans('Choose another event…')
                : trans('Link to an event already registered…'),
            menu: trans('The event to link'),
        };
    }

    return {
        create: trans('Create a new card'),
        choose: existing
            ? trans('Choose another…')
            : trans('Link to a card already registered…'),
        menu: trans('The card to link'),
    };
}

/**
 * What a box can be linked to instead, as the options of its menu.
 *
 * @param candidates - the expenses, events or cards of the proposal
 * @returns the options: a card by its label, the others by title, date and amount
 */
export function candidateOptions(
    candidates: Candidate[],
): App.Data.OptionData[] {
    return candidates.map((candidate) => ({
        value: String(candidate.id),
        label:
            candidate.label ??
            [
                candidate.title,
                candidate.date ? formatDate(candidate.date) : '',
                candidate.amount === undefined
                    ? ''
                    : formatEuro(candidate.amount),
            ]
                .filter((part) => part)
                .join(' · '),
        help: null,
    }));
}
