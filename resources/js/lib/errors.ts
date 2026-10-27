/**
 * The validation errors that come from Laravel, in the shape FormKit asks for.
 */

/**
 * Turns the error message of a field (`form.errors.email`) into the list of
 * messages of the `errors` prop of `<FormKit>`.
 *
 * @param message - the message of the server, if there is one
 * @returns the list of messages to show under the field
 */
export function errors(message: string | undefined): string[] {
    return message ? [message] : [];
}
