import { describe, expect, it } from 'vite-plus/test';
import {
    formatAmount,
    isEmpty,
    parseAmount,
    withMethod,
    withNone,
} from './forms';

describe('withMethod', () => {
    it('leaves the data of a POST as they are', () => {
        expect(withMethod({ name: 'Coop' }, 'post')).toEqual({ name: 'Coop' });
    });

    it('sends a PUT as a POST with _method, so a file can go with it', () => {
        expect(withMethod({ name: 'Coop' }, 'put')).toEqual({
            name: 'Coop',
            _method: 'put',
        });
    });
});

describe('isEmpty', () => {
    it('is true for nothing written or chosen, and false for zero and "no"', () => {
        expect(isEmpty(null)).toBe(true);
        expect(isEmpty(undefined)).toBe(true);
        expect(isEmpty('')).toBe(true);
        expect(isEmpty(0)).toBe(false);
        expect(isEmpty(false)).toBe(false);
        expect(isEmpty('x')).toBe(false);
    });
});

describe('withNone', () => {
    it('puts the empty choice in front and keeps only the value and the label', () => {
        expect(
            withNone([{ value: '1', label: 'Conto BPER', help: 'x' }]),
        ).toEqual([
            { value: '', label: 'None' },
            { value: '1', label: 'Conto BPER' },
        ]);
    });
});

describe('parseAmount', () => {
    it('reads a point or a comma, and an empty field as null', () => {
        expect(parseAmount('3.50')).toBe(3.5);
        expect(parseAmount('3,50')).toBe(3.5);
        expect(parseAmount(45)).toBe(45);
        expect(parseAmount('')).toBeNull();
        expect(parseAmount(null)).toBeNull();
    });
});

describe('formatAmount', () => {
    it('writes two decimals for a currency field, empty when there is no amount', () => {
        expect(formatAmount(45)).toBe('45.00');
        expect(formatAmount(null)).toBe('');
        expect(formatAmount(undefined)).toBe('');
    });
});
