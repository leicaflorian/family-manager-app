import { http } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    BankMatchError,
    fetchExamples,
    matchLine,
    matchSummary,
    previewMatches,
    writeRule,
} from './bankMatch';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const request =
    vi.fn<
        (
            config: Config,
        ) => Promise<{ status: number; data: unknown; headers: object }>
    >();

const row = (id: number, day: string, amount: number) =>
    ({
        id,
        booked_on: day,
        value_on: day,
        text: 'PAGAMENTO SCUOLA',
        counterparty: null,
        amount,
        currency: 'EUR',
        account: 'Carta ·· 3307',
        pending: false,
        ignored: false,
        recurring: false,
        status: 'unsorted',
        link: null,
        provider_id: 'tx-' + String(id),
        read_at: '2026-11-06T08:00',
        bank_status: 'booked',
        fee: null,
        orphan_fee: false,
        transfer: null,
        account_id: 4,
        two_filters: null,
    }) satisfies App.Data.BankTransactionData;

beforeEach(() => {
    request.mockReset();
    http.setClient({ request } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
});

describe('previewMatches', () => {
    it('asks for the rule, the direction and the account, and gives the count and the rows', async () => {
        request.mockResolvedValue({
            status: 200,
            headers: {},
            data: JSON.stringify({
                count: 2,
                rows: [row(1, '2026-09-05', -72.1)],
            }),
        });

        const result = await previewMatches('SCUOLA', 'expense', 7);

        expect(result).toEqual({
            count: 2,
            rows: [row(1, '2026-09-05', -72.1)],
        });
        const config = request.mock.calls[0]![0];
        expect(config.method).toBe('get');
        expect(config.url).toContain('/budget/bank/matches?');
        expect(config.url).toContain('pattern=SCUOLA');
        expect(config.url).toContain('kind=expense');
        expect(config.url).toContain('account_id=7');
    });

    it('leaves the account out when none is chosen', async () => {
        request.mockResolvedValue({
            status: 200,
            headers: {},
            data: { count: 0, rows: [] },
        });

        await previewMatches('SCUOLA', 'income', null);

        expect(request.mock.calls[0]![0].url).not.toContain('account_id');
    });

    it('throws the message of a rule that does not work', async () => {
        request.mockRejectedValue(
            Object.assign(new Error('422'), {
                response: {
                    status: 422,
                    data: JSON.stringify({
                        message: 'The pattern is not valid.',
                        errors: {
                            pattern: [
                                'This rule does not work. Try writing it with the AI.',
                            ],
                        },
                    }),
                },
            }),
        );

        await expect(previewMatches('.*', 'expense', null)).rejects.toThrow(
            new BankMatchError(
                422,
                'This rule does not work. Try writing it with the AI.',
            ),
        );
    });

    it('tells that there is no network when the server does not answer', async () => {
        request.mockRejectedValue(new Error('Network Error'));

        await expect(
            previewMatches('SCUOLA', 'expense', null),
        ).rejects.toMatchObject({
            status: 0,
            message: 'There is no network: try again in a while.',
        });
    });
});

describe('fetchExamples', () => {
    it('gives the transactions to take examples from', async () => {
        request.mockResolvedValue({
            status: 200,
            headers: {},
            data: { rows: [row(3, '2026-11-05', -45)] },
        });

        expect(await fetchExamples('expense', null)).toEqual([
            row(3, '2026-11-05', -45),
        ]);
        expect(request.mock.calls[0]![0].url).toContain(
            '/budget/bank/examples?kind=expense',
        );
    });
});

describe('writeRule', () => {
    it('posts the pasted texts or the transactions with the direction', async () => {
        request.mockResolvedValue({
            status: 200,
            headers: {},
            data: {
                pattern: 'SCUOLA.*DUE',
                explanation: 'The school.',
                count: 1,
                rows: [row(1, '2026-09-05', -45)],
            },
        });

        const result = await writeRule({ examples: ['A', 'B'] }, 'expense', 4);

        expect(result.pattern).toBe('SCUOLA.*DUE');
        const config = request.mock.calls[0]![0];
        expect(config.method).toBe('post');
        expect(config.url).toBe('/budget/bank/rule');
        expect(config.data).toEqual({
            examples: ['A', 'B'],
            kind: 'expense',
            account_id: 4,
        });

        await writeRule({ transactions: [9] }, 'income', null);
        expect(request.mock.calls[1]![0].data).toEqual({
            transactions: [9],
            kind: 'income',
        });
    });

    it('throws what the reading says when it cannot write the rule', async () => {
        request.mockRejectedValue(
            Object.assign(new Error('422'), {
                response: {
                    status: 422,
                    data: {
                        errors: { examples: ['Automatic reading is not on.'] },
                    },
                },
            }),
        );

        await expect(
            writeRule({ examples: ['A'] }, 'expense', null),
        ).rejects.toThrow('Automatic reading is not on.');
    });
});

describe('the words about a rule', () => {
    it('writes a transaction as its day and month and its amount, without the sign', () => {
        expect(matchLine(row(1, '2026-09-05', -72.1))).toBe('05/09 72,10 €');
        expect(
            matchLine({
                ...row(2, '2026-10-05', 68.9),
                booked_on: null,
                value_on: '2026-10-06',
            }),
        ).toBe('06/10 68,90 €');
    });

    it('lists what the rule recognises, or says it recognises nothing', () => {
        const rows = [
            row(1, '2026-09-05', -72.1),
            row(2, '2026-10-05', -68.9),
            row(3, '2026-11-05', -72.4),
        ];

        expect(matchSummary({ count: 3, rows })).toBe(
            'It matches 3 movements: 05/09 72,10 €, 05/10 68,90 €, 05/11 72,40 €',
        );
        expect(matchSummary({ count: 1, rows: [rows[0]!] })).toBe(
            'It matches 1 movement: 05/09 72,10 €',
        );
        expect(matchSummary({ count: 8, rows })).toBe(
            'It matches 8 movements: 05/09 72,10 €, 05/10 68,90 €, 05/11 72,40 €, …',
        );
        expect(matchSummary({ count: 0, rows: [] })).toBe(
            'It does not match any movement of the last 90 days.',
        );
    });
});
