import { describe, expect, it } from 'vite-plus/test';
import { ferriProposal } from '@/test/fixtures';
import {
    actionTexts,
    candidateOptions,
    dueChoices,
    dueInEffect,
    eventSubtitle,
    movementSubtitle,
    eventHint,
    eventLabel,
    eventWarnings,
    movementHint,
    movementLabel,
    movementWarnings,
    policyBox,
    recordHint,
    recordLabel,
    recordWarnings,
} from './alsoDo';

const expense = ferriProposal.movement!;
const event = ferriProposal.event!;
const organization = ferriProposal.records[0]!;

/** The expense of the fixture with other values. */
function movement(
    changes: Partial<App.Data.Reading.MovementProposalData>,
    values: Partial<App.Data.Reading.MovementValuesData> = {},
): App.Data.Reading.MovementProposalData {
    return {
        ...expense,
        ...changes,
        values: { ...expense.values, ...values },
    };
}

const plan = { id: 9, title: 'Luce', date: '2026-11-10' };

describe('movementLabel', () => {
    it('creates the expense, or the income, with its amount', () => {
        expect(movementLabel(expense)).toBe('Create the expense of 120,00 €');
        expect(
            movementLabel(movement({}, { kind: 'income', amount: 1500 })),
        ).toBe('Create the income of 1.500,00 €');
    });

    it('names the planned expense or income the movement belongs to', () => {
        const lead = {
            id: 6,
            title: 'Stipendio Marco',
            amount: 1500,
            is_estimated: false,
        };

        expect(
            movementLabel(movement({}, { kind: 'income', amount: 1480, lead })),
        ).toBe('Create the income of 1.480,00 € (Stipendio Marco)');
        expect(
            movementLabel(movement({}, { kind: 'income', amount: null, lead })),
        ).toBe('Create the income (Stipendio Marco)');
    });

    it('creates it without an amount when none was read', () => {
        expect(movementLabel(movement({}, { amount: null }))).toBe(
            'Create the expense',
        );
        expect(
            movementLabel(movement({}, { kind: 'income', amount: null })),
        ).toBe('Create the income');
    });

    it('links to the expense, or the income, already registered', () => {
        const duplicate = { id: 4, title: 'Dentista', date: '2026-10-22' };

        expect(movementLabel(movement({ duplicate }))).toBe(
            'Link to the expense "Dentista" of 22/10/2026',
        );
        expect(movementLabel(movement({ duplicate }, { kind: 'income' }))).toBe(
            'Link to the income "Dentista" of 22/10/2026',
        );
    });

    it('to pay, creates a new expense to pay', () => {
        expect(movementLabel(expense, true)).toBe(
            'Create the expense to pay of 120,00 €',
        );
        expect(movementLabel(movement({}, { amount: null }), true)).toBe(
            'Create the expense to pay',
        );
    });

    it('to pay, links to the expense to pay already there', () => {
        const due = { ...expense.due!, mode: 'existing' as const, plan };

        expect(movementLabel(movement({ due }), true)).toBe(
            'Link to the expense to pay "Luce" of 10/11/2026',
        );
    });

    it('to pay, updates the deadline of a planned expense, on the date of the deadline', () => {
        const due = {
            ...expense.due!,
            mode: 'exception' as const,
            plan,
            occurrence: '2026-12-10',
        };

        expect(movementLabel(movement({ due }), true)).toBe(
            'Update the deadline of "Luce" of 10/12/2026: 120,00 €',
        );
        expect(
            movementLabel(
                movement(
                    { due: { ...due, occurrence: null } },
                    { amount: null },
                ),
                true,
            ),
        ).toBe('Update the deadline of "Luce" of 10/11/2026');
    });

    it('records the payment of an expense to pay', () => {
        const pays = {
            planned: 9,
            title: 'Luce',
            date: '2026-11-10',
            occurrence: '2026-11-10',
            others: 0,
        };

        expect(movementLabel(movement({ pays }))).toBe(
            'Record the payment of "Luce" of 10/11/2026',
        );
        // To pay wins over the payment
        expect(movementLabel(movement({ pays }), true)).toBe(
            'Create the expense to pay of 120,00 €',
        );
    });
});

describe('dueInEffect', () => {
    it('follows the choice of the person, otherwise the proposal', () => {
        expect(dueInEffect(expense, null)).toBe(false);
        expect(dueInEffect(expense, true)).toBe(true);
        expect(
            dueInEffect(
                movement({ due: { ...expense.due!, suggested: true } }),
                null,
            ),
        ).toBe(true);
    });

    it('is never in effect without a deadline to propose', () => {
        expect(dueInEffect(movement({ due: null }), true)).toBe(false);
    });
});

describe('eventLabel', () => {
    it('creates the health event', () => {
        expect(eventLabel(event)).toBe('Create the health event');
    });

    it('links to the event already there', () => {
        expect(
            eventLabel({
                ...event,
                existing: { id: 3, title: 'Dentista', date: '2026-10-22' },
            }),
        ).toBe('Link to the event "Dentista" of 22/10/2026');
    });
});

describe('recordLabel', () => {
    it('creates the card, named by the server', () => {
        expect(recordLabel(organization)).toBe(
            'Create the organization Studio Ferri',
        );
    });

    it('links to the card already there, with the words of its kind', () => {
        const existing = { id: 5, title: 'Studio dentistico' };
        const cases: [App.Enums.RecordKind, string][] = [
            ['organization', 'Link to the organization "Studio dentistico"'],
            ['vehicle', 'Link to the vehicle "Studio dentistico"'],
            ['utility', 'Link to the utility "Studio dentistico"'],
            ['policy', 'Link to the policy "Studio dentistico"'],
            ['belonging', 'Link to the item "Studio dentistico"'],
            ['maintenance', 'Link to the intervention "Studio dentistico"'],
        ];

        for (const [kind, label] of cases) {
            expect(recordLabel({ ...organization, kind, existing })).toBe(
                label,
            );
        }
    });
});

const duplicate = { id: 4, title: 'Dentista', date: '2026-10-22' };

describe('movementHint', () => {
    const ticked = { ticked: true, due: false, target: 'auto' as const };

    it('shows the subtitle of a new expense', () => {
        expect(movementHint(expense, ticked, 'Spese mediche · Giulia')).toBe(
            'Spese mediche · Giulia',
        );
        expect(movementHint(expense, ticked, '')).toBeUndefined();
    });

    it('says why an expense that does not look like a payment starts unticked', () => {
        expect(
            movementHint(
                movement({ suggested: false }),
                { ...ticked, ticked: false },
                'Spese mediche',
            ),
        ).toBe(
            'It does not look like a payment: tick the box to create the expense anyway.',
        );
    });

    it('says the expense is already registered, or the one chosen is linked', () => {
        expect(movementHint(movement({ duplicate }), ticked, '')).toBe(
            'The expense is already registered (same commitment, or same amount and organization on close dates): instead of creating another, the document is linked to that one.',
        );
        expect(
            movementHint(
                movement({ duplicate }, { kind: 'income' }),
                ticked,
                '',
            ),
        ).toBe(
            'The income is already registered (same commitment, or same amount and organization on close dates): instead of creating another, the document is linked to that one.',
        );
        expect(
            movementHint(
                movement({ duplicate }),
                { ...ticked, target: 41 },
                '',
            ),
        ).toBe('The document is linked to the expense you chose.');
    });

    it('says a receipt pays an expense to pay', () => {
        const pays = {
            planned: 9,
            title: 'Luce',
            date: '2026-11-10',
            occurrence: '2026-11-10',
            others: 0,
        };

        expect(movementHint(movement({ pays }), ticked, '')).toBe(
            'The receipt pays an expense to pay: the payment is recorded on that one.',
        );
    });
});

describe('movementWarnings', () => {
    const ticked = { ticked: true, due: false };

    it('says what is missing to create it, only while it is ticked', () => {
        const blocked = movement({
            blocked: 'To create the expense the amount is missing.',
        });

        expect(movementWarnings(blocked, ticked)).toEqual([
            'To create the expense the amount is missing.',
        ]);
        expect(movementWarnings(blocked, { ...ticked, ticked: false })).toEqual(
            [],
        );
    });

    it('says what changes on the expense found, and how many others were as close', () => {
        expect(
            movementWarnings(
                movement({
                    duplicate,
                    changes: ['Fee: 0,10 €'],
                    warnings: ['The receipt is of 30,10 €.'],
                    others: 2,
                }),
                ticked,
            ),
        ).toEqual([
            'On the expense changes: Fee: 0,10 €',
            'The receipt is of 30,10 €.',
            'There are 2 other expenses with the same amount close to this date: if it is not the right one, choose another.',
        ]);
    });

    it('says how many other expenses to pay a receipt could pay', () => {
        const pays = {
            planned: 9,
            title: 'Luce',
            date: '2026-11-10',
            occurrence: '2026-11-10',
            others: 1,
        };

        expect(movementWarnings(movement({ pays }), ticked)).toEqual([
            'There is 1 other expense to pay with the same amount close to this date.',
        ]);
    });
});

describe('eventHint', () => {
    const ticked = { ticked: true, target: 'auto' as const };

    it('shows the subtitle of a new event', () => {
        expect(eventHint(event, ticked, '«Visita» · Giulia')).toBe(
            '«Visita» · Giulia',
        );
    });

    it('says why a prescription starts unticked', () => {
        expect(
            eventHint(
                { ...event, suggested: false },
                { ...ticked, ticked: false },
                '',
            ),
        ).toBe(
            'A prescription usually comes from a visit already registered: tick the box if you want its event anyway.',
        );
    });

    it('says the event is already there, or the one chosen is linked', () => {
        const existing = { ...event, existing: duplicate };

        expect(eventHint(existing, ticked, '')).toBe(
            'There is already an event of this person (same day, or same place and same type a few days apart): instead of creating another, the document is linked to that one, and the reading only fills its empty fields.',
        );
        expect(eventHint(existing, { ...ticked, target: 3 }, '')).toBe(
            'The document is linked to the event you chose, and the reading only fills its empty fields.',
        );
    });
});

describe('eventWarnings', () => {
    it('says what a new event misses, while it is ticked', () => {
        const blocked = { ...event, blocked: 'The person is missing.' };

        expect(eventWarnings(blocked, true)).toEqual([
            'The person is missing.',
        ]);
        expect(eventWarnings(blocked, false)).toEqual([]);
    });
});

describe('recordHint', () => {
    it('says why a card starts unticked', () => {
        expect(
            recordHint(
                { ...organization, suggested: false },
                { ticked: false, target: 'auto' },
            ),
        ).toBe(
            'A shop on a receipt does not become an organization by itself: tick the box if you want to keep it among the organizations.',
        );
        expect(
            recordHint(
                { ...organization, kind: 'belonging', suggested: false },
                { ticked: false, target: 'auto' },
            ),
        ).toBe(
            'Under 100 € a purchase does not become an item by itself: tick the box if you want to keep it among the items, with the warranty.',
        );
    });

    it('says the card is already there, or the one chosen is linked', () => {
        const existing = {
            ...organization,
            existing: { id: 5, title: 'Studio dentistico' },
        };

        expect(recordHint(existing, { ticked: true, target: 'auto' })).toBe(
            'There is already a card that matches: instead of creating another, the document is linked to that one.',
        );
        expect(recordHint(existing, { ticked: true, target: 5 })).toBe(
            'The document is linked to the card you chose.',
        );
    });
});

describe('recordWarnings', () => {
    it('says what a new card misses, while it is ticked', () => {
        expect(
            recordWarnings({ ...organization, missing: ['the name'] }, true),
        ).toEqual(['To create it the name is missing.']);
        expect(
            recordWarnings(
                { ...organization, missing: ['the name', 'the type'] },
                true,
            ),
        ).toEqual(['To create it the name, the type are missing.']);
        expect(
            recordWarnings({ ...organization, missing: ['the name'] }, false),
        ).toEqual([]);
    });
});

describe('policyBox', () => {
    const giver: App.Data.Reading.PolicyGiverData = {
        key: 'file:9',
        fileName: 'Contratto.pdf',
        number: '40/123456',
        creates: true,
        organizationId: null,
        organizationName: 'UnipolSai',
        personId: null,
        type: 'car',
    };

    it('is not there when the document needs no policy', () => {
        expect(policyBox(null, 'auto')).toBeUndefined();
        expect(
            policyBox({ need: true, giver: null, many: false }, 'auto'),
        ).toBeUndefined();
    });

    it('links the policy a file creates, or one already registered', () => {
        expect(policyBox({ need: true, giver, many: false }, 'auto')).toEqual({
            label: 'Link to the policy 40/123456',
            hint: 'The document is linked to the policy 40/123456, which the file Contratto.pdf creates.',
            warnings: [],
            ticked: true,
            disabled: false,
        });
        expect(
            policyBox(
                {
                    need: true,
                    giver: { ...giver, creates: false },
                    many: false,
                },
                'auto',
            )?.hint,
        ).toBe(
            'The document is linked to the policy 40/123456, already registered, found from the file Contratto.pdf.',
        );
        expect(
            policyBox(
                {
                    need: true,
                    giver: { ...giver, creates: false, fileName: null },
                    many: false,
                },
                'auto',
            )?.hint,
        ).toBe(
            'The document is linked to the policy 40/123456, already registered.',
        );
    });

    it('says the document is not linked once the person left the policy', () => {
        expect(
            policyBox({ need: true, giver: null, many: false }, 'none'),
        ).toMatchObject({
            label: 'Link to a policy',
            hint: 'The document is not linked to a policy.',
            ticked: false,
        });
    });

    it('says more policies fit, and links none', () => {
        expect(
            policyBox({ need: true, giver: null, many: true }, 'auto'),
        ).toMatchObject({
            label: 'Link to a policy',
            warnings: [
                'There are more policies that fit in this import: choose the one of the document.',
            ],
            ticked: false,
            disabled: true,
        });
    });
});

const people = [{ value: '2', label: 'Giulia Bianchi', help: null }];

describe('movementSubtitle', () => {
    it('names the category, without the main one, and the person', () => {
        expect(
            movementSubtitle(expense, {
                categories: [
                    {
                        value: '12',
                        label: 'Salute › Spese mediche',
                        help: null,
                    },
                ],
                people,
            }),
        ).toBe('Spese mediche · Giulia Bianchi');
    });

    it('leaves out what it does not know', () => {
        expect(movementSubtitle(expense, { categories: [], people: [] })).toBe(
            '',
        );
    });
});

describe('eventSubtitle', () => {
    it('names the title, the person and the date', () => {
        expect(eventSubtitle(event, people)).toBe(
            '«Visita dal dentista» · Giulia Bianchi · 22/10/2026',
        );
        expect(
            eventSubtitle(
                {
                    ...event,
                    values: { ...event.values, title: null, date: null },
                },
                [],
            ),
        ).toBe('');
    });
});

describe('dueChoices', () => {
    const accounts = [{ value: '3', label: 'Carta Laura', help: null }];

    it('offers already paid, with the date and the account, and to pay', () => {
        expect(dueChoices(expense, false, accounts)).toEqual([
            {
                label: 'Already paid on 22/10/2026 with Carta Laura',
                due: false,
                checked: true,
            },
            { label: 'To pay', due: true, checked: false },
        ]);
    });

    it('says the deadline, and leaves out the date or the account it does not know', () => {
        const due = { ...expense.due!, date: '2026-11-30' };

        expect(
            dueChoices(movement({ due }, { date: null }), true, accounts).map(
                (choice) => [choice.label, choice.checked],
            ),
        ).toEqual([
            ['Already paid', false],
            ['To pay by 30/11/2026', true],
        ]);
        expect(dueChoices(expense, false, [])[0]?.label).toBe(
            'Already paid on 22/10/2026',
        );
    });
});

describe('actionTexts', () => {
    it('speaks of the expense, the income, the event or the card', () => {
        expect(actionTexts('movement', true, false)).toEqual({
            create: 'Create a new expense',
            choose: 'Choose another…',
            menu: 'The expense to link',
        });
        expect(actionTexts('movement', false, true)).toEqual({
            create: 'Create a new income',
            choose: 'Link to an income already registered…',
            menu: 'The income to link',
        });
        expect(actionTexts('event', true, false)).toEqual({
            create: 'Create a new event',
            choose: 'Choose another event…',
            menu: 'The event to link',
        });
        expect(actionTexts('vehicle', false, false)).toEqual({
            create: 'Create a new card',
            choose: 'Link to a card already registered…',
            menu: 'The card to link',
        });
    });
});

describe('candidateOptions', () => {
    it('names an expense by title, date and amount, an event by title and date, a card by its label', () => {
        expect(candidateOptions(expense.candidates)).toEqual([
            {
                value: '41',
                label: 'Dentista settembre · 15/09/2026 · 80,00 €',
                help: null,
            },
        ]);
        expect(
            candidateOptions([
                { id: 3, title: 'Dentista', date: '2026-10-22' },
            ]),
        ).toEqual([{ value: '3', label: 'Dentista · 22/10/2026', help: null }]);
        expect(candidateOptions(organization.candidates)).toEqual([
            { value: '5', label: 'Studio dentistico', help: null },
        ]);
    });
});
