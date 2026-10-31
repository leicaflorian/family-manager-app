/**
 * Helpers for the forms: the method of a form with files, empty fields, menus that can stay empty and amounts.
 */
import { trans } from 'laravel-vue-i18n';

/**
 * The data of a form sent as a POST, with the real method in `_method`:
 * PHP reads the files of a multipart request only in a POST, and Laravel
 * takes the method from `_method`.
 *
 * @param data - the fields of the form
 * @param method - the method of the route, for example `put`
 * @returns the fields, with `_method` when the method is not POST
 */
export function withMethod<T extends object>(
    data: T,
    method: string,
): T | (T & { _method: string }) {
    return method === 'post' ? data : { ...data, _method: method };
}

/**
 * Whether a value is empty: nothing written or chosen. Zero and "no" are values.
 *
 * @param value - the value of a field
 * @returns true if it is empty
 */
export function isEmpty(value: unknown): boolean {
    return value === null || value === undefined || value === '';
}

/**
 * The options of a menu that can stay empty: the empty choice, then the options.
 *
 * @param choices - the options of the menu
 * @returns the options with «None» in front, keeping value and label only
 */
export function withNone(
    choices: { value: string; label: string; help?: string | null }[],
): { value: string; label: string }[] {
    return [
        { value: '', label: trans('None') },
        ...choices.map(({ value, label }) => ({ value, label })),
    ];
}

/**
 * The number in a currency field.
 *
 * @param value - what the field holds, «3.50» or «3,50»
 * @returns the number, or null when the field is empty
 */
export function parseAmount(value: unknown): number | null {
    return isEmpty(value) ? null : Number(String(value).replace(',', '.'));
}

/**
 * An amount for a currency field.
 *
 * @param amount - the amount, or null
 * @returns the amount with two decimals, empty when there is none
 */
export function formatAmount(amount: number | null | undefined): string {
    return amount === null || amount === undefined ? '' : amount.toFixed(2);
}
