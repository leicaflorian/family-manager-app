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

/**
 * The messages of a list field and of its items (`files`, `files.0`,
 * `files.1`…), all together: Laravel gives each item its own key.
 *
 * @param all - the errors of the form, `form.errors`
 * @param field - the name of the list field
 * @returns the messages, in their order
 */
export function listErrors(
    all: Partial<Record<string, string>>,
    field: string,
): string[] {
    return Object.entries(all)
        .filter(([key]) => key === field || key.startsWith(`${field}.`))
        .map(([, message]) => message)
        .filter((message): message is string => typeof message === 'string');
}
