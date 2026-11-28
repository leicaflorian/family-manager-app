/**
 * The words of the filters of the bank (*From the bank → Filters*, boards *Dalla-banca-filtri* and
 * *Dalla-banca-filtro*): the line *Matches* and the line *Action* of a filter, the sentence that says what saving it
 * does, what each movement of the preview will become, and the request of the preview the form asks while the
 * person writes. The list of the tab, the form and the box of a planned expense read them from here.
 */
import { http } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { formatDayMonth, formatEuro } from '@/lib/format';
import { formatAmount, parseAmount } from '@/lib/forms';
import { failedAnswer, jsonBody, serverMessage } from '@/lib/httpAnswer';
import type { MovementFormOptions } from '@/lib/budget';
import { preview } from '@/routes/budget/bank/filters';

/** One piece of the line *Matches*: words (drawn as tags), an expression, or a plain text. */
export type CriteriaPart =
    | {
          kind: 'words';
          label: string;
          words: string[];
          /** The words left out, drawn struck through. */
          excluded: boolean;
      }
    | { kind: 'pattern'; label: string; pattern: string }
    | { kind: 'text'; text: string };

/** What saving a filter would do to the movements to sort, as the preview counts it. */
export type BankFilterSummary = App.Data.BankFilterPreviewData['summary'];

/** The line *Action* cut around the name it speaks of, to draw the name strong. */
export type ActionPieces = {
    before: string;
    name: string | null;
    after: string;
};

/** The fields of the form of a filter, as FormKit holds them: ids as text, amounts as written. */
export interface BankFilterFormValues {
    name: string;
    contains: string[];
    excludes: string[];
    direction: string;
    financial_account_id: string;
    amount_min: string;
    amount_max: string;
    /** The expression of an advanced filter, null for a filter of words. */
    pattern: string | null;
    fee_mode: string;
    fee_contains: string[];
    fee_excludes: string[];
    action: string;
    planned_id: string;
    title: string;
    title_with_month: boolean;
    category_id: string;
    organization_id: string;
    person_id: string;
    card: string | null;
    notes: string;
    transfer_account_id: string;
}

/** The body of the save and of the preview of a filter: the fields as the server reads them. */
export type BankFilterPayload = Record<
    string,
    string | number | boolean | null | string[]
>;

/** The choices of the form of a filter, as `BankFilterController` sends them. */
export interface BankFilterFormOptions extends Omit<
    MovementFormOptions,
    'accounts'
> {
    /** The accounts the bank reads, for the account the filter reads. */
    accounts: { id: number; name: string }[];
    /** The open accounts of the family, for the other side of a transfer. */
    transfer_accounts: { id: number; name: string; connected: boolean }[];
}

/**
 * An id as the text FormKit compares.
 *
 * @param id - the id, or null
 * @returns the text, empty without an id
 */
function idText(id: number | null | undefined): string {
    return id === null || id === undefined ? '' : String(id);
}

/**
 * A text of the form as an id for the server.
 *
 * @param text - the id as text, empty for none
 * @returns the id, or null
 */
function idOf(text: string): number | null {
    return text === '' ? null : Number(text);
}

/**
 * The fields of the form, from the filter being changed or the proposal of a new one; empty without either.
 *
 * @param filter - the filter or the proposal, null for an empty form
 * @returns the fields
 */
export function formValues(
    filter: App.Data.BankFilterData | null,
): BankFilterFormValues {
    return {
        name: filter?.name ?? '',
        contains: [...(filter?.contains ?? [])],
        excludes: [...(filter?.excludes ?? [])],
        direction: filter?.direction ?? 'expense',
        financial_account_id: idText(filter?.financial_account_id),
        amount_min: formatAmount(filter?.amount_min),
        amount_max: formatAmount(filter?.amount_max),
        pattern: filter?.pattern ?? null,
        fee_mode: filter?.fee_mode ?? 'auto',
        fee_contains: [...(filter?.fee_contains ?? [])],
        fee_excludes: [...(filter?.fee_excludes ?? [])],
        action: filter?.action ?? 'create',
        planned_id: idText(
            filter?.planned_expense_id ?? filter?.planned_income_id,
        ),
        title: filter?.title ?? '',
        title_with_month: filter?.title_with_month ?? false,
        category_id: idText(filter?.category_id),
        organization_id: idText(filter?.organization_id),
        person_id: idText(filter?.person_id),
        card: filter?.card ?? null,
        notes: filter?.notes ?? '',
        transfer_account_id: idText(filter?.transfer_account_id),
    };
}

/**
 * The fields of the form as the server reads them, for the save and for the preview: ids as numbers, amounts as
 * numbers, empty fields as null.
 *
 * @param values - the fields of the form
 * @returns the body of the request
 */
export function formPayload(values: BankFilterFormValues): BankFilterPayload {
    return {
        name: values.name,
        contains: values.contains,
        excludes: values.excludes,
        direction: values.direction,
        financial_account_id: idOf(values.financial_account_id),
        amount_min: parseAmount(values.amount_min),
        amount_max: parseAmount(values.amount_max),
        pattern: values.pattern,
        fee_mode: values.fee_mode,
        fee_contains: values.fee_contains,
        fee_excludes: values.fee_excludes,
        action: values.action,
        planned_id: idOf(values.planned_id),
        title: values.title === '' ? null : values.title,
        title_with_month: values.title_with_month,
        category_id: idOf(values.category_id),
        organization_id: idOf(values.organization_id),
        person_id: idOf(values.person_id),
        card: values.card || null,
        notes: values.notes === '' ? null : values.notes,
        transfer_account_id: idOf(values.transfer_account_id),
    };
}

/** Marks where the name goes in a sentence, to cut it around the name. */
const NAME = '\u0000';

/**
 * Cuts a translated sentence around its `:name`.
 *
 * @param key - the sentence, with `:name`
 * @param name - the name, already in «»
 * @returns the text before, the name and the text after
 */
function around(key: string, name: string | null): ActionPieces {
    if (name === null) {
        return {
            before: trans(key.replace(' :name', '')),
            name: null,
            after: '',
        };
    }

    const [before = '', after = ''] = trans(key, { name: NAME }).split(NAME);

    return { before, name, after };
}

/**
 * A name between the Italian quotes, null without a name.
 *
 * @param name - the name, or null
 * @returns «name», or null
 */
function quoted(name: string | null | undefined): string | null {
    return name ? `«${name}»` : null;
}

/**
 * The amounts the filter takes, as «from 10,00 € to 50,00 €», null without amounts.
 *
 * @param min - the smallest amount, or null
 * @param max - the largest amount, or null
 * @returns the text, or null
 */
function amountText(min: number | null, max: number | null): string | null {
    if (min !== null && max !== null) {
        return trans('from :min to :max', {
            min: formatEuro(min),
            max: formatEuro(max),
        });
    }

    if (min !== null) {
        return trans('from :min', { min: formatEuro(min) });
    }

    return max === null ? null : trans('up to :max', { max: formatEuro(max) });
}

/**
 * The pieces of the line *Matches*: the words it needs, the ones it leaves out, the direction, the account and the
 * amounts; an advanced filter shows its expression instead of the words.
 *
 * @param filter - the filter
 * @returns the pieces, in order
 */
export function criteriaParts(filter: App.Data.BankFilterData): CriteriaPart[] {
    const parts: CriteriaPart[] = [];

    if (filter.pattern !== null) {
        parts.push({
            kind: 'pattern',
            label: trans('expression'),
            pattern: filter.pattern,
        });
    } else {
        parts.push({
            kind: 'words',
            label: trans('contains'),
            words: filter.contains,
            excluded: false,
        });
    }

    if (filter.pattern === null && filter.excludes.length > 0) {
        parts.push({
            kind: 'words',
            label: trans('does not contain'),
            words: filter.excludes,
            excluded: true,
        });
    }

    parts.push({
        kind: 'text',
        text:
            filter.direction === 'income'
                ? trans('coming in')
                : trans('going out'),
    });

    // An advanced filter reads every account unless it says one: the board leaves «all the accounts» out
    if (filter.account !== null || filter.pattern === null) {
        parts.push({
            kind: 'text',
            text: filter.account ?? trans('all the accounts'),
        });
    }

    const amounts = amountText(filter.amount_min, filter.amount_max);

    if (amounts !== null) {
        parts.push({ kind: 'text', text: amounts });
    }

    return parts;
}

/**
 * The line *Matches* as one text, the words in quotes.
 *
 * @param filter - the filter
 * @returns «contains "SATISPAY" "DOMICILIAZIONE" · does not contain "COMMISSIONI" · going out · Conto ·· 3307»
 */
export function criteriaLine(filter: App.Data.BankFilterData): string {
    return criteriaParts(filter)
        .map((part) => {
            switch (part.kind) {
                case 'words':
                    return [
                        part.label,
                        ...part.words.map((word) => `"${word}"`),
                    ].join(' ');
                case 'pattern':
                    return `${part.label} ${part.pattern}`;
                default:
                    return part.text;
            }
        })
        .join(' · ');
}

/**
 * The line *Action* cut around the name it speaks of (the expense created, the planned item paid, the account of
 * the transfer).
 *
 * @param filter - the filter
 * @returns the text before the name, the name in «» (or null) and the text after
 */
export function actionPieces(filter: App.Data.BankFilterData): ActionPieces {
    const income = filter.direction === 'income';

    switch (filter.action) {
        case 'create': {
            const details = [
                ...(income ? [] : [filter.category]),
                filter.organization,
                ...(!income && filter.fee_mode !== 'none'
                    ? [trans('with the commission')]
                    : []),
            ].filter((detail): detail is string => !!detail);
            const pieces = around(
                income
                    ? 'creates the income :name'
                    : 'creates the expense :name',
                quoted(filter.title),
            );

            return {
                ...pieces,
                after: [pieces.after, ...details].join(' · '),
            };
        }
        case 'pay':
            return around(
                income
                    ? 'marks the planned income :name received'
                    : 'pays the due date of :name',
                quoted(filter.planned?.title),
            );
        case 'transfer':
            return around(
                income ? 'is a transfer from :name' : 'is a transfer to :name',
                quoted(filter.transfer_account),
            );
        case 'ignore':
            return { before: trans('ignores'), name: null, after: '' };
        default:
            return { before: trans('no action yet'), name: null, after: '' };
    }
}

/**
 * The line *Action* as one text.
 *
 * @param filter - the filter
 * @returns «creates the expense «Ricarica Satispay» · Varie · Satispay · with the commission»
 */
export function actionLine(filter: App.Data.BankFilterData): string {
    const { before, name, after } = actionPieces(filter);

    return `${before}${name ?? ''}${after}`;
}

/**
 * What saving the filter does to the movements to sort: «Saving, creates 10 expenses and links 1 already recorded».
 *
 * @param summary - how many movements each outcome takes
 * @param direction - `expense` or `income`, the movements the filter takes
 * @returns the sentence
 */
export function summaryLine(
    summary: BankFilterSummary,
    direction: string,
): string {
    const income = direction === 'income';
    const clauses: string[] = [];

    if (summary.pay > 0) {
        clauses.push(
            income
                ? transChoice(
                      '{1} marks 1 income received|[2,*] marks :count incomes received',
                      summary.pay,
                  )
                : transChoice(
                      '{1} pays 1 due date|[2,*] pays :count due dates',
                      summary.pay,
                  ),
        );
    }

    if (summary.create > 0) {
        clauses.push(
            income
                ? transChoice(
                      '{1} creates 1 income|[2,*] creates :count incomes',
                      summary.create,
                  )
                : transChoice(
                      '{1} creates 1 expense|[2,*] creates :count expenses',
                      summary.create,
                  ),
        );
    }

    if (summary.link > 0) {
        // After «creates 10 expenses» the kind is already said: «and links 1 already recorded»
        const key =
            summary.create > 0
                ? '{1} links 1 already recorded|[2,*] links :count already recorded'
                : income
                  ? '{1} links 1 income already recorded|[2,*] links :count incomes already recorded'
                  : '{1} links 1 expense already recorded|[2,*] links :count expenses already recorded';
        clauses.push(transChoice(key, summary.link));
    }

    if (summary.transfer > 0) {
        clauses.push(
            transChoice(
                '{1} marks 1 transfer|[2,*] marks :count transfers',
                summary.transfer,
            ),
        );
    }

    if (summary.ignore > 0) {
        clauses.push(
            transChoice(
                '{1} ignores 1 movement|[2,*] ignores :count movements',
                summary.ignore,
            ),
        );
    }

    if (clauses.length === 0) {
        return trans('Saving changes nothing among the movements to sort.');
    }

    const last = clauses.pop() ?? '';
    const what =
        clauses.length === 0
            ? last
            : trans(':first and :last', { first: clauses.join(', '), last });

    return trans('Saving, :what', { what });
}

/**
 * What a movement of the preview will become, for its tag: «Create the expense», «Link «Ricarica Satispay» of
 * 24/11», «Pay the due date of 05/12»…
 *
 * @param row - the movement of the preview
 * @returns the text and its tone: `paid` (green), `action` (blue) or `muted` (grey)
 */
export function willTag(row: App.Data.BankFilterPreviewRowData): {
    text: string;
    tone: 'paid' | 'action' | 'muted';
} {
    const income = row.transaction.amount > 0;
    const day = row.will_on ? formatDayMonth(row.will_on) : null;

    switch (row.will) {
        case 'create':
            return {
                text: income
                    ? trans('Create the income')
                    : trans('Create the expense'),
                tone: 'action',
            };
        case 'link':
            return {
                text:
                    day === null
                        ? trans('Link «:title»', {
                              title: row.will_title ?? '',
                          })
                        : trans('Link «:title» of :day', {
                              title: row.will_title ?? '',
                              day,
                          }),
                tone: 'paid',
            };
        case 'pay':
            if (income) {
                return {
                    text:
                        day === null
                            ? trans('Mark the income received')
                            : trans('Mark the income of :day received', {
                                  day,
                              }),
                    tone: 'action',
                };
            }

            return {
                text:
                    day === null
                        ? trans('Pay the due date')
                        : trans('Pay the due date of :day', { day }),
                tone: 'action',
            };
        case 'transfer':
            return { text: trans('Transfer'), tone: 'action' };
        case 'ignore':
            return { text: trans('Ignore'), tone: 'muted' };
        case 'linked':
            return { text: trans('Already sorted'), tone: 'paid' };
        default:
            return { text: trans('Stays to sort'), tone: 'muted' };
    }
}

/** The preview could not be asked: what to tell the person. */
export class BankFilterPreviewError extends Error {
    /**
     * @param message - the reason, for the person
     */
    constructor(message: string) {
        super(message);
        this.name = 'BankFilterPreviewError';
    }
}

/**
 * Asks the server which movements of the last 90 days the filter written so far takes, and what saving it would do.
 *
 * @param values - the fields of the form, as the save sends them
 * @param changing - the id of the filter being changed (it never conflicts with itself), null for a new one
 * @param signal - stops the request when its answer is not wanted any more
 * @returns the rows, the summary and how many rows another filter takes
 * @throws BankFilterPreviewError when the server says no or does not answer
 */
export async function previewFilter(
    values: BankFilterPayload,
    changing: number | null,
    signal?: AbortSignal,
): Promise<App.Data.BankFilterPreviewData> {
    try {
        const response = await http.getClient().request({
            method: 'post',
            url: preview.url(),
            data: { ...values, filter: changing },
            headers: { Accept: 'application/json' },
            signal,
        });
        const body = jsonBody(response.data) ?? {};

        return {
            rows: Array.isArray(body.rows)
                ? (body.rows as App.Data.BankFilterPreviewRowData[])
                : [],
            summary: {
                pay: 0,
                create: 0,
                link: 0,
                transfer: 0,
                ignore: 0,
                ...(body.summary as Partial<BankFilterSummary> | undefined),
            },
            conflicts: typeof body.conflicts === 'number' ? body.conflicts : 0,
        };
    } catch (error) {
        const { status, body } = failedAnswer(error);

        throw new BankFilterPreviewError(
            serverMessage(body) ??
                (status === 0
                    ? trans('There is no network: try again in a while.')
                    : trans('This did not work. Try again in a while.')),
        );
    }
}
