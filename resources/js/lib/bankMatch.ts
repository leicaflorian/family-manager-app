/**
 * What the field *How it shows in the bank* asks the server: the transactions a rule recognises, the transactions
 * to take examples from and the rule the AI writes. The requests go through the HTTP client of Inertia, which sends
 * the token against forged requests; the tests give it a fake client.
 */
import { http } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { formatMoney } from '@/lib/format';
import { examples, matches, rule } from '@/routes/budget/bank';

/** The direction of the form: an expense looks at outgoing transactions, an income at incoming ones. */
export type BankMatchKind = 'expense' | 'income';

/** What the server answers for a rule: how many transactions it recognises and the newest five. */
export type BankMatchResult = {
    /** How many booked transactions of the last 90 days the rule recognises. */
    count: number;
    /** The newest five of them, the oldest of the five first. */
    rows: App.Data.BankTransactionData[];
};

/** The rule the AI wrote, with the sentence that says what it recognises, and what it recognises. */
export type BankRuleResult = BankMatchResult & {
    /** The rule, a PCRE pattern without delimiters. */
    pattern: string;
    /** What the rule recognises, in a simple sentence. */
    explanation: string;
};

/** What a rule is written from: pasted texts, or transactions of the family. */
export type BankRuleSource =
    | { examples: string[] }
    | { transactions: number[] };

/** A request that did not succeed: the status (0 without an answer) and what to tell the person. */
export class BankMatchError extends Error {
    /**
     * @param status - the HTTP status, 0 when the server did not answer
     * @param message - the reason, for the person
     */
    constructor(
        readonly status: number,
        message: string,
    ) {
        super(message);
        this.name = 'BankMatchError';
    }
}

/** The body of an answer read as an object, whatever its kind. */
function bodyOf(data: unknown): Record<string, unknown> | null {
    if (data !== null && typeof data === 'object') {
        return data as Record<string, unknown>;
    }

    if (typeof data !== 'string') {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse(data);

        return parsed !== null && typeof parsed === 'object'
            ? (parsed as Record<string, unknown>)
            : null;
    } catch {
        return null;
    }
}

/**
 * The first message of a 422: the first of `errors`, or `message`.
 *
 * @param body - the answer read as an object
 * @returns the message, or null
 */
function messageOf(body: Record<string, unknown> | null): string | null {
    const errors = body?.errors;

    if (errors !== null && typeof errors === 'object') {
        for (const list of Object.values(errors)) {
            if (
                Array.isArray(list) &&
                typeof list[0] === 'string' &&
                list[0] !== ''
            ) {
                return list[0];
            }
        }
    }

    return typeof body?.message === 'string' && body.message !== ''
        ? body.message
        : null;
}

/**
 * Makes a request and gives its answer as an object.
 *
 * @param method - `get` or `post`
 * @param url - the address
 * @param data - the body of a `post`
 * @returns the answer
 * @throws BankMatchError when the server says no or does not answer
 */
async function call(
    method: 'get' | 'post',
    url: string,
    data?: Record<string, unknown>,
    signal?: AbortSignal,
): Promise<Record<string, unknown>> {
    try {
        const response = await http.getClient().request({
            method,
            url,
            data,
            headers: { Accept: 'application/json' },
            signal,
        });

        return bodyOf(response.data) ?? {};
    } catch (error) {
        const response = (
            error as { response?: { status?: number; data?: unknown } } | null
        )?.response;
        const status =
            typeof response?.status === 'number' ? response.status : 0;
        const message = messageOf(bodyOf(response?.data));

        throw new BankMatchError(
            status,
            message ??
                (status === 0
                    ? trans('There is no network: try again in a while.')
                    : trans('This did not work. Try again in a while.')),
        );
    }
}

/**
 * The transactions a rule recognises.
 *
 * @param pattern - the rule
 * @param kind - the direction of the form
 * @param accountId - the account the form has chosen, null for all of them
 * @param signal - stops the request when the answer is not wanted any more
 * @returns how many and the newest five
 * @throws BankMatchError with the message of the rule when it does not work (422)
 */
export async function previewMatches(
    pattern: string,
    kind: BankMatchKind,
    accountId: number | null,
    signal?: AbortSignal,
): Promise<BankMatchResult> {
    const body = await call(
        'get',
        matches.url({
            query: {
                pattern,
                kind,
                ...(accountId === null ? {} : { account_id: accountId }),
            },
        }),
        undefined,
        signal,
    );

    return {
        count: typeof body.count === 'number' ? body.count : 0,
        rows: Array.isArray(body.rows)
            ? (body.rows as App.Data.BankTransactionData[])
            : [],
    };
}

/**
 * The last transactions to sort, to take examples from.
 *
 * @param kind - the direction of the form
 * @param accountId - the account the form has chosen, null for all of them
 * @returns the transactions, newest first
 */
export async function fetchExamples(
    kind: BankMatchKind,
    accountId: number | null,
): Promise<App.Data.BankTransactionData[]> {
    const body = await call(
        'get',
        examples.url({
            query: {
                kind,
                ...(accountId === null ? {} : { account_id: accountId }),
            },
        }),
    );

    return Array.isArray(body.rows)
        ? (body.rows as App.Data.BankTransactionData[])
        : [];
}

/**
 * Asks the AI for the rule of some examples.
 *
 * @param source - pasted texts or ids of transactions
 * @param kind - the direction of the form
 * @param accountId - the account the form has chosen, null for all of them
 * @returns the rule, its sentence and what it recognises
 * @throws BankMatchError with what to tell the person when the rule could not be written
 */
export async function writeRule(
    source: BankRuleSource,
    kind: BankMatchKind,
    accountId: number | null,
): Promise<BankRuleResult> {
    const body = await call('post', rule.url(), {
        ...source,
        kind,
        ...(accountId === null ? {} : { account_id: accountId }),
    });

    return {
        pattern: typeof body.pattern === 'string' ? body.pattern : '',
        explanation:
            typeof body.explanation === 'string' ? body.explanation : '',
        count: typeof body.count === 'number' ? body.count : 0,
        rows: Array.isArray(body.rows)
            ? (body.rows as App.Data.BankTransactionData[])
            : [],
    };
}

/**
 * A transaction as the line of a preview: its day and month and its amount, without the sign.
 *
 * @param row - the transaction
 * @returns for example `05/09 72,10 €`
 */
export function matchLine(row: App.Data.BankTransactionData): string {
    const day = (row.booked_on ?? row.value_on ?? '').slice(0, 10);
    const [, month, date] = day.split('-');
    const amount = formatMoney(Math.abs(row.amount), row.currency);

    return month && date ? `${date}/${month} ${amount}` : amount;
}

/**
 * What a rule recognises, in words.
 *
 * @param result - the count and the rows the server gave
 * @returns «It matches 3 movements: 05/09 72,10 €, …», or that it matches none
 */
export function matchSummary(result: BankMatchResult): string {
    if (result.count === 0) {
        return trans('It does not match any movement of the last 90 days.');
    }

    const list = [
        result.rows.map(matchLine).join(', '),
        result.count > result.rows.length ? '…' : '',
    ]
        .filter((part) => part !== '')
        .join(', ');

    return transChoice(
        '{1} It matches 1 movement: :list|[2,*] It matches :count movements: :list',
        result.count,
        { list },
    );
}
