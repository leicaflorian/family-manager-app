/**
 * Helpers for the tests of the field *How it shows in the bank*: a fake of the HTTP client of Inertia that answers
 * the three calls of the field, and transactions as the server sends them.
 */
import { http } from '@inertiajs/vue3';
import { vi } from 'vite-plus/test';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

/** A transaction of the bank as `BankTransactionData` sends it. */
export function bankRow(
    id: number,
    day: string,
    amount: number,
    text = 'PAGAMENTO CARTA SCUOLA PASSO A DUE',
): App.Data.BankTransactionData {
    return {
        id,
        booked_on: day,
        value_on: day,
        text,
        counterparty: null,
        amount,
        currency: 'EUR',
        account: 'Carta Laura ·· 7731',
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
    };
}

/** What the fake answers: a function of the request, a 200 with the body or an error with its status. */
export type FakeAnswer =
    | { status: number; body: unknown }
    | ((config: Config) => { status: number; body: unknown });

const real = http.getClient();

/**
 * Puts a fake HTTP client in place; the answer of each call is chosen by the path of its address.
 *
 * @param answers - the answer by path (`/budget/bank/matches`, `/budget/bank/examples`, `/budget/bank/rule`)
 * @returns the spy of the requests, and the function that puts the real client back
 */
export function fakeBankServer(answers: Record<string, FakeAnswer>): {
    request: ReturnType<typeof vi.fn>;
    restore: () => void;
} {
    const request = vi.fn((config: Config) => {
        const path = String(config.url).split('?')[0]!;
        const answer = answers[path];
        const given =
            typeof answer === 'function'
                ? answer(config)
                : (answer ?? { status: 404, body: {} });
        const response = {
            status: given.status,
            data: JSON.stringify(given.body),
            headers: {},
        };

        return given.status < 400
            ? Promise.resolve(response)
            : Promise.reject(
                  Object.assign(new Error(`status ${given.status}`), {
                      response,
                  }),
              );
    });

    http.setClient({ request } as unknown as Client);

    return { request, restore: () => http.setClient(real) };
}
