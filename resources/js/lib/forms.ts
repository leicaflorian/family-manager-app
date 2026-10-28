/**
 * Helpers for the forms that send files.
 */

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
