import { http } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { bankFilterPreview, bankFilters } from '@/test/fixtures';
import {
    actionLine,
    actionPieces,
    criteriaLine,
    criteriaParts,
    formPayload,
    formValues,
    previewFilter,
    summaryLine,
} from './bankFilters';

type Client = ReturnType<typeof http.getClient>;

const satispay = bankFilters[1]!;
const luce = bankFilters[0]!;

/** A filter of the fixtures with some fields changed. */
function filter(
    changes: Partial<App.Data.BankFilterData>,
): App.Data.BankFilterData {
    return { ...satispay, ...changes };
}

const noSummary = { pay: 0, create: 0, link: 0, transfer: 0, ignore: 0 };

afterEach(() => {
    vi.restoreAllMocks();
});

describe('criteriaLine', () => {
    it('says the words, the words left out, the direction and the account', () => {
        expect(
            criteriaLine(filter({ account: 'Conto BancoPosta ·· 3307' })),
        ).toBe(
            'contains "SATISPAY" "DOMICILIAZIONE" · does not contain "COMMISSIONI" · going out · Conto BancoPosta ·· 3307',
        );
    });

    it('says every account and the amounts when the filter has them', () => {
        expect(
            criteriaLine(
                filter({
                    excludes: [],
                    direction: 'income',
                    amount_min: 10,
                    amount_max: 50,
                }),
            ),
        ).toBe(
            'contains "SATISPAY" "DOMICILIAZIONE" · coming in · all the accounts · from 10,00 € to 50,00 €',
        );
        expect(criteriaLine(filter({ amount_min: 10 }))).toContain(
            'from 10,00 €',
        );
        expect(criteriaLine(filter({ amount_max: 50 }))).toContain(
            'up to 50,00 €',
        );
    });

    it('says the expression of an advanced filter', () => {
        const advanced = filter({
            pattern: 'ENEL\\s+ENERGIA.*(BOLL|FATT)',
            contains: [],
            excludes: [],
        });

        expect(criteriaLine(advanced)).toBe(
            'expression ENEL\\s+ENERGIA.*(BOLL|FATT) · going out',
        );
        expect(criteriaParts(advanced)[0]).toEqual({
            kind: 'pattern',
            label: 'expression',
            pattern: 'ENEL\\s+ENERGIA.*(BOLL|FATT)',
        });
    });
});

describe('actionLine', () => {
    it('says the expense it creates, with category, organization and commission', () => {
        expect(actionLine(satispay)).toBe(
            'creates the expense «Ricarica Satispay» · Varie · Satispay · with the commission',
        );
        expect(actionPieces(satispay)).toEqual({
            before: 'creates the expense ',
            name: '«Ricarica Satispay»',
            after: ' · Varie · Satispay · with the commission',
        });
    });

    it('says the income it creates, without commission', () => {
        expect(
            actionLine(
                filter({
                    direction: 'income',
                    category: null,
                    fee_mode: 'none',
                    organization: null,
                }),
            ),
        ).toBe('creates the income «Ricarica Satispay»');
    });

    it('says the due date it pays, for an expense and for an income', () => {
        expect(actionLine(luce)).toBe('pays the due date of «Luce»');
        expect(
            actionLine(
                filter({
                    action: 'pay',
                    direction: 'income',
                    planned: {
                        kind: 'income',
                        id: 3,
                        title: 'Assegno Unico',
                    },
                }),
            ),
        ).toBe('marks the planned income «Assegno Unico» received');
    });

    it('says the account of a transfer, from it or to it', () => {
        expect(
            actionLine(
                filter({
                    action: 'transfer',
                    direction: 'income',
                    transfer_account: 'Libretto postale',
                }),
            ),
        ).toBe('is a transfer from «Libretto postale»');
        expect(
            actionLine(
                filter({ action: 'transfer', transfer_account: 'Libretto' }),
            ),
        ).toBe('is a transfer to «Libretto»');
    });

    it('says ignore, and the action an advanced filter has', () => {
        expect(actionLine(filter({ action: 'ignore' }))).toBe('ignores');
        expect(
            actionLine({ ...luce, pattern: 'ENEL.*BOLL', contains: [] }),
        ).toBe('pays the due date of «Luce»');
    });
});

describe('summaryLine', () => {
    it('says what saving creates and links, with the plurals', () => {
        expect(
            summaryLine({ ...noSummary, create: 10, link: 1 }, 'expense'),
        ).toBe('Saving, creates 10 expenses and links 1 already recorded');
        expect(summaryLine({ ...noSummary, create: 1 }, 'expense')).toBe(
            'Saving, creates 1 expense',
        );
        expect(summaryLine({ ...noSummary, link: 2 }, 'income')).toBe(
            'Saving, links 2 incomes already recorded',
        );
    });

    it('says the due dates, the transfers and the movements ignored', () => {
        expect(
            summaryLine(
                { pay: 3, create: 0, link: 0, transfer: 2, ignore: 1 },
                'expense',
            ),
        ).toBe(
            'Saving, pays 3 due dates, marks 2 transfers and ignores 1 movement',
        );
        expect(summaryLine({ ...noSummary, pay: 1 }, 'income')).toBe(
            'Saving, marks 1 income received',
        );
    });

    it('says when saving changes nothing', () => {
        expect(summaryLine(noSummary, 'expense')).toBe(
            'Saving changes nothing among the movements to sort.',
        );
    });
});

describe('previewFilter', () => {
    it('posts the form with the filter being changed and gives the answer', async () => {
        const real = http.getClient();
        const request = vi.fn().mockResolvedValue({
            status: 200,
            headers: {},
            data: JSON.stringify(bankFilterPreview),
        });
        http.setClient({ request } as unknown as Client);

        const answer = await previewFilter({ name: 'Satispay' }, 1);

        http.setClient(real);
        expect(answer).toEqual(bankFilterPreview);
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({
                method: 'post',
                url: '/budget/bank/filters/preview',
                data: { name: 'Satispay', filter: 1 },
            }),
        );
    });
});

describe('the form values', () => {
    it('start from a filter and go back as the save sends them', () => {
        const values = formValues(satispay);

        expect(values).toMatchObject({
            name: 'Satispay',
            contains: ['SATISPAY', 'DOMICILIAZIONE'],
            direction: 'expense',
            financial_account_id: '',
            fee_mode: 'words',
            action: 'create',
            category_id: '3',
            organization_id: '5',
        });
        expect(formPayload({ ...values, amount_min: '10,50' })).toMatchObject({
            name: 'Satispay',
            financial_account_id: null,
            amount_min: 10.5,
            amount_max: null,
            category_id: 3,
            organization_id: 5,
            person_id: null,
            card: null,
            planned_id: null,
        });
    });

    it('take the planned item of a filter that pays, and start a new filter empty', () => {
        expect(formValues(luce).planned_id).toBe('7');
        expect(formValues(null)).toMatchObject({
            name: '',
            contains: [],
            direction: 'expense',
            fee_mode: 'auto',
            action: 'create',
        });
    });
});

describe('the body of an income filter', () => {
    it('has no commission: commissions belong to the money going out', () => {
        expect(
            formPayload({
                ...formValues(satispay),
                direction: 'income',
            }),
        ).toMatchObject({
            fee_mode: 'none',
            fee_contains: [],
            fee_excludes: [],
        });
    });
});
