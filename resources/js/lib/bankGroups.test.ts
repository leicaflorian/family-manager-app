import { describe, expect, it } from 'vite-plus/test';
import { bankGroups } from '@/test/fixtures';
import {
    groupAmounts,
    groupDays,
    groupFilterUrl,
    groupMonths,
    groupProposal,
} from './bankGroups';

const [satispay, inps, eni, cashback, virgin] = bankGroups;

describe('bankGroups', () => {
    it('writes how many movements or incomes and their amounts', () => {
        expect(groupAmounts(satispay)).toBe(
            '11 movements · from 10,00 € to 50,00 €',
        );
        expect(groupAmounts(virgin)).toBe('3 movements · 49,00 € each');
        expect(groupAmounts(inps)).toBe('3 incomes · 407,60 € each');
        expect(groupAmounts(cashback)).toBe(
            '6 incomes · from 0,04 € to 1,20 €',
        );
    });

    it('writes the months, one alone, and the years when they differ', () => {
        expect(groupMonths(satispay)).toBe('from September to November');
        expect(
            groupMonths({ ...satispay, from: '2026-11-02', to: '2026-11-24' }),
        ).toBe('in November');
        expect(
            groupMonths({ ...satispay, from: '2025-12-02', to: '2026-02-24' }),
        ).toBe('from December 2025 to February 2026');
    });

    it('writes the days for the panel', () => {
        expect(groupDays(satispay)).toBe('between 02/09 and 24/11');
    });

    it('says the proposal in a few words, none without one', () => {
        expect(groupProposal(satispay)).toBe(
            'create the expense «Ricarica Satispay» · Varie · Satispay',
        );
        expect(groupProposal({ ...eni, proposal_detail: null })).toBe(
            'create the expense «Carburante»',
        );
        expect(groupProposal(inps)).toBe(
            'mark the planned income «Assegno Unico» as received',
        );
        expect(
            groupProposal({
                ...inps,
                kind: 'expense',
                proposal_detail: 'Netflix',
            }),
        ).toBe('pay the planned expense «Netflix»');
        expect(groupProposal(cashback)).toBeNull();
    });

    it('leads to the new filter of the group, asking the AI only without a proposal', () => {
        expect(groupFilterUrl(satispay)).toBe(
            '/budget/bank/filters/create?group=3f9a1c2b7d4e',
        );
        expect(groupFilterUrl(cashback)).toBe(
            '/budget/bank/filters/create?group=5d7f2a90c3e1&ai=1',
        );
    });
});
